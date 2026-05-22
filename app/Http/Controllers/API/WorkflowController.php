<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\StoreWorkflowRequest;
use App\Http\Resources\WorkflowResource;
use App\Jobs\ExecuteWorkflowJob;
use App\Jobs\RunWorkflowTargetJob;
use App\Models\Execution;
use App\Models\ExecutionTarget;
use App\Models\Skill;
use App\Models\Workflow;
use App\Services\AiProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkflowController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $workflows = Workflow::with('creator:id,name')
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%$s%"))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($request->per_page ?? 15);

        // Batch-load all skill steps needed by this page in one query
        $skillIds = collect($workflows->items())
            ->flatMap(fn($wf) => collect($wf->graph_data['nodes'] ?? [])
                ->map(fn($n) => $n['data']['skill_id'] ?? $n['data']['skillId'] ?? null)
                ->filter()
            )
            ->unique()
            ->values()
            ->all();

        $skillMap = count($skillIds)
            ? Skill::with(['steps' => fn($q) => $q->where('is_active', true)])
                ->whereIn('id', $skillIds)
                ->get()
                ->keyBy('id')
            : collect();

        WorkflowResource::$skillMap = $skillMap;

        return WorkflowResource::collection($workflows);
    }

    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        $workflow = Workflow::create([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        return response()->json(new WorkflowResource($workflow), 201);
    }

    public function show(Workflow $workflow): JsonResponse
    {
        return response()->json(new WorkflowResource($workflow->load('creator:id,name')));
    }

    public function update(StoreWorkflowRequest $request, Workflow $workflow): JsonResponse
    {
        $workflow->update($request->validated());
        return response()->json(new WorkflowResource($workflow));
    }

    public function destroy(Workflow $workflow): JsonResponse
    {
        $workflow->delete();
        return response()->json(['message' => 'Workflow deleted successfully']);
    }

    public function preflight(Workflow $workflow): JsonResponse
    {
        $graphData  = $workflow->graph_data ?? [];
        $isApt      = isset($graphData['apt_meta']);
        $nodes      = collect($graphData['nodes'] ?? []);

        $skillNodes = $nodes->filter(fn($n) =>
            ($n['data']['type'] ?? '') === 'skill' ||
            ($n['type'] ?? '') === 'skillNode' ||
            isset($n['data']['skill_id']) ||
            isset($n['data']['skillId'])
        );

        $totalInput  = 0;
        $totalOutput = 0;
        $steps       = 0;
        $breakdown   = [];

        if ($isApt) {
            // APT nodes embed the prompt template directly
            foreach ($skillNodes as $node) {
                $tpl       = $node['data']['prompt_template'] ?? '';
                $inputEst  = (int) ceil(strlen($tpl) / 4);
                $outputEst = 2048;
                $totalInput  += $inputEst;
                $totalOutput += $outputEst;
                $steps++;
                $breakdown[] = [
                    'label'  => $node['data']['label'] ?? 'Stage',
                    'input'  => $inputEst,
                    'output' => $outputEst,
                    'steps'  => 1,
                ];
            }
        } else {
            foreach ($skillNodes as $node) {
                $skillId = $node['data']['skill_id'] ?? $node['data']['skillId'] ?? null;
                if (! $skillId) continue;

                $skill = Skill::with(['steps' => fn($q) => $q->where('is_active', true)])->find($skillId);
                if (! $skill) continue;

                $skillInput = $skillOutput = 0;
                $stepCount  = 0;

                foreach ($skill->steps as $step) {
                    // Base prompt length; subsequent steps also receive {{last_output}} from prior step
                    $chainBonus = $stepCount > 0 ? (int) ceil($skillOutput / $stepCount) : 0;
                    $inputEst   = (int) ceil(strlen($step->prompt_template) / 4) + $chainBonus;
                    $outputEst  = min($step->max_tokens ?? 2048, 4096);

                    $skillInput  += $inputEst;
                    $skillOutput += $outputEst;
                    $stepCount++;
                }

                $totalInput  += $skillInput;
                $totalOutput += $skillOutput;
                $steps       += $stepCount;

                $breakdown[] = [
                    'label'  => $skill->name,
                    'input'  => $skillInput,
                    'output' => $skillOutput,
                    'steps'  => $stepCount,
                ];
            }
        }

        // API health check — make a 1-token call to detect credit issues early
        $apiStatus = $this->checkApi(app(AiProviderService::class));

        return response()->json([
            'skills_count'   => $skillNodes->count(),
            'steps_count'    => $steps,
            'input_tokens'   => $totalInput,
            'output_tokens'  => $totalOutput,
            'total_tokens'   => $totalInput + $totalOutput,
            'api'            => $apiStatus,
            'breakdown'      => $breakdown,
        ]);
    }

    private function checkApi(AiProviderService $ai): array
    {
        $provider = $ai->getActiveProvider();

        if (! $provider) {
            return ['ok' => false, 'status' => 'no_provider', 'message' => 'No active AI provider configured'];
        }

        try {
            $ai->complete('ping', $provider, ['max_tokens' => 1, 'temperature' => 0]);
            return ['ok' => true, 'status' => 'ok', 'provider' => $provider->provider, 'model' => $provider->default_model];
        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());
            $isCredits = str_contains($msg, 'credit')
                || str_contains($msg, 'quota')
                || str_contains($msg, 'billing')
                || str_contains($msg, 'insufficient');
            if ($isCredits) {
                return ['ok' => false, 'status' => 'no_credits', 'provider' => $provider->provider, 'message' => 'Insufficient API credits'];
            }
            return ['ok' => false, 'status' => 'error', 'provider' => $provider->provider, 'message' => 'API check failed'];
        }
    }

    public function execute(Request $request, Workflow $workflow): JsonResponse
    {
        $request->validate([
            'input_data'  => 'nullable|array',
            'run_name'    => 'required|string|max:255',
            'target_id'   => 'nullable|exists:targets,id',
            'targets'     => 'nullable|array|min:1|max:100',
            'targets.*'   => 'required|string|max:500',
        ]);

        // ── Multi-target mode ─────────────────────────────────────────────────
        if ($request->filled('targets')) {
            $rawTargets  = $request->targets;
            $targets     = array_values(array_unique(array_filter(array_map('trim', $rawTargets))));
            $baseData    = $request->input_data ?? [];
            $targetType  = $baseData['target_type'] ?? 'host';

            $execution = Execution::create([
                'user_id'     => auth()->id(),
                'workflow_id' => $workflow->id,
                'type'        => 'workflow',
                'run_name'    => $request->run_name,
                'status'      => 'running',
                'started_at'  => now(),
                'input_data'  => array_merge($baseData, [
                    'is_multi_target' => true,
                    'targets_total'   => count($targets),
                ]),
            ]);

            foreach ($targets as $targetValue) {
                $execTarget = ExecutionTarget::create([
                    'execution_id' => $execution->id,
                    'target_type'  => $targetType,
                    'target_value' => $targetValue,
                    'status'       => 'pending',
                ]);

                RunWorkflowTargetJob::dispatch(
                    $execution->id,
                    $execTarget->id,
                    $workflow->id,
                    $targetValue,
                    $targetType,
                    $baseData,
                );
            }

            return response()->json([
                'message'      => 'Multi-target workflow execution started',
                'execution_id' => $execution->id,
                'targets'      => count($targets),
            ], 202);
        }

        // ── Single-target mode (existing behaviour) ───────────────────────────
        $execution = Execution::create([
            'user_id'     => auth()->id(),
            'workflow_id' => $workflow->id,
            'target_id'   => $request->target_id,
            'type'        => 'workflow',
            'run_name'    => $request->run_name,
            'status'      => 'pending',
            'input_data'  => $request->input_data ?? [],
        ]);

        ExecuteWorkflowJob::dispatch($execution);

        return response()->json(['message' => 'Workflow execution started', 'execution_id' => $execution->id], 202);
    }

    public function targets(Execution $execution): JsonResponse
    {
        $targets = $execution->executionTargets()
            ->with('childExecution:id,status,started_at,completed_at,error_message')
            ->get();

        $total      = $targets->count();
        $completed  = $targets->where('status', 'completed')->count();
        $failed     = $targets->where('status', 'failed')->count();
        $processing = $targets->where('status', 'processing')->count();
        $pending    = $targets->where('status', 'pending')->count();

        return response()->json([
            'data'  => $targets,
            'stats' => compact('total', 'completed', 'failed', 'processing', 'pending'),
        ]);
    }
}
