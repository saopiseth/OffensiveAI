<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\SkillStep;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;

class AdvisorySimulationSeeder extends Seeder
{
    /*
     * Advisory Simulation Workflow
     *
     * Simulates a full-lifecycle security advisory engagement:
     *   Recon → Threat Actor Profiling → Attack Surface → Threat Modeling
     *       → Maturity Assessment → Tabletop Exercise → Advisory Report
     */

    private const WORKFLOW_NAME = 'Advisory Simulation';

    // Skill slugs (names as stored in DB from imported SKILL.md files)
    private const PHASES = [
        [
            'skill'       => 'conducting-external-reconnaissance-with-osint',
            'label'       => 'External Reconnaissance',
            'description' => 'Map the target organisation\'s external footprint using OSINT techniques before any advisory engagement.',
        ],
        [
            'skill'       => 'profiling-threat-actor-groups',
            'label'       => 'Threat Actor Profiling',
            'description' => 'Identify and profile threat actors most likely to target the client based on sector, geography, and technology stack.',
        ],
        [
            'skill'       => 'implementing-attack-surface-management',
            'label'       => 'Attack Surface Management',
            'description' => 'Enumerate and classify all externally reachable assets to establish the advisory scope.',
        ],
        [
            'skill'       => 'implementing-threat-modeling-with-mitre-attack',
            'label'       => 'MITRE ATT&CK Threat Modeling',
            'description' => 'Map identified threat actors and TTPs to the client\'s environment using the MITRE ATT&CK framework.',
        ],
        [
            'skill'       => 'performing-nist-csf-maturity-assessment',
            'label'       => 'NIST CSF Maturity Assessment',
            'description' => 'Evaluate the client\'s current security controls against the NIST Cybersecurity Framework to identify gaps.',
        ],
        [
            'skill'       => 'performing-soc-tabletop-exercise',
            'label'       => 'Tabletop Exercise Facilitation',
            'description' => 'Facilitate a scenario-based tabletop exercise to stress-test incident response readiness.',
        ],
        [
            'skill'       => 'generating-threat-intelligence-reports',
            'label'       => 'Advisory Report Generation',
            'description' => 'Synthesise all findings into a structured advisory report with prioritised recommendations.',
        ],
    ];

    // ─── Graph layout constants ───────────────────────────────────────────────

    // Nodes 1-4 on top row (left → right), nodes 5-7 on bottom row (right → left)
    // This creates an S-curve that reads as a continuous flow.
    private const ROW_Y   = [200, 520];
    private const X_START = 80;
    private const X_STEP  = 320;

    public function run(): void
    {
        if (Workflow::where('name', self::WORKFLOW_NAME)->exists()) {
            $this->command->warn(self::WORKFLOW_NAME . ' already exists — skipping.');
            return;
        }

        $adminId = User::where('email', 'admin@redto.app')->value('id');

        $phases  = $this->resolveSkills();
        $graph   = $this->buildGraph($phases);

        Workflow::create([
            'created_by'  => $adminId,
            'name'        => self::WORKFLOW_NAME,
            'description' => 'End-to-end security advisory engagement simulation: external reconnaissance → threat actor profiling → attack surface mapping → MITRE ATT&CK threat modeling → NIST CSF maturity assessment → tabletop exercise → advisory report. Mirrors a real consulting engagement lifecycle.',
            'graph_data'  => $graph,
            'is_active'   => true,
            'status'      => 'published',
        ]);

        $found   = count(array_filter($phases, fn($p) => $p['skill'] !== null));
        $missing = count($phases) - $found;

        $this->command->info('✅ Advisory Simulation workflow created.');
        $this->command->info("   Nodes: {$found} skills resolved" . ($missing ? ", {$missing} placeholder(s)" : '.'));
    }

    // ─── Skill resolution ─────────────────────────────────────────────────────

    private function resolveSkills(): array
    {
        return array_map(function (array $phase) {
            $skill = Skill::where('name', $phase['skill'])->first()
                  ?? Skill::where('name', 'like', '%' . str_replace('-', '%', $phase['skill']) . '%')->first();

            return array_merge($phase, ['skill' => $skill]);
        }, self::PHASES);
    }

    // ─── Graph builder ────────────────────────────────────────────────────────

    private function buildGraph(array $phases): array
    {
        $nodes = [];
        $edges = [];

        $topCount    = 4; // first 4 nodes on top row, remaining on bottom
        $bottomPhases = array_slice($phases, $topCount);

        // Top row: left → right
        foreach (array_slice($phases, 0, $topCount) as $idx => $phase) {
            $nodes[] = $this->node(
                $idx + 1,
                self::X_START + $idx * self::X_STEP,
                self::ROW_Y[0],
                $phase
            );
        }

        // Bottom row: continues from node-4 position rightward-to-left
        // (mirror: starts at same x as node-4, goes left)
        foreach ($bottomPhases as $idx => $phase) {
            $nodeNum = $topCount + $idx + 1;
            $xPos    = self::X_START + ($topCount - 1 - $idx) * self::X_STEP;
            $nodes[] = $this->node($nodeNum, $xPos, self::ROW_Y[1], $phase);
        }

        // Edges: sequential chain 1→2→3→…→N
        $total = count($nodes);
        for ($i = 1; $i < $total; $i++) {
            $edges[] = [
                'id'       => "e{$i}-" . ($i + 1),
                'source'   => "node-{$i}",
                'target'   => 'node-' . ($i + 1),
                'type'     => 'smoothstep',
                'animated' => true,
                'label'    => $this->edgeLabel($i),
            ];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function node(int $num, int $x, int $y, array $phase): array
    {
        $skill = $phase['skill'];

        return [
            'id'       => "node-{$num}",
            'type'     => 'skillNode',
            'position' => ['x' => $x, 'y' => $y],
            'data'     => [
                'skillId'     => $skill?->id,
                'label'       => $phase['label'],
                'category'    => $skill?->category ?? 'cybersecurity',
                'description' => $phase['description'],
                'phase'       => $num,
            ],
        ];
    }

    private function edgeLabel(int $fromNode): string
    {
        $labels = [
            1 => 'Intel Gathered',
            2 => 'Actors Profiled',
            3 => 'Surface Mapped',
            4 => 'TTPs Modeled',
            5 => 'Gaps Identified',
            6 => 'Scenarios Tested',
        ];

        return $labels[$fromNode] ?? '';
    }
}
