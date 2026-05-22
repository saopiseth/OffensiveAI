<?php

namespace App\Console\Commands;

use App\Jobs\ExecuteWorkflowJob;
use App\Models\Execution;
use App\Models\ScheduledWorkflow;
use Illuminate\Console\Command;

class RunScheduledWorkflowsCommand extends Command
{
    protected $signature   = 'workflow:run-scheduled {--dry-run : Preview due schedules without executing}';
    protected $description = 'Execute workflows whose scheduled run time has arrived';

    public function handle(): int
    {
        $due = ScheduledWorkflow::with(['workflow', 'target'])
            ->where('is_active', true)
            ->where('next_run_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->line('No scheduled workflows due.');
            return self::SUCCESS;
        }

        $this->info("Found {$due->count()} due schedule(s).");

        if ($this->option('dry-run')) {
            $this->table(
                ['Name', 'Workflow', 'Target', 'Was Due'],
                $due->map(fn($s) => [
                    $s->name,
                    $s->workflow->name ?? '—',
                    $s->target?->value ?? '—',
                    $s->next_run_at?->diffForHumans(),
                ])->toArray()
            );
            return self::SUCCESS;
        }

        foreach ($due as $schedule) {
            try {
                $execution = Execution::create([
                    'user_id'               => $schedule->created_by,
                    'workflow_id'           => $schedule->workflow_id,
                    'target_id'             => $schedule->target_id,
                    'scheduled_workflow_id' => $schedule->id,
                    'type'                  => 'workflow',
                    'status'                => 'pending',
                    'input_data'            => $schedule->input_data ?? [],
                ]);

                ExecuteWorkflowJob::dispatch($execution);

                $schedule->update([
                    'last_run_at' => now(),
                    'last_status' => 'pending',
                    'run_count'   => $schedule->run_count + 1,
                    'next_run_at' => $schedule->computeNextRun(),
                ]);

                $this->line("  ✓ {$schedule->name} → execution {$execution->id}");
            } catch (\Throwable $e) {
                $schedule->update(['last_status' => 'failed']);
                $this->error("  ✗ {$schedule->name}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
