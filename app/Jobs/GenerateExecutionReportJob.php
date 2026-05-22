<?php

namespace App\Jobs;

use App\Models\Execution;
use App\Services\ExecutionReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateExecutionReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 1;
    public int $timeout = 360;  // 6 min — covers Opus on large payloads

    public function __construct(public string $executionId) {}

    public function handle(ExecutionReportService $reportService): void
    {
        $execution = Execution::find($this->executionId);
        if (! $execution) {
            return;
        }

        // ExecutionReportService::generate() handles status updates internally
        // and re-throws on failure so the job is marked failed in the queue log.
        $reportService->generate($execution);
    }

    public function failed(\Throwable $exception): void
    {
        Execution::where('id', $this->executionId)->update([
            'report_status' => 'failed',
            'report_error'  => mb_substr($exception->getMessage(), 0, 1000),
        ]);
    }
}
