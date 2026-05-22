<?php

namespace App\Console\Commands;

use App\Models\Skill;
use App\Models\Workflow;
use App\Models\User;
use App\Services\SkillWorkflowBuilder;
use Illuminate\Console\Command;

/**
 * php artisan skills:generate-workflows
 * php artisan skills:generate-workflows --category=web-application-security
 * php artisan skills:generate-workflows --force      (re-generate existing)
 */
class GenerateSkillWorkflowsCommand extends Command
{
    protected $signature = 'skills:generate-workflows
        {--category=     : Only process skills in this category}
        {--force         : Re-generate workflows that already exist}
        {--chunk=100     : DB chunk size for memory efficiency}';

    protected $description = 'Auto-generate ReactFlow workflows for every skill in the library';

    public function handle(SkillWorkflowBuilder $builder): int
    {
        $category = $this->option('category');
        $force    = $this->option('force');
        $chunk    = (int) $this->option('chunk');

        $admin = User::role('admin')->first() ?? User::first();

        // Build set of skill names that already have a workflow
        $existing = Workflow::pluck('name')
            ->map(fn($n) => str_replace(' Workflow', '', $n))
            ->flip();

        $query = Skill::with('steps')
            ->when($category, fn($q) => $q->where('category', $category))
            ->orderBy('category')
            ->orderBy('name');

        $total   = $query->count();
        $created = 0;
        $skipped = 0;
        $failed  = 0;

        $this->info("Processing {$total} skill(s)" . ($category ? " in [{$category}]" : '') . '…');
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->start();

        $query->chunk($chunk, function ($skills) use (
            $builder, $admin, $existing, $force, $bar,
            &$created, &$skipped, &$failed
        ) {
            foreach ($skills as $skill) {
                $bar->setMessage($skill->name);

                if (! $force && isset($existing[$skill->name])) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                try {
                    $builder->build($skill, $admin->id);
                    $created++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("  FAILED: {$skill->name} — {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Workflows created / updated', $created],
                ['Already had workflow (skipped)', $skipped],
                ['Failed', $failed],
            ]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
