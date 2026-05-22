<?php

namespace App\Services;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Parses SKILL.md files (YAML frontmatter + Markdown sections)
 * and raw JSON skill definitions into a normalized array.
 *
 * Supported input formats
 * ───────────────────────
 *  1. Markdown with YAML frontmatter  (--- … ---)
 *  2. Plain JSON object
 *
 * Output structure
 * ────────────────
 * [
 *   'meta'        => [...],          // YAML/JSON metadata
 *   'description' => '...',          // ## Description section
 *   'workflow'    => '...',          // ## Workflow section (single-step fallback)
 *   'steps'       => [               // parsed multi-step array
 *     ['name' => '...', 'prompt_template' => '...', 'description' => '...'],
 *     ...
 *   ],
 * ]
 */
class SkillParserService
{
    // ── Public API ────────────────────────────────────────────────────────────

    public function parse(string $content): array
    {
        $content = trim($content);

        if ($this->isJson($content)) {
            return $this->parseJson($content);
        }

        return $this->parseMarkdown($content);
    }

    // ── Markdown parsing ──────────────────────────────────────────────────────

    private function parseMarkdown(string $content): array
    {
        $meta   = $this->extractYaml($content);
        $body   = $this->stripFrontmatter($content);
        $steps  = $this->extractSteps($body);

        return [
            'meta'        => $meta,
            'description' => $this->extractSection($body, 'Description'),
            'workflow'    => $this->extractSection($body, 'Workflow'),
            'steps'       => $steps,
        ];
    }

    // ── JSON parsing ──────────────────────────────────────────────────────────

    private function parseJson(string $content): array
    {
        $data  = json_decode($content, true);
        $steps = [];

        foreach ($data['steps'] ?? [] as $i => $s) {
            $steps[] = [
                'name'            => $s['name']            ?? "Step " . ($i + 1),
                'description'     => $s['description']     ?? null,
                'prompt_template' => $s['prompt_template'] ?? $s['prompt'] ?? $s['content'] ?? '',
            ];
        }

        return [
            'meta'        => $data,
            'description' => $data['description'] ?? null,
            'workflow'    => $data['workflow']     ?? null,
            'steps'       => $steps,
        ];
    }

    // ── YAML frontmatter ──────────────────────────────────────────────────────

    private function extractYaml(string $content): array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---/s', $content, $matches)) {
            return [];
        }

        try {
            return Yaml::parse($matches[1]) ?? [];
        } catch (ParseException) {
            return [];
        }
    }

    private function stripFrontmatter(string $content): string
    {
        return preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $content);
    }

    // ── Section extraction ────────────────────────────────────────────────────

    private function extractSection(string $content, string $section): string
    {
        // Match ## Section … until next ## or end of string
        if (preg_match('/^##\s+' . preg_quote($section, '/') . '\s*\n(.*?)(?=^##|\z)/ms', $content, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    /**
     * Extract multi-step definitions.
     *
     * Supports two patterns:
     *   A) ## Step N: Title  (Markdown H2)
     *   B) ### Step N: Title (Markdown H3)
     *
     * Each step body is used as the prompt_template.
     */
    private function extractSteps(string $content): array
    {
        $steps = [];

        // Try H2 steps first, fall back to H3
        $pattern = '/^#{2,3}\s+Step\s+\d+[:\-–]?\s*(.+?)\s*\n(.*?)(?=^#{2,3}\s+Step\s+\d+|\z)/ms';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $i => $match) {
                $steps[] = [
                    'name'            => trim($match[1]),
                    'description'     => null,
                    'prompt_template' => trim($match[2]),
                ];
            }
        }

        return $steps;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isJson(string $content): bool
    {
        if (! str_starts_with($content, '{') && ! str_starts_with($content, '[')) {
            return false;
        }

        json_decode($content);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
