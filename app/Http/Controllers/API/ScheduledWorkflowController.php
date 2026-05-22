<?php

namespace App\Http\Controllers\API;

use App\Jobs\ExecuteWorkflowJob;
use App\Models\Execution;
use App\Models\ScheduledWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ScheduledWorkflowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schedules = ScheduledWorkflow::with([
            'creator:id,name',
            'workflow:id,name',
            'target:id,name,type,value',
        ])
            ->when($request->workflow_id, fn($q, $id) => $q->where('workflow_id', $id))
            ->when($request->active !== null, fn($q) => $q->where('is_active', (bool) $request->active))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($schedules);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('schedule_workflows');

        $data = $request->validate([
            'workflow_id'     => 'required|exists:workflows,id',
            'target_id'       => 'nullable|exists:targets,id',
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'cron_expression' => ['required', 'string', function ($attr, $value, $fail) {
                try { new \Cron\CronExpression($value); }
                catch (\InvalidArgumentException) { $fail('Invalid cron expression.'); }
            }],
            'input_data'      => 'nullable|array',
            'is_active'       => 'boolean',
        ]);

        $schedule = ScheduledWorkflow::create(array_merge($data, [
            'created_by'  => $request->user()->id,
            'next_run_at' => (new \Cron\CronExpression($data['cron_expression']))->getNextRunDate(),
        ]));

        return response()->json($schedule->load(['workflow:id,name', 'target:id,name,type,value']), 201);
    }

    public function show(ScheduledWorkflow $scheduledWorkflow): JsonResponse
    {
        return response()->json($scheduledWorkflow->load([
            'creator:id,name', 'workflow:id,name', 'target:id,name,type,value',
        ]));
    }

    public function update(Request $request, ScheduledWorkflow $scheduledWorkflow): JsonResponse
    {
        $this->authorize('schedule_workflows');

        $data = $request->validate([
            'target_id'       => 'nullable|exists:targets,id',
            'name'            => 'sometimes|string|max:255',
            'description'     => 'nullable|string',
            'cron_expression' => ['sometimes', 'string', function ($attr, $value, $fail) {
                try { new \Cron\CronExpression($value); }
                catch (\InvalidArgumentException) { $fail('Invalid cron expression.'); }
            }],
            'input_data'      => 'nullable|array',
            'is_active'       => 'boolean',
        ]);

        if (isset($data['cron_expression'])) {
            $data['next_run_at'] = (new \Cron\CronExpression($data['cron_expression']))->getNextRunDate();
        }

        $scheduledWorkflow->update($data);

        return response()->json($scheduledWorkflow->fresh(['workflow:id,name', 'target:id,name,type,value']));
    }

    public function destroy(ScheduledWorkflow $scheduledWorkflow): JsonResponse
    {
        $this->authorize('schedule_workflows');
        $scheduledWorkflow->delete();
        return response()->json(['message' => 'Schedule deleted']);
    }

    public function toggle(ScheduledWorkflow $scheduledWorkflow): JsonResponse
    {
        $this->authorize('schedule_workflows');
        $scheduledWorkflow->update(['is_active' => !$scheduledWorkflow->is_active]);
        return response()->json(['is_active' => $scheduledWorkflow->is_active]);
    }

    public function run(Request $request, ScheduledWorkflow $scheduledWorkflow): JsonResponse
    {
        $this->authorize('execute_workflows');

        $execution = Execution::create([
            'user_id'               => $request->user()->id,
            'workflow_id'           => $scheduledWorkflow->workflow_id,
            'target_id'             => $scheduledWorkflow->target_id,
            'scheduled_workflow_id' => $scheduledWorkflow->id,
            'type'                  => 'workflow',
            'status'                => 'pending',
            'input_data'            => $scheduledWorkflow->input_data ?? [],
        ]);

        ExecuteWorkflowJob::dispatch($execution);

        $scheduledWorkflow->update([
            'last_run_at'  => now(),
            'last_status'  => 'pending',
            'run_count'    => $scheduledWorkflow->run_count + 1,
            'next_run_at'  => $scheduledWorkflow->computeNextRun(),
        ]);

        return response()->json(['execution_id' => $execution->id], 202);
    }
}
