<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\SkillStep;

class SkillConverterService
{
    /**
     * Return all non-Claude skills that can be converted, grouped by category.
     * Flags skills that already have a Claude conversion (converted_from link or matching name).
     */
    public function listConvertible(): array
    {
        // Skills that have at least one non-Claude step
        $sourceSkills = Skill::with('steps')
            ->whereHas('steps', fn($q) => $q->where('ai_provider', '!=', 'claude'))
            ->latest()
            ->get();

        // Names of skills that were already converted from something
        $alreadyConvertedNames = Skill::whereNotNull('converted_from')
            ->pluck('name')
            ->map(fn($n) => strtolower($n))
            ->all();

        $grouped = [];

        foreach ($sourceSkills as $skill) {
            $category = $skill->category ?? 'uncategorized';

            $providers = $skill->steps
                ->pluck('ai_provider')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $claudeName     = $skill->name . ' (Claude)';
            $alreadyConverted = in_array(strtolower($claudeName), $alreadyConvertedNames)
                || Skill::where('converted_from', $skill->id)->exists();

            $grouped[$category][] = [
                'id'               => $skill->id,
                'name'             => $skill->name,
                'description'      => $skill->description,
                'category'         => $category,
                'version'          => $skill->version,
                'steps_count'      => $skill->steps->count(),
                'is_active'        => $skill->is_active,
                'providers'        => $providers,
                'already_converted'=> $alreadyConverted,
            ];
        }

        ksort($grouped);
        return $grouped;
    }

    /**
     * Preview the converted prompt for each selected skill without persisting.
     */
    public function preview(array $skillIds): array
    {
        $skills  = Skill::with('steps')->whereIn('id', $skillIds)->get();
        $results = [];

        foreach ($skills as $skill) {
            $results[] = [
                'source_id'      => $skill->id,
                'source_name'    => $skill->name,
                'converted_name' => $skill->name . ' (Claude)',
                'steps'          => $skill->steps->map(fn($s) => [
                    'name'             => $s->name,
                    'original_provider'=> $s->ai_provider,
                    'merged_prompt'    => $this->mergePrompt($s),
                    'input_schema'     => $s->input_schema,
                    'output_schema'    => $s->output_schema,
                ])->values()->all(),
            ];
        }

        return $results;
    }

    /**
     * Convert the given skills to Claude, creating new Skill + SkillStep records.
     * Returns per-skill result: 'created', 'skipped', or 'failed'.
     */
    public function convert(array $skillIds, ?string $createdBy = null): array
    {
        $skills  = Skill::with('steps')->whereIn('id', $skillIds)->get();
        $results = [];

        foreach ($skills as $skill) {
            try {
                $claudeName = $skill->name . ' (Claude)';

                // Skip if already converted
                if (
                    Skill::where('name', $claudeName)->exists() ||
                    Skill::where('converted_from', $skill->id)->exists()
                ) {
                    $results[] = [
                        'source_id'    => $skill->id,
                        'source_name'  => $skill->name,
                        'status'       => 'skipped',
                        'reason'       => 'Claude version already exists',
                    ];
                    continue;
                }

                $claudeSkill = Skill::create([
                    'name'           => $claudeName,
                    'description'    => $skill->description,
                    'category'       => $skill->category,
                    'version'        => $skill->version,
                    'is_active'      => $skill->is_active,
                    'created_by'     => $createdBy,
                    'converted_from' => $skill->id,
                    'tags'           => $skill->tags,
                ]);

                $order = 1;
                foreach ($skill->steps()->orderBy('execution_order')->get() as $step) {
                    $mergedPrompt = $this->mergePrompt($step);

                    // Validation: merged prompt must not be empty
                    if (trim($mergedPrompt) === '') {
                        throw new \RuntimeException("Step \"{$step->name}\" produced an empty merged prompt.");
                    }

                    SkillStep::create([
                        'skill_id'        => $claudeSkill->id,
                        'name'            => $step->name,
                        'description'     => $step->description,
                        'prompt_template' => $mergedPrompt,
                        'system_prompt'   => null,
                        'input_schema'    => $step->input_schema,
                        'output_schema'   => $step->output_schema,
                        'execution_order' => $order++,
                        'ai_provider'     => 'claude',
                        'model'           => null,
                        'temperature'     => $step->temperature,
                        'max_tokens'      => $step->max_tokens,
                        'is_active'       => $step->is_active,
                    ]);
                }

                $results[] = [
                    'source_id'      => $skill->id,
                    'source_name'    => $skill->name,
                    'converted_id'   => $claudeSkill->id,
                    'converted_name' => $claudeSkill->name,
                    'steps_copied'   => $order - 1,
                    'status'         => 'created',
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
     * Merge a step's system_prompt + prompt_template into a single Claude-compatible prompt.
     *
     * Claude uses a single combined prompt; OpenAI uses system + user separately.
     * This method merges them preserving all {{placeholders}}.
     */
    public function mergePrompt(SkillStep $step): string
    {
        $systemPrompt    = trim($step->system_prompt    ?? '');
        $promptTemplate  = trim($step->prompt_template  ?? '');
        $outputSchema    = $step->output_schema;

        $parts = [];

        if ($systemPrompt !== '') {
            $parts[] = $systemPrompt;
        }

        if ($promptTemplate !== '') {
            $parts[] = $promptTemplate;
        }

        // Append output schema hint when defined and not already referenced in prompts
        if (!empty($outputSchema)) {
            $schemaJson = json_encode($outputSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $combined   = implode(' ', $parts);

            // Only append if schema not already mentioned in either prompt
            if (!str_contains(strtolower($combined), 'output_schema') &&
                !str_contains(strtolower($combined), 'json format')) {
                $parts[] = "Requirements:\n- Follow structured output\n- Return JSON format only\n\nExpected Output:\n```json\n{$schemaJson}\n```";
            }
        }

        return implode("\n\n", $parts);
    }
}
