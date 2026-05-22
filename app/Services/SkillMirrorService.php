<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\SkillStep;

class SkillMirrorService
{
    // Categories that contain skills suitable for mirroring
    private const MIRRORABLE_CATEGORIES = [
        'network-penetration',
        'web-application-penetration',
        'red-teaming',
        'penetration-testing',
        'threat-intelligence',
        'malware-analysis',
        'digital-forensics',
        'phishing-defense',
        'soc-operations',
        'endpoint-security',
        'identity-access-management',
        'apt-simulation',
    ];

    /**
     * Return all Claude skills that can be mirrored, grouped by category.
     * Skills that already have an OpenAI mirror (same name + "_openai" suffix or matching name) are flagged.
     */
    public function listMirrorable(): array
    {
        $claudeSkills = Skill::with('steps')
            ->whereHas('steps', fn($q) => $q->where('ai_provider', 'claude'))
            ->orWhereDoesntHave('steps')
            ->latest()
            ->get();

        $openaiSkillNames = Skill::whereHas('steps', fn($q) => $q->where('ai_provider', 'openai'))
            ->pluck('name')
            ->map(fn($n) => strtolower($n))
            ->all();

        $grouped = [];

        foreach ($claudeSkills as $skill) {
            $category = $skill->category ?? 'uncategorized';
            $alreadyMirrored = in_array(strtolower($skill->name . ' (OpenAI)'), $openaiSkillNames)
                || in_array(strtolower($skill->name), $openaiSkillNames);

            $grouped[$category][] = [
                'id'              => $skill->id,
                'name'            => $skill->name,
                'description'     => $skill->description,
                'category'        => $category,
                'version'         => $skill->version,
                'steps_count'     => $skill->steps->count(),
                'is_active'       => $skill->is_active,
                'already_mirrored'=> $alreadyMirrored,
            ];
        }

        ksort($grouped);
        return $grouped;
    }

    /**
     * Preview what a mirror would look like without persisting.
     */
    public function preview(array $skillIds, string $targetProvider, string $targetModel): array
    {
        $skills = Skill::with('steps')->whereIn('id', $skillIds)->get();
        $results = [];

        foreach ($skills as $skill) {
            $results[] = [
                'source_id'   => $skill->id,
                'source_name' => $skill->name,
                'mirror_name' => $skill->name . ' (OpenAI)',
                'steps'       => $skill->steps->map(fn($s) => [
                    'name'          => $s->name,
                    'provider'      => $targetProvider,
                    'model'         => $targetModel,
                    'system_prompt' => $this->generateSystemPrompt($s),
                ])->all(),
            ];
        }

        return $results;
    }

    /**
     * Mirror the given skills to OpenAI, creating new Skill + SkillStep records.
     * Returns per-skill result: 'created', 'skipped', or 'failed'.
     */
    public function mirror(array $skillIds, string $targetProvider, string $targetModel, ?string $createdBy = null): array
    {
        $skills  = Skill::with('steps')->whereIn('id', $skillIds)->get();
        // Ensure skill name is accessible on each step for system prompt generation
        $skills->each(fn($s) => $s->steps->each->setRelation('skill', $s));
        $results = [];

        foreach ($skills as $skill) {
            try {
                $mirrorName = $skill->name . ' (OpenAI)';

                // Skip if a skill with this mirror name already exists
                if (Skill::where('name', $mirrorName)->exists()) {
                    $results[] = [
                        'source_id'   => $skill->id,
                        'source_name' => $skill->name,
                        'status'      => 'skipped',
                        'reason'      => 'Mirror already exists',
                    ];
                    continue;
                }

                $mirroredSkill = Skill::create([
                    'name'        => $mirrorName,
                    'description' => $skill->description,
                    'category'    => $skill->category,
                    'version'     => $skill->version,
                    'is_active'   => $skill->is_active,
                    'created_by'  => $createdBy,
                    'tags'        => $skill->tags,
                ]);

                $order = 1;
                foreach ($skill->steps()->orderBy('execution_order')->get() as $step) {
                    SkillStep::create([
                        'skill_id'        => $mirroredSkill->id,
                        'name'            => $step->name,
                        'description'     => $step->description,
                        'prompt_template' => $step->prompt_template,
                        'system_prompt'   => $this->generateSystemPrompt($step),
                        'input_schema'    => $step->input_schema,
                        'output_schema'   => $step->output_schema,
                        'execution_order' => $order++,
                        'ai_provider'     => $targetProvider,
                        'model'           => $targetModel,
                        'temperature'     => $step->temperature,
                        'max_tokens'      => $step->max_tokens,
                        'is_active'       => $step->is_active,
                    ]);
                }

                $results[] = [
                    'source_id'    => $skill->id,
                    'source_name'  => $skill->name,
                    'mirror_id'    => $mirroredSkill->id,
                    'mirror_name'  => $mirroredSkill->name,
                    'steps_copied' => $order - 1,
                    'status'       => 'created',
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'source_id'   => $skill->id,
                    'source_name' => $skill->name,
                    'status'      => 'failed',
                    'reason'      => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Generate a concise system prompt for an OpenAI step based on the Claude step context.
     * The system prompt sets the assistant's role and output expectations.
     */
    private function generateSystemPrompt(SkillStep $step): string
    {
        $skillName = $step->skill?->name ?? '';
        $stepName  = $step->name;

        // Extract output schema keys to hint the assistant
        $outputKeys = array_keys($step->output_schema ?? []);
        $outputHint = count($outputKeys)
            ? 'Return a JSON object with these keys: ' . implode(', ', $outputKeys) . '.'
            : 'Return structured JSON output.';

        $lines = [
            "You are an expert cybersecurity analyst specializing in penetration testing and threat assessment.",
        ];

        if ($skillName) {
            $lines[] = "You are performing the \"{$skillName}\" assessment.";
        }

        $lines[] = "Current task: {$stepName}.";
        $lines[] = "Be precise, technical, and actionable in your analysis.";
        $lines[] = $outputHint;

        return implode(' ', $lines);
    }
}
