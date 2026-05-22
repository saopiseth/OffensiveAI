<?php

namespace App\Jobs;

use App\Models\Execution;
use App\Models\ExecutionTarget;
use App\Services\ExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunWorkflowTargetJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 1;
    public int $timeout = 3600;

    public function __construct(
        public string $parentExecutionId,
        public string $executionTargetId,
        public string $workflowId,
        public string $targetValue,
        public string $targetType,
        public array  $baseInputData = [],
    ) {}

    public function handle(ExecutionService $executionService): void
    {
        $execTarget = ExecutionTarget::find($this->executionTargetId);
        if (! $execTarget) {
            return;
        }

        $parent = Execution::find($this->parentExecutionId);
        if (! $parent || $parent->status === 'cancelled') {
            return;
        }

        $execTarget->update(['status' => 'processing', 'started_at' => now()]);

        // Create a child execution scoped to this single target
        $childExecution = Execution::create([
            'user_id'             => $parent->user_id,
            'workflow_id'         => $this->workflowId,
            'parent_execution_id' => $this->parentExecutionId,
            'type'                => 'workflow',
            'run_name'            => $parent->run_name . ' — ' . $this->targetValue,
            'status'              => 'pending',
            'input_data'          => array_merge($this->baseInputData, [
                'target'      => $this->targetValue,
                'target_name' => $this->targetValue,
                'target_type' => $this->targetType,
            ]),
        ]);

        $execTarget->update(['child_execution_id' => $childExecution->id]);

        try {
            $executionService->executeWorkflow($childExecution);
            $childExecution = $childExecution->fresh();

            $execTarget->update([
                'status'       => $childExecution->status,
                'completed_at' => now(),
                'error_message' => $childExecution->error_message,
            ]);
        } catch (\Throwable $e) {
            $execTarget->update([
                'status'        => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 5000),
                'completed_at'  => now(),
            ]);
        }

        $this->maybeCompleteParent($parent);
    }

    private function maybeCompleteParent(Execution $parent): void
    {
        $targets = ExecutionTarget::where('execution_id', $parent->id)->get();

        $total   = $targets->count();
        $done    = $targets->whereIn('status', ['completed', 'failed'])->count();

        if ($done < $total) {
            return;
        }

        $allOk = $targets->where('status', 'failed')->count() === 0;

        $parent->update([
            'status'       => $allOk ? 'completed' : 'failed',
            'completed_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        if ($execTarget = ExecutionTarget::find($this->executionTargetId)) {
            $execTarget->update([
                'status'        => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 5000),
                'completed_at'  => now(),
            ]);
        }

        $parent = Execution::find($this->parentExecutionId);
        if ($parent) {
            $this->maybeCompleteParent($parent);
        }
    }
}
