<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExecutionResource;
use App\Jobs\ExecuteSkillJob;
use App\Jobs\ExecuteWorkflowJob;
use App\Jobs\GenerateExecutionReportJob;
use App\Models\Execution;
use App\Models\Skill;
use App\Services\WordReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExecutionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $executions = Execution::with(['user:id,name', 'skill:id,name', 'workflow:id,name'])
            ->whereNull('parent_execution_id')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->skill_id, fn($q, $id) => $q->where('skill_id', $id))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return ExecutionResource::collection($executions);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'skill_id' => 'required|exists:skills,id',
            'input_data' => 'nullable|array',
        ]);

        $skill = Skill::findOrFail($request->skill_id);

        $execution = Execution::create([
            'user_id' => auth()->id(),
            'skill_id' => $skill->id,
            'type' => 'skill',
            'status' => 'pending',
            'input_data' => $request->input_data ?? [],
        ]);

        ExecuteSkillJob::dispatch($execution);

        return response()->json(['message' => 'Execution started', 'execution_id' => $execution->id], 202);
    }

    public function show(Execution $execution): JsonResponse
    {
        $execution->load([
            'logs',
            'user:id,name',
            'skill:id,name',
            'workflow:id,name',
            'target:id,name,type,value',
            'childExecutions' => fn($q) => $q->with(['skill:id,name', 'logs']),
        ]);

        return response()->json(new ExecutionResource($execution));
    }

    public function destroy(Execution $execution): JsonResponse
    {
        $execution->delete();
        return response()->json(['message' => 'Execution deleted successfully']);
    }

    public function generateReport(Execution $execution, WordReportService $wordService): JsonResponse
    {
        if ($execution->type !== 'workflow') {
            return response()->json(['message' => 'Report generation is only available for workflow executions'], 422);
        }

        if ($execution->status !== 'completed') {
            return response()->json(['message' => 'Execution must be completed before generating a report'], 422);
        }

        if ($execution->report_status === 'processing') {
            return response()->json(['message' => 'Report is already being generated'], 409);
        }

        // Clear stale Word cache so next download reflects the new AI content
        $wordService->clearCache($execution->id);

        $execution->update([
            'report_status' => 'pending',
            'report_error'  => null,
        ]);

        // AI call can take 60+ s — raise the limit for this request
        set_time_limit(300);

        GenerateExecutionReportJob::dispatchSync($execution->id);

        $execution->refresh();

        return response()->json(['message' => 'Report generation completed', 'report_status' => $execution->report_status], 202);
    }

    public function downloadReport(Execution $execution, WordReportService $wordService): \Illuminate\Http\Response
    {
        if ($execution->type !== 'workflow') {
            abort(422, 'Report download is only available for workflow executions.');
        }

        if ($execution->report_status !== 'completed') {
            abort(422, 'Report has not been generated yet.');
        }

        $filePath = $wordService->generate($execution);
        $content  = file_get_contents($filePath);
        // File is cached — do not delete it

        $filename = preg_replace('/[^a-z0-9_\-]/i', '_', $execution->workflow?->name ?? 'report')
            . '_' . now()->format('Ymd') . '.docx';

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($content),
        ]);
    }

    public function retry(Execution $execution): JsonResponse
    {
        if (!in_array($execution->status, ['failed', 'completed'])) {
            return response()->json(['message' => 'Only failed or completed executions can be retried'], 422);
        }

        $execution->update([
            'status'        => 'pending',
            'retry_count'   => $execution->retry_count + 1,
            'error_message' => null,
            'started_at'    => null,
            'completed_at'  => null,
        ]);

        if ($execution->type === 'workflow') {
            ExecuteWorkflowJob::dispatch($execution);
        } else {
            ExecuteSkillJob::dispatch($execution);
        }

        return response()->json(['message' => 'Execution retried', 'execution_id' => $execution->id]);
    }
}
