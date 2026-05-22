<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\SkillFramework;
use App\Models\SkillStep;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Persists a parsed skill (output of SkillParserService) to the database.
 *
 * Handles:
 *  - Skill upsert (update-or-create by name)
 *  - Tags (stored as array on skills.tags)
 *  - Framework mappings (skill_frameworks table)
 *  - Skill steps (multi-step or single workflow fallback)
 *  - Duplicate prevention via name match + version check
 */
class SkillImporterService
{
    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * @param  array  $parsed   Output from SkillParserService::parse()
     * @param  int|string|null  $createdBy  User ID to attribute the skill to
     * @return array  ['skill' => Skill, 'created' => bool, 'steps_count' => int]
     */
    public function import(array $parsed, mixed $createdBy = null): array
    {
        $meta = $parsed['meta'];
        $name = $meta['name'] ?? null;

        if (! $name) {
            throw new \InvalidArgumentException("Skill name is required in YAML frontmatter or JSON.");
        }

        $createdBy ??= User::role('admin')->first()?->id ?? User::first()?->id;

        // ── Upsert skill ──────────────────────────────────────────────────────

        [$skill, $created] = $this->upsertSkill($meta, $parsed['description'], $createdBy);

        // ── Tags ──────────────────────────────────────────────────────────────

        if (! empty($meta['tags'])) {
            $existing = $skill->tags ?? [];
            $merged   = array_values(array_unique(array_merge($existing, (array) $meta['tags'])));
            $skill->update(['tags' => $merged]);
        }

        // ── Framework mappings ────────────────────────────────────────────────

        foreach ($meta['frameworks'] ?? [] as $fw) {
            SkillFramework::firstOrCreate(
                [
                    'skill_id'       => $skill->id,
                    'framework'      => $fw['name'] ?? $fw,
                    'reference_code' => $fw['id'] ?? $fw['code'] ?? null,
                ],
                ['reference_url' => $fw['url'] ?? null]
            );
        }

        // ── Skill steps ───────────────────────────────────────────────────────

        $stepsCount = $this->upsertSteps($skill, $parsed);

        return [
            'skill'       => $skill,
            'created'     => $created,
            'steps_count' => $stepsCount,
        ];
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function upsertSkill(array $meta, string $description, mixed $createdBy): array
    {
        $existing = Skill::where('name', $meta['name'])->first();
        $created  = false;

        $attributes = [
            'description' => $description ?: ($meta['description'] ?? null),
            'category'    => $this->resolveCategory($meta),
            'version'     => $meta['version'] ?? '1.0.0',
            'tags'        => (array) ($meta['tags'] ?? []),
            'is_active'   => true,
            'created_by'  => $createdBy,
        ];

        if ($existing) {
            $existing->update($attributes);
            $skill = $existing;
        } else {
            $skill   = Skill::create(array_merge(['name' => $meta['name']], $attributes));
            $created = true;
        }

        return [$skill, $created];
    }

    private function upsertSteps(Skill $skill, array $parsed): int
    {
        $steps = $parsed['steps'];

        // Fall back to single "Main Workflow" step if no ## Step N sections found
        if (empty($steps)) {
            $content = $parsed['workflow'] ?: $parsed['description'] ?: '';
            if ($content) {
                $steps = [['name' => 'Main Workflow', 'description' => null, 'prompt_template' => $content]];
            }
        }

        foreach ($steps as $order => $step) {
            SkillStep::updateOrCreate(
                ['skill_id' => $skill->id, 'execution_order' => $order + 1],
                [
                    'name'            => $step['name'],
                    'description'     => $step['description'] ?? null,
                    'prompt_template' => $step['prompt_template'] ?? '',
                    'model'           => 'claude-opus-4-7',
                    'temperature'     => 0.3,
                    'max_tokens'      => 4096,
                    'is_active'       => true,
                ]
            );
        }

        // Remove orphaned steps if re-importing a shorter version
        $skill->steps()->where('execution_order', '>', count($steps))->delete();

        return count($steps);
    }

    /**
     * Map common domain/category aliases to the platform's category vocabulary.
     */
    private function resolveCategory(array $meta): string
    {
        $raw = strtolower(trim($meta['category'] ?? $meta['domain'] ?? $meta['subdomain'] ?? ''));

        $map = [
            'web'                    => 'web-application-security',
            'web-app'                => 'web-application-security',
            'web-application'        => 'web-application-security',
            'injection'              => 'web-application-security',
            'sqli'                   => 'web-application-security',
            'xss'                    => 'web-application-security',
            'pentest'                => 'penetration-testing',
            'pen-test'               => 'penetration-testing',
            'penetration'            => 'penetration-testing',
            'red-team'               => 'red-teaming',
            'redteam'                => 'red-teaming',
            'ctf'                    => 'red-teaming',
            'api'                    => 'api-security',
            'api-security'           => 'api-security',
            'malware'                => 'malware-analysis',
            'forensics'              => 'digital-forensics',
            'cloud'                  => 'cloud-security',
            'network'                => 'network-security',
            'crypto'                 => 'cryptography',
            'cryptography'           => 'cryptography',
            'osint'                  => 'osint',
            'threat-intel'           => 'threat-intelligence',
            'threat-intelligence'    => 'threat-intelligence',
            'incident-response'      => 'incident-response',
            'vulnerability'          => 'vulnerability-management',
            'vuln-management'        => 'vulnerability-management',
            'devsecops'              => 'devsecops',
            'compliance'             => 'compliance-governance',
            'ai-security'            => 'ai-security',
            'llm'                    => 'ai-security',
            'llm-security'           => 'ai-security',
        ];

        return $map[$raw] ?? ($raw ?: 'penetration-testing');
    }
}
