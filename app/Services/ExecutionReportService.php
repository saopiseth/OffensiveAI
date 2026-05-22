<?php

namespace App\Services;

use App\Models\Execution;
use App\Models\Finding;
use App\Models\PentestChecklist;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExecutionReportService
{
    // Cap raw tool outputs sent to Claude to avoid prompt bloat.
    private const MAX_FINDINGS_CHARS = 80_000;

    public function __construct(private AiProviderService $aiProvider) {}

    public function generate(Execution $execution): void
    {
        $execution->update([
            'report_status' => 'processing',
            'report_error'  => null,
        ]);

        Log::info("Report generation started for execution {$execution->id}");

        try {
            $execution->loadMissing([
                'workflow',
                'target',
                'logs'            => fn($q) => $q->orderBy('step_order'),
                'childExecutions' => fn($q) => $q->with([
                    'skill:id,name,category',
                    'logs' => fn($q) => $q->orderBy('step_order'),
                ]),
            ]);

            // ── Load findings directly via authoritative query ──────────────
            $findings = Finding::where('execution_id', $execution->id)
                ->orderByRaw("FIELD(severity, 'Critical', 'High', 'Medium', 'Low', 'Informational')")
                ->orderBy('created_at')
                ->get();

            $findingCount = $findings->count();

            Log::info("Report [{$execution->id}] findings in DB: {$findingCount}");

            // Require at least one finding OR tool-log output
            $rawSections = $this->collectRawOutputs($execution);
            if ($findingCount === 0 && empty($rawSections)) {
                $execution->update([
                    'report_status' => 'failed',
                    'report_error'  => 'No findings or completed tool outputs to summarise.',
                ]);
                return;
            }

            $workflowName = $execution->workflow?->name ?? 'Security Assessment';
            $targetInfo   = $this->resolveTargetInfo($execution);
            $date         = now()->format('F j, Y');

            // Raw tool outputs — context for Claude's narrative sections only
            $rawBody = implode("\n\n", $rawSections);
            if (mb_strlen($rawBody) > self::MAX_FINDINGS_CHARS) {
                $rawBody = mb_substr($rawBody, 0, self::MAX_FINDINGS_CHARS)
                    . "\n\n[... tool outputs truncated ...]";
            }

            // ── Ask Claude for all sections EXCEPT Technical Details ────────
            $prompt = $this->buildPrompt($workflowName, $targetInfo, $date, $rawBody, $findings);

            $result = $this->aiProvider->complete($prompt, null, [
                'model'       => 'claude-sonnet-4-6',
                'max_tokens'  => 12000,
                'temperature' => 0.1,
            ]);

            // ── Build Technical Details programmatically ────────────────────
            $technicalDetails = $this->buildTechnicalDetails($findings);

            $renderedCount = $findings->count();
            Log::info("Report [{$execution->id}] Technical Details rendered: {$renderedCount}");

            if ($renderedCount !== $findingCount) {
                Log::error("Report [{$execution->id}] MISMATCH — DB: {$findingCount}, rendered: {$renderedCount}");
            }

            // Splice Technical Details into Claude's output (replace placeholder)
            $reportBody = str_replace(
                '<!-- TECHNICAL_DETAILS_PLACEHOLDER -->',
                $technicalDetails,
                $result['content']
            );

            // If Claude didn't emit the placeholder (shouldn't happen), append it
            if ($reportBody === $result['content']) {
                $reportBody .= "\n\n" . $technicalDetails;
            }

            $reportBody .= $this->buildChecklist($execution);

            $outputData           = $execution->output_data ?? [];
            $outputData['report'] = [
                'content'      => $reportBody,
                'generated_at' => now()->toIso8601String(),
                'model'        => $result['model'],
                'tokens_used'  => $result['tokens_used'],
            ];

            $execution->update([
                'output_data'         => $outputData,
                'report_status'       => 'completed',
                'report_error'        => null,
                'report_generated_at' => now(),
            ]);

            Log::info("Report [{$execution->id}] completed — {$findingCount} findings, {$result['tokens_used']} tokens, {$result['duration_ms']}ms");
        } catch (\Throwable $e) {
            $msg = mb_substr($e->getMessage(), 0, 1000);
            Log::error("Report generation failed for execution {$execution->id}: {$msg}");

            $execution->update([
                'report_status' => 'failed',
                'report_error'  => $msg,
            ]);

            throw $e;
        }
    }

    // ── Programmatic Technical Details ─────────────────────────────────────────

    private function buildTechnicalDetails(\Illuminate\Support\Collection $findings): string
    {
        if ($findings->isEmpty()) {
            return "## Technical Details\n\n*No structured findings recorded.*\n";
        }

        $lines = ["## Technical Details\n"];

        foreach ($findings as $index => $f) {
            $severityUpper = strtoupper($f->severity ?? 'INFORMATIONAL');
            $lines[] = "### " . ($f->title ?? 'Untitled Finding') . " — {$severityUpper}\n";

            // Metadata table
            $lines[] = "| Field | Value |";
            $lines[] = "|---|---|";
            $lines[] = "| **Severity** | " . ($f->severity ?? '—') . " |";
            $lines[] = "| **OWASP Test** | " . ($f->owasp_category ?? '—') . " |";
            $lines[] = "| **CWE** | " . ($f->cwe_id ?? '—') . " |";
            $lines[] = "";

            if ($f->description) {
                $lines[] = "**Description:**\n";
                $lines[] = $f->description . "\n";
            }

            if ($f->impact) {
                $lines[] = "**Impact:**\n";
                $lines[] = $f->impact . "\n";
            }

            // PoC Steps
            $pocSteps = $f->poc_steps ?? [];
            if (!empty($pocSteps)) {
                $lines[] = "**PoC Steps:**\n";
                foreach ($pocSteps as $i => $step) {
                    $lines[] = ($i + 1) . ". " . $step;
                }
                $lines[] = "";
            } elseif ($f->request || $f->response) {
                // Minimal reproduction note when no explicit steps stored
                $lines[] = "**Reproduction Steps:**\n";
                $lines[] = "1. Send the request shown in the Evidence section below.";
                $lines[] = "2. Observe the response for the described vulnerability indicator.\n";
            }

            if ($f->recommendation) {
                $lines[] = "**Remediation:**\n";
                $lines[] = $f->recommendation . "\n";
            }

            if ($f->request) {
                $lines[] = "**Request**";
                $lines[] = "```";
                $lines[] = $this->truncateEvidence($f->request, 60);
                $lines[] = "```\n";
            }

            if ($f->response) {
                $lines[] = "**Response**";
                $lines[] = "```";
                $lines[] = $this->truncateEvidence($f->response, 60);
                $lines[] = "```\n";
            }

            $lines[] = "---\n";
        }

        return implode("\n", $lines);
    }

    private function truncateEvidence(string $text, int $maxLines): string
    {
        $parts = explode("\n", $text);
        if (count($parts) <= $maxLines) {
            return $text;
        }
        return implode("\n", array_slice($parts, 0, $maxLines))
            . "\n[... truncated to {$maxLines} lines ...]";
    }

    // ── Raw tool outputs (context for Claude only) ─────────────────────────────

    private function collectRawOutputs(Execution $execution): array
    {
        $sections = [];

        foreach ($execution->childExecutions as $child) {
            $skillName    = $child->skill?->name ?? 'Unknown Skill';
            $completedLogs = $child->logs->where('status', 'completed');
            $steps         = [];

            foreach ($completedLogs as $log) {
                if (! $log->output_data) continue;
                $steps[] = $this->formatStep($log->step_name, $log->prompt_rendered, $log->output_data);
            }

            if (!empty($steps)) {
                $sections[] = "### {$skillName}\n\n" . implode("\n\n---\n\n", $steps);
            }
        }

        foreach ($execution->logs->where('status', 'completed') as $log) {
            if (! $log->output_data) continue;
            $sections[] = $this->formatStep($log->step_name, $log->prompt_rendered, $log->output_data);
        }

        return $sections;
    }

    private function formatStep(string $stepName, ?string $promptRendered, mixed $outputData): string
    {
        $response = is_string($outputData)
            ? $outputData
            : json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $block = "#### {$stepName}\n\n";

        if ($promptRendered) {
            $trimmed  = mb_strlen($promptRendered) > 2000
                ? '...' . mb_substr($promptRendered, -2000)
                : $promptRendered;
            $block .= "**[REQUEST]**\n```\n{$trimmed}\n```\n\n";
        }

        $block .= "**[RESPONSE]**\n```\n{$response}\n```";

        return $block;
    }

    // ── Prompt for Claude (narrative sections only) ────────────────────────────

    private function buildPrompt(
        string $workflowName,
        string $targetInfo,
        string $date,
        string $rawOutputs,
        \Illuminate\Support\Collection $findings,
    ): string {
        $findingCount     = $findings->count();
        $structuredBlock  = $this->formatStructuredFindings($findings);

        return <<<PROMPT
You are a senior cybersecurity consultant producing a formal security assessment report aligned to OWASP WSTG v4.2, CVSS v3.1, and MITRE CWE.

═══════════════════════════════════════════════════
ASSESSMENT CONTEXT
═══════════════════════════════════════════════════
Assessment Type : {$workflowName}
Target          : {$targetInfo}
Date            : {$date}

═══════════════════════════════════════════════════
CONFIRMED FINDINGS ({$findingCount} total) — AUTHORITATIVE
═══════════════════════════════════════════════════
Use EXACTLY these {$findingCount} findings for all summary tables and counts.

{$structuredBlock}

═══════════════════════════════════════════════════
RAW TOOL OUTPUTS (narrative context only)
═══════════════════════════════════════════════════
{$rawOutputs}

───────────────────────────────────────────────────
Generate the report sections below in Markdown.
The Technical Details section will be inserted automatically — write the placeholder exactly as shown.

# {$workflowName} — Security Assessment Report

## Executive Summary
2–3 paragraphs: scope, methodology, total finding count by severity, overall risk posture.

Include this severity count table (use the confirmed findings above):

| Severity | Count |
|---|---|
| Critical | N |
| High | N |
| Medium | N |
| Low | N |
| Informational | N |
| **Total** | **{$findingCount}** |

## Scope & Objectives
- Target system(s) assessed
- Assessment type and testing standard (OWASP WSTG v4.2)
- Assessment boundaries and exclusions

## Methodology
Testing approach, tools, and techniques used. State severity uses CVSS v3.1 Base Score and findings are mapped to OWASP WSTG v4.2 and MITRE CWE.

## Findings & Vulnerabilities

Single consolidated table of ALL {$findingCount} findings, sorted by severity (Critical → High → Medium → Low → Informational):

| # | Vulnerability Name | Severity | OWASP Mapping | CWE Mapping | Status |
|---|---|---|---|---|---|
| 1 | [Finding Title] | Critical | WSTG-XXXX-XX | CWE-XXX | Identified |

<!-- TECHNICAL_DETAILS_PLACEHOLDER -->

## Attack Surface Summary
Key entry points, exposed services, open ports, and overall attack surface.

## Recommendations

| Priority | OWASP WSTG | CWE | Finding | Recommended Action |
|---|---|---|---|---|
| Immediate (0–7 days) | … | … | … | … |
| Short-term (1–4 weeks) | … | … | … | … |
| Long-term (1–3 months) | … | … | … | … |

## Conclusion
Overall risk rating. Reference the findings count and most critical issues.

───────────────────────────────────────────────────
RULES:
1. Base content strictly on the confirmed findings and tool outputs — never fabricate data.
2. Every finding in the Findings & Vulnerabilities table MUST use the exact title from the confirmed findings list.
3. The Findings & Vulnerabilities table MUST contain exactly {$findingCount} rows (one per finding).
4. Write the placeholder `<!-- TECHNICAL_DETAILS_PLACEHOLDER -->` exactly as shown — do NOT write a Technical Details section yourself; it is generated automatically.
5. ## Conclusion MUST be the last section — do NOT include any Appendix.
PROMPT;
    }

    private function formatStructuredFindings(\Illuminate\Support\Collection $findings): string
    {
        if ($findings->isEmpty()) {
            return '*No structured findings recorded.*';
        }

        $out = [];
        foreach ($findings as $index => $f) {
            $num   = $index + 1;
            $block = "**Finding #{$num}: {$f->title}**\n";
            $block .= "- Severity: {$f->severity}\n";
            if ($f->owasp_category) $block .= "- OWASP: {$f->owasp_category}\n";
            if ($f->cwe_id)         $block .= "- CWE: {$f->cwe_id}\n";
            if ($f->description)    $block .= "- Description: " . Str::limit($f->description, 300) . "\n";
            $out[] = $block;
        }

        return implode("\n", $out);
    }

    // ── Checklist appendix ─────────────────────────────────────────────────────

    private function buildChecklist(Execution $execution): string
    {
        $this->ensureChecklist($execution);

        $items = PentestChecklist::where('execution_id', $execution->id)
            ->orderBy('category')
            ->orderBy('activity_name')
            ->get();

        if ($items->isEmpty()) {
            return "\n\n---\n\n"
                . "## Appendix A: Penetration Testing Checklist\n\n"
                . "*No checklist activities were recorded for this assessment.*\n";
        }

        $grouped   = $items->groupBy('category');
        $total     = $items->count();
        $completed = $items->where('is_completed', true)->count();

        $lines = [];
        foreach ($grouped as $category => $categoryItems) {
            $lines[] = "**Category: {$category}**\n";
            foreach ($categoryItems as $item) {
                $box     = $item->is_completed ? '☑' : '☐';
                $lines[] = "{$box} {$item->activity_name}";
            }
            $lines[] = '';
        }

        while (!empty($lines) && trim(end($lines)) === '') {
            array_pop($lines);
        }

        $lines[] = "\n---\n";
        $lines[] = "**Legend:** ☑ Completed · ☐ Not Completed";

        return "\n\n---\n\n"
            . "## Appendix A: Penetration Testing Checklist\n\n"
            . "The following activities were planned and executed during this assessment, "
            . "grouped by testing category. **{$completed} of {$total}** activities completed.\n\n"
            . implode("\n", $lines) . "\n";
    }

    private function ensureChecklist(Execution $execution): void
    {
        if (PentestChecklist::where('execution_id', $execution->id)->exists()) {
            return;
        }

        $records = [];
        $order   = 1;

        foreach ($execution->childExecutions as $child) {
            $skill    = $child->skill;
            $category = $this->humanizeCategory($skill?->category ?? $skill?->name ?? 'General');

            foreach ($child->logs->sortBy('step_order') as $log) {
                $records[] = [
                    'id'            => (string) Str::uuid(),
                    'execution_id'  => $execution->id,
                    'category'      => $category,
                    'activity_name' => $log->step_name ?? 'Unknown Step',
                    'is_completed'  => $log->status === 'completed',
                    'sort_order'    => $order++,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }
        }

        foreach ($execution->logs->sortBy('step_order') as $log) {
            $records[] = [
                'id'            => (string) Str::uuid(),
                'execution_id'  => $execution->id,
                'category'      => 'Assessment',
                'activity_name' => $log->step_name ?? 'Unknown Step',
                'is_completed'  => $log->status === 'completed',
                'sort_order'    => $order++,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        if (!empty($records)) {
            PentestChecklist::insert($records);
        }
    }

    private function humanizeCategory(string $raw): string
    {
        return Str::title(str_replace(['-', '_'], ' ', $raw));
    }

    private function resolveTargetInfo(Execution $execution): string
    {
        if ($execution->target) {
            return "{$execution->target->name} ({$execution->target->value})";
        }
        $input = $execution->input_data ?? [];
        return $input['target_name'] ?? $input['target'] ?? 'Not specified';
    }
}
