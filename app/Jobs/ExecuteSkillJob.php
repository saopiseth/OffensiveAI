<?php

namespace App\Jobs;

use App\Models\Execution;
use App\Services\ExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteSkillJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public Execution $execution) {}

    public function handle(ExecutionService $executionService): void
    {
        $execution = Execution::find($this->execution->id);
        if (! $execution || $execution->status === 'cancelled') {
            return;
        }

        $executionService->executeSkill($execution);
    }

    public function failed(\Throwable $exception): void
    {
        Execution::where('id', $this->execution->id)->update([
            'status'        => 'failed',
            'error_message' => mb_substr($exception->getMessage(), 0, 5000),
            'completed_at'  => now(),
        ]);
    }
}
