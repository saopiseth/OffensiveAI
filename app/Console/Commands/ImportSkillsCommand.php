<?php

namespace App\Console\Commands;

use App\Services\SkillImporterService;
use App\Services\SkillParserService;
use App\Services\SkillWorkflowBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * php artisan skills:import storage/skills-repo
 * php artisan skills:import path/to/skill.md
 * php artisan skills:import path/to/skill.json
 * php artisan skills:import storage/skills-repo --workflow
 * php artisan skills:import storage/skills-repo --dry-run
 */
class ImportSkillsCommand extends Command
{
    protected $signature = 'skills:import
        {path             : File or directory to import from}
        {--workflow       : Auto-generate a workflow for each imported skill}
        {--dry-run        : Parse and validate without writing to the database}
        {--format=auto    : Force input format: auto | markdown | json}';

    protected $description = 'Import skills from SKILL.md / JSON files into the platform';

    public function handle(
        SkillParserService   $parser,
        SkillImporterService $importer,
        SkillWorkflowBuilder $builder
    ): int {
        $path    = $this->argument('path');
        $dryRun  = $this->option('dry-run');
        $mkWf    = $this->option('workflow');
        $format  = $this->option('format');

        if ($dryRun) {
            $this->warn('DRY-RUN mode — nothing will be written to the database.');
        }

        $files = $this->resolveFiles($path, $format);

        if (empty($files)) {
            $this->error("No importable files found at: {$path}");
            return self::FAILURE;
        }

        $this->info("Found " . count($files) . " file(s) to process.");
        $this->newLine();

        $imported = 0;
        $updated  = 0;
        $failed   = 0;
        $workflows = 0;

        foreach ($files as $file) {
            $filename = basename($file);

            try {
                $content = File::get($file);
                $parsed  = $parser->parse($content);

                $name = $parsed['meta']['name'] ?? '(unknown)';

                if ($dryRun) {
                    $this->line("  <fg=cyan>PARSE OK</> {$filename}");
                    $this->line("         name     : {$name}");
                    $this->line("         category : " . ($parsed['meta']['category'] ?? '—'));
                    $this->line("         steps    : " . count($parsed['steps']));
                    $this->line("         tags     : " . implode(', ', $parsed['meta']['tags'] ?? []));
                    $this->newLine();
                    continue;
                }

                $result = $importer->import($parsed);

                if ($result['created']) {
                    $imported++;
                    $this->line("  <fg=green>CREATED</>  {$name}  [{$result['skill']->category}]  {$result['steps_count']} step(s)");
                } else {
                    $updated++;
                    $this->line("  <fg=yellow>UPDATED</>  {$name}  [{$result['skill']->category}]  {$result['steps_count']} step(s)");
                }

                if ($mkWf) {
                    $wf = $builder->build($result['skill']);
                    $workflows++;
                    $this->line("             <fg=blue>→ Workflow:</> {$wf->name}");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->line("  <fg=red>FAILED</>   {$filename}: {$e->getMessage()}");
            }
        }

        $this->newLine();

        if (! $dryRun) {
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Created',   $imported],
                    ['Updated',   $updated],
                    ['Workflows', $workflows],
                    ['Failed',    $failed],
                ]
            );
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── File resolution ───────────────────────────────────────────────────────

    private function resolveFiles(string $path, string $format): array
    {
        if (! file_exists($path)) {
            return [];
        }

        if (is_file($path)) {
            return [$path];
        }

        $allFiles = File::allFiles($path);
        $result   = [];

        foreach ($allFiles as $file) {
            $ext      = strtolower($file->getExtension());
            $basename = $file->getFilename();

            $isMarkdown = $ext === 'md';
            $isJson     = $ext === 'json';
            $isSkillMd  = $basename === 'SKILL.md';

            $include = match ($format) {
                'markdown' => $isMarkdown,
                'json'     => $isJson,
                default    => $isSkillMd || $isJson,
            };

            if ($include) {
                $result[] = $file->getRealPath();
            }
        }

        return $result;
    }
}
