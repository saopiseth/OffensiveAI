<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\Workflow;
use Illuminate\Support\Str;

class AptSimulationService
{
    private const ROW_SIZE = 4;
    private const X_START  = 80;
    private const X_STEP   = 300;
    private const Y_START  = 80;
    private const Y_STEP   = 280;

    private const META_PROMPT = <<<'PROMPT'
You are a senior cybersecurity APT simulation architect.

Your task is to design an Advanced Persistent Threat (APT) simulation advisory system.

⚠️ STRICT OUTPUT FORMAT
Return ONLY valid JSON. No explanation. No markdown. No code fences. No comments.

🧱 1. APT SIMULATION STRUCTURE
Create an APT scenario for: "{{SCENARIO_TYPE}}"
Include: threat_actor, target_type, objective, campaign_id

🔁 2. ATTACK STAGES
Design multi-stage APT lifecycle (MITRE ATT&CK):
Required stages: Initial Access, Execution, Persistence, Privilege Escalation,
Lateral Movement, Defense Evasion, Collection, Exfiltration

Each stage node must include: tactic, technique, mitre_technique_id,
simulation_input, expected_detection_signal, associated_skill (skill_name)

🎯 OUTPUT STRUCTURE — return exactly this shape:

{
  "scenario": {
    "name": "",
    "threat_actor": "",
    "target_type": "",
    "objective": "",
    "campaign_id": ""
  },
  "workflow": {
    "name": "",
    "description": "",
    "nodes": [
      {
        "id": "",
        "type": "input",
        "label": "Target: {{SCENARIO_TYPE}}",
        "skill_name": null,
        "simulation_input": "Organisation profile and entry point",
        "prompt_template": null
      },
      {
        "id": "",
        "type": "skill",
        "label": "",
        "skill_name": "",
        "tactic": "",
        "technique": "",
        "mitre_technique_id": "",
        "mitre_mapping": "",
        "simulation_input": "",
        "expected_detection_signal": "",
        "prompt_template": "You are a cybersecurity APT analyst.\n\nStage: {{stage_name}}\nMITRE Technique: {{technique}}\n\nInput Data:\n{{input}}\n\nAnalyze step-by-step. Return JSON:\n- risk_level (low/medium/high/critical)\n- indicators_of_compromise (array)\n- attack_behavior (string)\n- recommended_actions (array)\n- confidence_score (0-1)",
        "input_schema": { "input": "string", "stage_name": "string", "technique": "string" },
        "output_schema": { "risk_level": "string", "indicators_of_compromise": "array", "attack_behavior": "string", "recommended_actions": "array", "confidence_score": "number" }
      },
      {
        "id": "advisory_node",
        "type": "output",
        "label": "Advisory Report",
        "skill_name": null
      }
    ],
    "edges": [
      { "from": "", "to": "", "label": "", "condition": "default" }
    ]
  },
  "advisory": {
    "risk_assessment": "critical",
    "attack_summary": "",
    "detected_stages": [],
    "missed_stages": [],
    "key_findings": [],
    "recommended_actions": [],
    "incident_response_priority": "",
    "mitre_coverage": []
  }
}

Generate ONE input node, EIGHT skill nodes (one per stage), ONE output node.
Create edges connecting every node sequentially plus a detection_fail branch
from Privilege Escalation back to Defense Evasion.
PROMPT;

    public function __construct(private readonly AiProviderService $aiProvider) {}

    public function simulate(string $scenarioType, ?string $createdBy = null): array
    {
        $prompt = str_replace('{{SCENARIO_TYPE}}', $scenarioType, self::META_PROMPT);

        // APT generation requires high reasoning — always use Claude explicitly.
        // Falls back to the active provider if Claude is not configured.
        $claudeSetting = $this->aiProvider->getProviderByName('claude')
            ?? $this->aiProvider->getActiveProvider();

        $result = $this->aiProvider->complete($prompt, $claudeSetting, [
            'model'       => 'claude-opus-4-7',
            'max_tokens'  => 8000,
            'temperature' => 0.7,
        ]);

        $data     = $this->extractJson($result['content']);
        $workflow = $this->persistWorkflow($data, $scenarioType, $createdBy);

        return [
            'workflow'    => $workflow,
            'scenario'    => $data['scenario'] ?? [],
            'advisory'    => $data['advisory'] ?? [],
            'stages'      => $this->extractStages($data),
            'tokens_used' => $result['tokens_used'],
            'duration_ms' => $result['duration_ms'],
        ];
    }

    // ─── JSON extraction ──────────────────────────────────────────────────────

    private function extractJson(string $raw): array
    {
        $raw = preg_replace('/^```(?:json)?\s*/im', '', $raw);
        $raw = preg_replace('/^```\s*$/im', '', $raw);

        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $raw = substr($raw, $start, $end - $start + 1);
        }

        $data = json_decode($raw, true);

        if (!$data) {
            throw new \RuntimeException(
                'Failed to parse Claude response as JSON. Raw: ' . substr($raw, 0, 400)
            );
        }

        return $data;
    }

    // ─── Workflow persistence ─────────────────────────────────────────────────

    private function persistWorkflow(array $data, string $scenarioType, ?string $createdBy): Workflow
    {
        $rawNodes = $data['workflow']['nodes'] ?? [];
        $rawEdges = $data['workflow']['edges'] ?? [];

        return Workflow::create([
            'created_by'  => $createdBy,
            'name'        => $data['workflow']['name'] ?? "APT Simulation: {$scenarioType}",
            'description' => $data['workflow']['description'] ?? '',
            'graph_data'  => [
                'nodes'    => $this->buildNodes($rawNodes),
                'edges'    => $this->buildEdges($rawEdges),
                'apt_meta' => [
                    'scenario_type' => $scenarioType,
                    'scenario'      => $data['scenario'] ?? [],
                    'advisory'      => $data['advisory'] ?? [],
                    'generated_at'  => now()->toIso8601String(),
                ],
            ],
            'is_active'   => true,
            'status'      => 'published',
        ]);
    }

    // ─── Node builder (snake layout) ──────────────────────────────────────────

    private function buildNodes(array $rawNodes): array
    {
        $total = count($rawNodes);
        $nodes = [];

        foreach ($rawNodes as $idx => $raw) {
            $row  = intdiv($idx, self::ROW_SIZE);
            $col  = $idx % self::ROW_SIZE;

            $leftToRight  = $row % 2 === 0;
            $nodesInRow   = min(self::ROW_SIZE, $total - $row * self::ROW_SIZE);
            $effectiveCol = $leftToRight ? $col : ($nodesInRow - 1 - $col);
            $rowOffset    = (int) ((self::ROW_SIZE - $nodesInRow) * self::X_STEP / 2);

            $x = self::X_START + $effectiveCol * self::X_STEP + $rowOffset;
            $y = self::Y_START + $row * self::Y_STEP;

            $type    = $raw['type'] ?? 'skill';
            $dbSkill = ($type === 'skill') ? $this->matchSkill($raw['skill_name'] ?? '') : null;

            $nodes[] = [
                'id'       => $raw['id'] ?? ('node-' . ($idx + 1)),
                'type'     => 'custom',
                'position' => ['x' => $x, 'y' => $y],
                'data'     => array_filter([
                    'type'                      => $type,
                    'label'                     => $raw['label'] ?? $raw['skill_name'] ?? Str::title($type),
                    'skill_name'                => $raw['skill_name'] ?? null,
                    'skillId'                   => $dbSkill?->id,
                    'category'                  => $dbSkill?->category ?? 'apt-simulation',
                    'tactic'                    => $raw['tactic'] ?? null,
                    'technique'                 => $raw['technique'] ?? null,
                    'mitre_technique_id'        => $raw['mitre_technique_id'] ?? null,
                    'mitre_mapping'             => $raw['mitre_mapping'] ?? null,
                    'simulation_input'          => $raw['simulation_input'] ?? null,
                    'expected_detection_signal' => $raw['expected_detection_signal'] ?? null,
                    'prompt_template'           => $raw['prompt_template'] ?? null,
                    'input_schema'              => $raw['input_schema'] ?? null,
                    'output_schema'             => $raw['output_schema'] ?? null,
                ], fn($v) => $v !== null),
            ];
        }

        return $nodes;
    }

    // ─── Edge builder ─────────────────────────────────────────────────────────

    private function buildEdges(array $rawEdges): array
    {
        $edges = [];

        foreach ($rawEdges as $i => $raw) {
            $from = $raw['from'] ?? $raw['source'] ?? null;
            $to   = $raw['to']   ?? $raw['target'] ?? null;
            if (!$from || !$to) continue;

            $condition = $raw['condition'] ?? 'default';
            $style     = match ($condition) {
                'detection_fail'    => ['stroke' => '#ef4444', 'strokeWidth' => 2, 'strokeDasharray' => '6,3'],
                'detection_success' => ['stroke' => '#22c55e', 'strokeWidth' => 2],
                default             => ['stroke' => '#6d28d9', 'strokeWidth' => 2],
            };

            $edges[] = [
                'id'       => "e{$i}-{$from}-{$to}",
                'source'   => $from,
                'target'   => $to,
                'type'     => 'smoothstep',
                'animated' => true,
                'label'    => $raw['label'] ?? '',
                'style'    => $style,
            ];
        }

        return $edges;
    }

    // ─── Skill matching ───────────────────────────────────────────────────────

    private function matchSkill(string $skillName): ?Skill
    {
        if (!$skillName) return null;

        // 1. Exact slug match
        foreach ([$skillName, Str::slug($skillName)] as $name) {
            $s = Skill::where('name', $name)->first();
            if ($s) return $s;
        }

        // 2. All significant words present
        $words = array_filter(
            explode(' ', strtolower($skillName)),
            fn($w) => strlen($w) >= 4 && !in_array($w, ['with', 'from', 'using', 'the', 'and', 'for', 'that'])
        );

        if (count($words) >= 2) {
            $q = Skill::query();
            foreach ($words as $word) {
                $q->where('name', 'like', "%{$word}%");
            }
            $s = $q->first();
            if ($s) return $s;
        }

        // 3. Category-based fallback
        $lower       = strtolower($skillName);
        $categoryMap = [
            'phishing'    => 'phishing-defense',
            'malware'     => 'malware-analysis',
            'network'     => 'network-security',
            'memory'      => 'digital-forensics',
            'forensic'    => 'digital-forensics',
            'threat'      => 'threat-intelligence',
            'reputation'  => 'threat-intelligence',
            'behavior'    => 'soc-operations',
            'anomaly'     => 'soc-operations',
            'siem'        => 'soc-operations',
            'privilege'   => 'identity-access-management',
            'lateral'     => 'red-teaming',
            'persistence' => 'endpoint-security',
            'endpoint'    => 'endpoint-security',
            'execution'   => 'endpoint-security',
            'evasion'     => 'red-teaming',
            'exfiltrat'   => 'soc-operations',
            'collection'  => 'digital-forensics',
            'recon'       => 'penetration-testing',
            'access'      => 'penetration-testing',
        ];

        foreach ($categoryMap as $keyword => $category) {
            if (str_contains($lower, $keyword)) {
                return Skill::where('category', $category)->inRandomOrder()->first();
            }
        }

        return null;
    }

    // ─── Stage extraction (for API response) ─────────────────────────────────

    private function extractStages(array $data): array
    {
        return collect($data['workflow']['nodes'] ?? [])
            ->where('type', 'skill')
            ->map(fn($n) => [
                'label'                     => $n['label'] ?? $n['skill_name'] ?? '',
                'tactic'                    => $n['tactic'] ?? '',
                'technique'                 => $n['technique'] ?? '',
                'mitre_technique_id'        => $n['mitre_technique_id'] ?? '',
                'expected_detection_signal' => $n['expected_detection_signal'] ?? '',
            ])
            ->values()
            ->all();
    }
}
