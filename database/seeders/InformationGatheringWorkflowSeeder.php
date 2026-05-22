<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;

class InformationGatheringWorkflowSeeder extends Seeder
{
    private const X_START  = 80;
    private const X_STEP   = 300;
    private const Y_BASE   = 180;
    private const Y_STEP   = 280;
    private const ROW_SIZE = 4;

    private const COLOR = ['edge' => '#06b6d4', 'input' => '#0891b2', 'output' => '#0e7490'];

    public function run(): void
    {
        $admin    = User::role('admin')->first() ?? User::first();
        $skillMap = Skill::pluck('id', 'name')->all();

        $name = 'Information Gathering';

        if (Workflow::where('name', $name)->exists()) {
            $this->command->warn("  Workflow \"{$name}\" already exists — skipping.");
            return;
        }

        $ts    = microtime(true) * 1000;
        $nodes = [];
        $edges = [];

        // Input node
        $inputId = "wf_{$ts}_input";
        $nodes[] = [
            'id'       => $inputId,
            'type'     => 'custom',
            'position' => ['x' => self::X_START, 'y' => self::Y_BASE],
            'data'     => ['type' => 'input', 'label' => 'Engagement Start', 'color' => self::COLOR['input']],
        ];
        $prevId = $inputId;

        foreach ($this->phases() as $i => $phase) {
            $skillId  = $skillMap[$phase['skill']] ?? null;
            $nodeId   = "wf_{$ts}_n{$i}";
            [$x, $y]  = $this->snakePos($i);

            $nodes[] = [
                'id'       => $nodeId,
                'type'     => 'custom',
                'position' => ['x' => $x, 'y' => $y],
                'data'     => [
                    'type'       => 'skill',
                    'label'      => $phase['label'],
                    'skill_id'   => $skillId,
                    'skill_name' => $phase['skill'],
                    'phase'      => $phase['phase'],
                ],
            ];

            $edge = [
                'id'        => "{$prevId}__{$nodeId}",
                'source'    => $prevId,
                'target'    => $nodeId,
                'style'     => ['stroke' => self::COLOR['edge'], 'strokeWidth' => 2],
                'markerEnd' => ['type' => 'arrowclosed', 'color' => self::COLOR['edge']],
            ];
            if (!empty($phase['edge_label'])) $edge['label'] = $phase['edge_label'];
            $edges[]  = $edge;
            $prevId   = $nodeId;
        }

        // Output node
        $last    = end($nodes);
        $outId   = "wf_{$ts}_output";
        $outX    = $last['position']['x'] + self::X_STEP;
        $outY    = $last['position']['y'];
        $nodes[] = [
            'id'       => $outId,
            'type'     => 'custom',
            'position' => ['x' => $outX, 'y' => $outY],
            'data'     => ['type' => 'output', 'label' => 'Intel Report Delivered', 'color' => self::COLOR['output']],
        ];
        $edges[] = [
            'id'        => "{$prevId}__{$outId}",
            'source'    => $prevId,
            'target'    => $outId,
            'label'     => 'Final Report',
            'style'     => ['stroke' => self::COLOR['edge'], 'strokeWidth' => 2],
            'markerEnd' => ['type' => 'arrowclosed', 'color' => self::COLOR['edge']],
        ];

        $workflow = Workflow::create([
            'created_by'  => $admin->id,
            'name'        => $name,
            'description' => 'Comprehensive passive and active information gathering: OSINT → DNS/subdomain recon → infrastructure intel → dark-web exposure monitoring → threat actor profiling → consolidated threat intelligence report.',
            'status'      => 'published',
            'is_active'   => true,
            'graph_data'  => ['nodes' => $nodes, 'edges' => $edges],
        ]);

        $phases   = $this->phases();
        $mapped   = collect($phases)->filter(fn($p) => isset($skillMap[$p['skill']]))->count();
        $unmapped = count($phases) - $mapped;

        $this->command->info("  ✓ {$workflow->name}");
        $this->command->line("    nodes: " . count($nodes) . "  |  skills mapped: {$mapped}  |  unmapped: {$unmapped}");
    }

    private function snakePos(int $i): array
    {
        $row = (int) floor($i / self::ROW_SIZE);
        $col = $i % self::ROW_SIZE;
        $y   = self::Y_BASE + ($row + 1) * self::Y_STEP;
        if ($row % 2 === 1) $col = self::ROW_SIZE - 1 - $col;
        $x = self::X_START + $col * self::X_STEP;
        return [$x, $y];
    }

    private function phases(): array
    {
        return [
            // ── Passive Recon ──────────────────────────────────────────────
            [
                'phase'      => 'Passive Recon',
                'label'      => 'External OSINT Recon',
                'skill'      => 'conducting-external-reconnaissance-with-osint',
                'edge_label' => 'Scope Defined',
            ],
            [
                'phase'      => 'Passive Recon',
                'label'      => 'OSINT Target Profiling',
                'skill'      => 'OSINT Target Profiling',
                'edge_label' => 'Target Profile',
            ],
            [
                'phase'      => 'Passive Recon',
                'label'      => 'SpiderFoot OSINT Scan',
                'skill'      => 'performing-osint-with-spiderfoot',
                'edge_label' => 'Data Sources',
            ],
            [
                'phase'      => 'Passive Recon',
                'label'      => 'Open Source Intel Gathering',
                'skill'      => 'performing-open-source-intelligence-gathering',
                'edge_label' => 'Raw Intel',
            ],
            // ── DNS & Network ──────────────────────────────────────────────
            [
                'phase'      => 'DNS & Network',
                'label'      => 'DNS Enumeration & Zone Transfer',
                'skill'      => 'performing-dns-enumeration-and-zone-transfer',
                'edge_label' => 'DNS Map',
            ],
            [
                'phase'      => 'DNS & Network',
                'label'      => 'Subdomain Enumeration',
                'skill'      => 'performing-subdomain-enumeration-with-subfinder',
                'edge_label' => 'Subdomains',
            ],
            [
                'phase'      => 'DNS & Network',
                'label'      => 'Network Port Scanning',
                'skill'      => 'Network Port Scanner',
                'edge_label' => 'Open Ports',
            ],
            // ── Infrastructure Intel ───────────────────────────────────────
            [
                'phase'      => 'Infrastructure Intel',
                'label'      => 'IP Reputation Analysis (Shodan)',
                'skill'      => 'performing-ip-reputation-analysis-with-shodan',
                'edge_label' => 'Exposure Map',
            ],
            [
                'phase'      => 'Infrastructure Intel',
                'label'      => 'Certificate Transparency Audit',
                'skill'      => 'auditing-tls-certificate-transparency-logs',
                'edge_label' => 'Cert History',
            ],
            // ── Exposure Monitoring ────────────────────────────────────────
            [
                'phase'      => 'Exposure Monitoring',
                'label'      => 'Dark Web Monitoring',
                'skill'      => 'monitoring-darkweb-sources',
                'edge_label' => 'Dark Web Hits',
            ],
            [
                'phase'      => 'Exposure Monitoring',
                'label'      => 'Paste Site Credential Monitoring',
                'skill'      => 'performing-paste-site-monitoring-for-credentials',
                'edge_label' => 'Leaked Creds',
            ],
            // ── Threat Intelligence ────────────────────────────────────────
            [
                'phase'      => 'Threat Intelligence',
                'label'      => 'Threat Actor Profiling (OSINT)',
                'skill'      => 'building-threat-actor-profile-from-osint',
                'edge_label' => 'Actor Profile',
            ],
            [
                'phase'      => 'Threat Intelligence',
                'label'      => 'AI-Driven OSINT Correlation',
                'skill'      => 'performing-ai-driven-osint-correlation',
                'edge_label' => 'Correlated Intel',
            ],
            // ── Reporting ──────────────────────────────────────────────────
            [
                'phase'      => 'Reporting',
                'label'      => 'Threat Intelligence Report',
                'skill'      => 'Threat Intelligence Report',
                'edge_label' => 'Final Report',
            ],
        ];
    }
}
