<?php

namespace App\Console\Commands;

use App\Models\Skill;
use App\Models\SkillStep;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportCybersecuritySkillsCommand extends Command
{
    protected $signature = 'skills:import-cybersecurity
                            {--repo= : Path to cloned Anthropic-Cybersecurity-Skills repo}
                            {--category= : Only import skills from this subdomain/category}
                            {--fresh : Delete and re-import skills that already exist}
                            {--dry-run : Preview what would be imported without writing}';

    protected $description = 'Import all 754 cybersecurity skills from a local clone of Anthropic-Cybersecurity-Skills';

    // Normalise duplicate subdomain spellings → canonical name
    private const SUBDOMAIN_MAP = [
        'identity-security'              => 'identity-access-management',
        'identity-and-access-management' => 'identity-access-management',
        'red-team'                       => 'red-teaming',
        'purple-team'                    => 'red-teaming',
        'offensive-security'             => 'penetration-testing',
        'zero-trust'                     => 'zero-trust-architecture',
        'ot-security'                    => 'ot-ics-security',
        'governance-risk-compliance'     => 'compliance-governance',
        'privacy-compliance'             => 'compliance-governance',
        'firmware-analysis'              => 'digital-forensics',
        'social-engineering-defense'     => 'phishing-defense',
        'threat-detection'               => 'soc-operations',
        'application-security'           => 'web-application-security',
    ];

    public function handle(): int
    {
        $repoPath = rtrim(
            $this->option('repo') ?? base_path('../CybersecuritySkills'),
            '/\\'
        );

        $skillsDir = $repoPath . DIRECTORY_SEPARATOR . 'skills';

        if (! is_dir($skillsDir)) {
            $this->error("Skills directory not found: {$skillsDir}");
            $this->line('Clone the repo first:');
            $this->line('  git clone --depth=1 https://github.com/mukul975/Anthropic-Cybersecurity-Skills.git');
            $this->line('Then pass --repo=<path>');
            return self::FAILURE;
        }

        $admin   = User::where('email', 'admin@redto.app')->first();
        $adminId = $admin?->id;

        $categoryFilter = $this->option('category');
        $isDryRun       = $this->option('dry-run');
        $isFresh        = $this->option('fresh');

        // Collect all skill directories
        $dirs = array_filter(
            glob($skillsDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR),
            fn($d) => file_exists($d . DIRECTORY_SEPARATOR . 'SKILL.md')
        );

        if ($categoryFilter) {
            $dirs = array_filter($dirs, function ($d) use ($categoryFilter, $skillsDir) {
                $md  = file_get_contents($d . DIRECTORY_SEPARATOR . 'SKILL.md');
                $sub = $this->normalisedSubdomain($this->yamlVal($md, 'subdomain'));
                return $sub === $categoryFilter;
            });
        }

        $total = count($dirs);
        $this->info("Found {$total} SKILL.md files" . ($categoryFilter ? " in category [{$categoryFilter}]" : '') . '.');

        if ($isDryRun) {
            $this->warn('DRY RUN — no database writes.');
        }

        $bar       = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->start();

        $imported = $skipped = $failed = 0;
        $errors   = [];

        foreach ($dirs as $dir) {
            $skillName = basename($dir);
            $bar->setMessage($skillName);
            $bar->advance();

            $mdPath = $dir . DIRECTORY_SEPARATOR . 'SKILL.md';

            try {
                $parsed   = $this->parseSkillMd(file_get_contents($mdPath));
                $name     = $parsed['name'] ?: $this->slugToTitle($skillName);
                $category = $this->normalisedSubdomain($parsed['subdomain'] ?: 'cybersecurity');

                if ($isFresh) {
                    Skill::where('name', $name)->forceDelete();
                } elseif (Skill::where('name', $name)->exists()) {
                    $skipped++;
                    continue;
                }

                if (! $isDryRun) {
                    $skill = Skill::create([
                        'created_by'  => $adminId,
                        'name'        => $name,
                        'description' => $parsed['description'],
                        'version'     => $parsed['version'] ?: '1.0.0',
                        'category'    => $category,
                        'is_active'   => true,
                        'tags'        => $parsed['tags'],
                    ]);

                    $this->createSteps($skill->id, $parsed);
                }

                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "{$skillName}: " . $e->getMessage();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Status', 'Count'],
            [
                ['<fg=green>Imported</>',  $imported],
                ['<fg=yellow>Skipped</>',  $skipped],
                ['<fg=red>Failed</>',      $failed],
            ]
        );

        if ($errors) {
            $this->warn('First 10 errors:');
            foreach (array_slice($errors, 0, 10) as $e) {
                $this->line("  · {$e}");
            }
        }

        // Final DB summary
        if (! $isDryRun) {
            $this->info(sprintf(
                '✅ Database totals → Skills: %d  Steps: %d',
                Skill::count(),
                SkillStep::count()
            ));
        }

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Step creation
    // ──────────────────────────────────────────────────────────────────────────

    private function createSteps(string $skillId, array $parsed): void
    {
        $steps       = $parsed['steps'];
        $outputFmt   = $parsed['output_format'];
        $totalSteps  = count($steps);

        if (empty($steps)) {
            // Fallback: one generic step from full body
            SkillStep::create($this->stepRecord($skillId, 1, [
                'name'    => 'Execute',
                'content' => Str::limit($parsed['full_body'], 6000),
            ], $parsed, 0, 1, $outputFmt));
            return;
        }

        foreach ($steps as $i => $step) {
            SkillStep::create(
                $this->stepRecord($skillId, $i + 1, $step, $parsed, $i, $totalSteps, $outputFmt)
            );
        }
    }

    private function stepRecord(
        string $skillId,
        int    $order,
        array  $step,
        array  $parsed,
        int    $idx,
        int    $total,
        string $outputFmt
    ): array {
        return [
            'skill_id'        => $skillId,
            'name'            => Str::limit($step['name'] ?? "Step {$order}", 250),
            'description'     => Str::limit($step['content'] ?? '', 500),
            'prompt_template' => $this->buildPrompt($parsed['name'], $step, $parsed, $idx, $total, $outputFmt),
            'execution_order' => $order,
            'ai_provider'     => 'claude',
            'model'           => 'claude-opus-4-7',
            'temperature'     => 0.3,
            'max_tokens'      => 4096,
            'is_active'       => true,
        ];
    }

    private function buildPrompt(
        string $skillName,
        array  $step,
        array  $parsed,
        int    $idx,
        int    $total,
        string $outputFmt
    ): string {
        $isFirst = $idx === 0;
        $isLast  = $idx === $total - 1;

        $lines   = [];
        $lines[] = "You are a cybersecurity expert executing the **{$skillName}** procedure.";

        // Context block (first step only)
        if ($isFirst && $parsed['when_to_use']) {
            $lines[] = '';
            $lines[] = '## When to Use';
            $lines[] = Str::limit($parsed['when_to_use'], 600);
        }

        if ($parsed['prerequisites'] && $isFirst) {
            $lines[] = '';
            $lines[] = '## Prerequisites';
            $lines[] = Str::limit($parsed['prerequisites'], 400);
        }

        // Step instructions
        $lines[] = '';
        $lines[] = '## Task: ' . ($step['name'] ?? "Step " . ($idx + 1));

        if (! empty($step['content'])) {
            $lines[] = '';
            $lines[] = $step['content'];
        }

        // Input variables
        $lines[] = '';
        if ($isFirst) {
            $lines[] = '## Input';
            $lines[] = 'Target / System: {{target}}';
            $lines[] = 'Data to analyse: {{data}}';
            $lines[] = 'Additional context: {{context}}';
        } else {
            $lines[] = '## Previous Step Output';
            $lines[] = '{{previous_output}}';
            $lines[] = '';
            $lines[] = 'Additional context: {{context}}';
        }

        // Output format (last step)
        if ($isLast && $outputFmt) {
            $lines[] = '';
            $lines[] = '## Expected Output Format';
            $lines[] = Str::limit($outputFmt, 1500);
        }

        return implode("\n", $lines);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SKILL.md parser
    // ──────────────────────────────────────────────────────────────────────────

    private function parseSkillMd(string $raw): array
    {
        $result = [
            'name'          => '',
            'description'   => '',
            'subdomain'     => '',
            'tags'          => [],
            'version'       => '1.0.0',
            'when_to_use'   => '',
            'prerequisites' => '',
            'output_format' => '',
            'steps'         => [],
            'full_body'     => '',
        ];

        // ── YAML frontmatter ──────────────────────────────────────────────────
        if (preg_match('/\A---\s*\n(.*?)\n---\s*\n/s', $raw, $fm)) {
            $yaml = $fm[1];

            $result['name']        = $this->yamlVal($yaml, 'name');
            $result['description'] = $this->cleanDescription($this->yamlVal($yaml, 'description'));
            $result['subdomain']   = $this->yamlVal($yaml, 'subdomain');
            $result['version']     = $this->yamlVal($yaml, 'version') ?: '1.0.0';

            // Tags array
            if (preg_match('/^tags:\s*\n((?:[ \t]*-[^\n]*\n)+)/m', $yaml, $tm)) {
                preg_match_all('/^\s*-\s+(.+)$/m', $tm[1], $tagM);
                $result['tags'] = array_values(array_filter(array_map('trim', $tagM[1])));
            }

            $body = substr($raw, strlen($fm[0]));
        } else {
            $body = $raw;
        }

        $result['full_body'] = $body;

        // ── Section extraction ────────────────────────────────────────────────
        $sections = $this->parseSections($body);

        $result['when_to_use']   = $sections['when to use']   ?? $sections['overview']    ?? '';
        $result['prerequisites'] = $sections['prerequisites'] ?? '';
        $result['output_format'] = $sections['output format'] ?? $sections['expected output']
                                                              ?? $sections['output']
                                                              ?? $sections['example output']
                                                              ?? '';

        // ── Step extraction (3 patterns) ──────────────────────────────────────

        // Pattern 1: ## Workflow with ### Step N: subsections
        if (isset($sections['workflow'])) {
            $result['steps'] = $this->extractSubheadingSteps($sections['workflow']);
        }

        // Pattern 2: ## Steps with numbered bold list  →  1. **Title**: body
        if (empty($result['steps']) && isset($sections['steps'])) {
            $result['steps'] = $this->extractNumberedBoldSteps($sections['steps']);
        }

        // Pattern 3: ## Instructions (flat numbered list)
        if (empty($result['steps']) && isset($sections['instructions'])) {
            $result['steps'] = $this->extractNumberedBoldSteps($sections['instructions']);
        }

        // Pattern 4: Custom H2 sections — each non-meta section becomes its own step
        if (empty($result['steps'])) {
            $ignore = [
                'when to use', 'prerequisites', 'output format', 'expected output',
                'output', 'example output', 'key concepts', 'tools & systems',
                'common scenarios', 'references', 'overview', 'instructions',
            ];

            foreach ($sections as $key => $val) {
                if (! in_array($key, $ignore, true) && strlen(trim($val)) > 20) {
                    $result['steps'][] = [
                        'name'    => Str::title($key),
                        'content' => trim($val),
                    ];
                }
            }

            // Still nothing useful — fall back to full body as single step
            if (empty($result['steps']) && strlen(trim($result['full_body'])) > 50) {
                $result['steps'][] = [
                    'name'    => 'Execute: ' . ($result['name'] ?: 'Skill'),
                    'content' => Str::limit(trim($result['full_body']), 6000),
                ];
            }
        }

        return $result;
    }

    /** Split markdown into ## sections → ['section title' => 'content'] */
    private function parseSections(string $body): array
    {
        $sections = [];
        $parts    = preg_split('/^## /m', $body);

        foreach (array_slice($parts, 1) as $part) {
            $nl      = strpos($part, "\n");
            $header  = $nl !== false ? substr($part, 0, $nl) : $part;
            $content = $nl !== false ? substr($part, $nl + 1) : '';
            $sections[strtolower(trim($header))] = trim($content);
        }

        return $sections;
    }

    /** Extract ### Step N: Title subsections from a workflow block */
    private function extractSubheadingSteps(string $text): array
    {
        $steps = [];

        // Match ### <anything> … up to next ### or end
        if (preg_match_all('/^### ([^\n]+)\n(.*?)(?=\n### |\z)/ms', $text, $m)) {
            foreach ($m[1] as $i => $title) {
                $content = trim($m[2][$i]);
                if (! $content) {
                    continue;
                }
                $steps[] = [
                    'name'    => trim(preg_replace('/^Step\s+\d+:?\s*/i', '', $title)),
                    'content' => $content,
                ];
            }
        }

        return $steps;
    }

    /** Extract 1. **Title**: description numbered list */
    private function extractNumberedBoldSteps(string $text): array
    {
        $steps = [];

        // Match:  1. **Title**: body text  (may span multiple lines until next number)
        if (preg_match_all('/^\d+\.\s+\*\*(.+?)\*\*[:\s—\-]*(.*?)(?=^\d+\.|\z)/ms', $text, $m)) {
            foreach ($m[1] as $i => $title) {
                $content = trim($m[2][$i]);
                $steps[] = [
                    'name'    => trim($title),
                    'content' => $content ?: trim($title),
                ];
            }
        }

        // Fallback: plain numbered list  →  1. Description text
        if (empty($steps) && preg_match_all('/^\d+\.\s+(?!\*\*)(.+?)(?=^\d+\.|\z)/ms', $text, $m)) {
            foreach ($m[1] as $i => $line) {
                $content = trim($line);
                if (strlen($content) < 5) {
                    continue;
                }
                $steps[] = [
                    'name'    => Str::limit($content, 80),
                    'content' => $content,
                ];
            }
        }

        return $steps;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function yamlVal(string $yaml, string $key): string
    {
        // Handle multi-line folded (description with indented continuation)
        if (preg_match('/^' . preg_quote($key, '/') . ':\s*(.+?)(?=\n\S|\z)/ms', $yaml, $m)) {
            // Collapse folded continuation lines (YAML block scalar)
            $val = preg_replace('/\n\s+/', ' ', trim($m[1]));
            return trim($val, "'\"");
        }
        return '';
    }

    private function cleanDescription(string $desc): string
    {
        // Remove surrounding quotes if any
        return trim($desc, "\"'\n\r ");
    }

    private function normalisedSubdomain(string $sub): string
    {
        return self::SUBDOMAIN_MAP[$sub] ?? $sub;
    }

    private function slugToTitle(string $slug): string
    {
        return Str::of($slug)->replace('-', ' ')->title()->toString();
    }
}
