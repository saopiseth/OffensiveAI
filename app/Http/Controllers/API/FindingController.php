<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\FindingResource;
use App\Models\Execution;
use App\Models\Finding;
use App\Services\AiProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FindingController extends Controller
{
    private const SEVERITY_OPTIONS = ['Critical', 'High', 'Medium', 'Low', 'Informational'];

    private array $baseRules = [
        'title'          => 'required|string|max:500',
        'severity'       => 'required|in:Critical,High,Medium,Low,Informational',
        'owasp_category' => 'nullable|string|max:255',
        'cwe_id'         => 'nullable|string|max:255',
        'description'    => 'nullable|string',
        'impact'         => 'nullable|string',
        'recommendation' => 'nullable|string',
        'poc_steps'      => 'nullable|array',
        'poc_steps.*'    => 'string',
        'request'        => 'nullable|string',
        'response'       => 'nullable|string',
    ];

    public function index(Execution $execution): AnonymousResourceCollection
    {
        $findings = Finding::where('execution_id', $execution->id)
            ->orderBy('finding_order')
            ->get();

        return FindingResource::collection($findings);
    }

    public function chartData(Execution $execution): JsonResponse
    {
        $severityOrder = ['Critical' => 0, 'High' => 1, 'Medium' => 2, 'Low' => 3, 'Informational' => 4];

        $findings = Finding::where('execution_id', $execution->id)->get();

        if ($findings->isEmpty()) {
            return response()->json(['severity' => [], 'owasp' => [], 'cwe' => []]);
        }

        // Severity counts — maintain Critical→Informational order
        $severityCounts = $findings->groupBy('severity')
            ->map(fn($g) => $g->count())
            ->sortBy(fn($_, $k) => $severityOrder[$k] ?? 99);

        // OWASP counts — short label = first token before ' – '
        $owaspCounts = $findings
            ->filter(fn($f) => !empty($f->owasp_category))
            ->groupBy(fn($f) => $this->shortLabel($f->owasp_category))
            ->map(fn($g) => $g->count())
            ->sortByDesc(fn($c) => $c);

        // CWE counts — short label = first token before ' – '
        $cweCounts = $findings
            ->filter(fn($f) => !empty($f->cwe_id))
            ->groupBy(fn($f) => $this->shortLabel($f->cwe_id))
            ->map(fn($g) => $g->count())
            ->sortByDesc(fn($c) => $c);

        return response()->json([
            'severity' => $severityCounts->map(fn($count, $label) => compact('label', 'count'))->values(),
            'owasp'    => $owaspCounts->map(fn($count, $label) => compact('label', 'count'))->values(),
            'cwe'      => $cweCounts->map(fn($count, $label) => compact('label', 'count'))->values(),
            'total'    => $findings->count(),
        ]);
    }

    private function shortLabel(string $raw): string
    {
        // "CWE-639 – Authorization Bypass…" → "CWE-639"
        $pos = mb_strpos($raw, ' – ');
        if ($pos !== false) {
            return trim(mb_substr($raw, 0, $pos));
        }
        return explode(' ', trim($raw))[0];
    }

    public function store(Request $request, Execution $execution): JsonResponse
    {
        $data = $request->validate($this->baseRules);

        $nextOrder = Finding::where('execution_id', $execution->id)->max('finding_order') + 1;

        $finding = Finding::create(array_merge($data, [
            'execution_id'  => $execution->id,
            'finding_order' => $nextOrder,
        ]));

        return response()->json(new FindingResource($finding), 201);
    }

    public function update(Request $request, Execution $execution, Finding $finding): JsonResponse
    {
        $this->abortIfMismatch($finding, $execution);

        $data = $request->validate($this->baseRules);
        $finding->update($data);

        return response()->json(new FindingResource($finding));
    }

    public function destroy(Execution $execution, Finding $finding): JsonResponse
    {
        $this->abortIfMismatch($finding, $execution);
        $finding->delete();

        return response()->json(['message' => 'Finding deleted']);
    }

    public function generateAi(Request $request, Execution $execution, AiProviderService $ai): JsonResponse
    {
        $request->validate([
            'raw_scan_data'      => 'required|string|max:20000',
            'vulnerability_type' => 'nullable|string|max:200',
        ]);

        $vulnHint = $request->vulnerability_type
            ? "Vulnerability type hint: {$request->vulnerability_type}\n\n"
            : '';

        $prompt = <<<PROMPT
You are a senior penetration tester. Analyse the raw scan data below and produce a single structured technical finding in strict JSON.

{$vulnHint}Raw scan data:
{$request->raw_scan_data}

Return ONLY a valid JSON object with these exact keys (no markdown, no explanation):
{
  "title": "Short finding title",
  "severity": "Critical|High|Medium|Low|Informational",
  "owasp_category": "WSTG-XXXX-XX – Test Name",
  "cwe_id": "CWE-XXX – Name",
  "description": "What was found and why it is a security concern.",
  "impact": "Business or technical impact if exploited.",
  "recommendation": "Specific remediation steps (may be multiline).",
  "poc_steps": [
    "Step 1: Navigate to the target URL.",
    "Step 2: Submit the payload into the vulnerable parameter.",
    "Step 3: Observe the application response.",
    "Step 4: Confirm the vulnerability impact."
  ],
  "request": "Relevant HTTP request or tool command (verbatim, truncated to 60 lines).",
  "response": "Relevant HTTP response or tool output (verbatim, truncated to 60 lines)."
}
PROMPT;

        $result = $ai->complete($prompt, null, [
            'model'       => 'claude-sonnet-4-6',
            'max_tokens'  => 4096,
            'temperature' => 0.1,
        ]);

        $json = $this->extractJson($result['content']);
        if ($json === null) {
            return response()->json(['message' => 'AI returned malformed response. Please try again.'], 422);
        }

        $nextOrder = Finding::where('execution_id', $execution->id)->max('finding_order') + 1;

        $pocSteps = isset($json['poc_steps']) && is_array($json['poc_steps'])
            ? array_values(array_filter($json['poc_steps'], 'is_string'))
            : null;

        $finding = Finding::create([
            'execution_id'   => $execution->id,
            'finding_order'  => $nextOrder,
            'title'          => $json['title']          ?? 'Untitled Finding',
            'severity'       => in_array($json['severity'] ?? '', self::SEVERITY_OPTIONS)
                                    ? $json['severity'] : 'Informational',
            'owasp_category' => $json['owasp_category'] ?? null,
            'cwe_id'         => $json['cwe_id']         ?? null,
            'description'    => $json['description']    ?? null,
            'impact'         => $json['impact']         ?? null,
            'recommendation' => $json['recommendation'] ?? null,
            'poc_steps'      => $pocSteps,
            'request'        => $json['request']        ?? null,
            'response'       => $json['response']       ?? null,
        ]);

        return response()->json(new FindingResource($finding), 201);
    }

    private function abortIfMismatch(Finding $finding, Execution $execution): void
    {
        if ($finding->execution_id !== $execution->id) {
            abort(404);
        }
    }

    private function extractJson(string $raw): ?array
    {
        $raw = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $raw = preg_replace('/\s*```\s*$/m', '', $raw);

        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');
        if ($start === false || $end === false) {
            return null;
        }

        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
        return is_array($decoded) ? $decoded : null;
    }
}
