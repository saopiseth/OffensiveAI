<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;

class WebAppTestingWorkflowsSeeder extends Seeder
{
    private const X_START  = 80;
    private const X_STEP   = 300;
    private const Y_BASE   = 180;
    private const Y_STEP   = 280;
    private const ROW_SIZE = 4;

    private const COLORS = [
        'graybox'  => ['edge' => '#f59e0b', 'input' => '#d97706', 'output' => '#b45309'],
        'whitebox' => ['edge' => '#10b981', 'input' => '#059669', 'output' => '#047857'],
    ];

    public function run(): void
    {
        $admin    = User::role('admin')->first() ?? User::first();
        $skillMap = Skill::pluck('id', 'name')->all();

        foreach ($this->definitions() as $def) {
            if (Workflow::where('name', $def['name'])->exists()) {
                $this->command->warn("  Already exists — skipping: {$def['name']}");
                continue;
            }
            $this->seed($def, $skillMap, $admin->id);
        }
    }

    private function seed(array $def, array $skillMap, string $userId): void
    {
        $color  = self::COLORS[$def['color_key']];
        $ts     = microtime(true) * 1000;
        $nodes  = [];
        $edges  = [];

        $inputId = "wf_{$ts}_input";
        $nodes[] = [
            'id'       => $inputId,
            'type'     => 'custom',
            'position' => ['x' => self::X_START, 'y' => self::Y_BASE],
            'data'     => ['type' => 'input', 'label' => 'Engagement Start', 'color' => $color['input']],
        ];
        $prevId = $inputId;

        foreach ($def['phases'] as $i => $phase) {
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
                'style'     => ['stroke' => $color['edge'], 'strokeWidth' => 2],
                'markerEnd' => ['type' => 'arrowclosed', 'color' => $color['edge']],
            ];
            if (!empty($phase['edge_label'])) $edge['label'] = $phase['edge_label'];
            $edges[] = $edge;
            $prevId  = $nodeId;
        }

        $last    = end($nodes);
        $outId   = "wf_{$ts}_output";
        $outX    = $last['position']['x'] + self::X_STEP;
        $outY    = $last['position']['y'];
        $nodes[] = [
            'id'       => $outId,
            'type'     => 'custom',
            'position' => ['x' => $outX, 'y' => $outY],
            'data'     => ['type' => 'output', 'label' => 'Report Delivered', 'color' => $color['output']],
        ];
        $edges[] = [
            'id'        => "{$prevId}__{$outId}",
            'source'    => $prevId,
            'target'    => $outId,
            'label'     => 'Final Report',
            'style'     => ['stroke' => $color['edge'], 'strokeWidth' => 2],
            'markerEnd' => ['type' => 'arrowclosed', 'color' => $color['edge']],
        ];

        $workflow = Workflow::create([
            'created_by'  => $userId,
            'name'        => $def['name'],
            'description' => $def['description'],
            'status'      => 'published',
            'is_active'   => true,
            'graph_data'  => ['nodes' => $nodes, 'edges' => $edges],
        ]);

        $phases   = $def['phases'];
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

    private function definitions(): array
    {
        return [

            // ══════════════════════════════════════════════════════════════════
            // GRAY-BOX WEB APPLICATION TESTING
            // Tester has partial knowledge: some credentials, API docs, app roles
            // ══════════════════════════════════════════════════════════════════
            [
                'name'        => 'Gray-Box Web Application Testing',
                'description' => 'Partial-knowledge web application assessment combining unauthenticated recon with authenticated session, access-control, injection, and API testing. Assumes tester holds at least one valid user account.',
                'color_key'   => 'graybox',
                'phases'      => [
                    // ── Reconnaissance ─────────────────────────────────────
                    [
                        'phase'      => 'Reconnaissance',
                        'label'      => 'Subdomain Enumeration',
                        'skill'      => 'performing-subdomain-enumeration-with-subfinder',
                        'edge_label' => 'Attack Surface',
                    ],
                    [
                        'phase'      => 'Reconnaissance',
                        'label'      => 'Security Headers Audit',
                        'skill'      => 'performing-security-headers-audit',
                        'edge_label' => 'Header Gaps',
                    ],
                    [
                        'phase'      => 'Reconnaissance',
                        'label'      => 'SSL/TLS Assessment',
                        'skill'      => 'performing-ssl-tls-security-assessment',
                        'edge_label' => 'TLS Findings',
                    ],
                    // ── Authenticated Discovery ─────────────────────────────
                    [
                        'phase'      => 'Authenticated Discovery',
                        'label'      => 'Authenticated Vulnerability Scan',
                        'skill'      => 'performing-authenticated-vulnerability-scan',
                        'edge_label' => 'Vuln Map',
                    ],
                    [
                        'phase'      => 'Authenticated Discovery',
                        'label'      => 'Web App Scan (Nikto)',
                        'skill'      => 'performing-web-application-scanning-with-nikto',
                        'edge_label' => 'Misconfig Hits',
                    ],
                    // ── Authentication & Session ────────────────────────────
                    [
                        'phase'      => 'Authentication & Session',
                        'label'      => 'JWT Token Security Testing',
                        'skill'      => 'testing-jwt-token-security',
                        'edge_label' => 'JWT Flaws',
                    ],
                    [
                        'phase'      => 'Authentication & Session',
                        'label'      => 'JWT Algorithm Confusion',
                        'skill'      => 'exploiting-jwt-algorithm-confusion-attack',
                        'edge_label' => 'Token Forgery',
                    ],
                    [
                        'phase'      => 'Authentication & Session',
                        'label'      => 'OAuth2 Implementation Flaws',
                        'skill'      => 'testing-oauth2-implementation-flaws',
                        'edge_label' => 'OAuth Bypass',
                    ],
                    // ── Injection ──────────────────────────────────────────
                    [
                        'phase'      => 'Injection',
                        'label'      => 'SQL Injection Testing',
                        'skill'      => 'SQL Injection Testing',
                        'edge_label' => 'SQLi Results',
                    ],
                    [
                        'phase'      => 'Injection',
                        'label'      => 'XSS Testing',
                        'skill'      => 'Cross-Site Scripting (XSS) Testing',
                        'edge_label' => 'XSS Vectors',
                    ],
                    [
                        'phase'      => 'Injection',
                        'label'      => 'XXE Injection Testing',
                        'skill'      => 'testing-for-xxe-injection-vulnerabilities',
                        'edge_label' => 'XXE Path',
                    ],
                    [
                        'phase'      => 'Injection',
                        'label'      => 'SSRF Exploitation',
                        'skill'      => 'performing-ssrf-vulnerability-exploitation',
                        'edge_label' => 'SSRF Access',
                    ],
                    // ── Access Control ─────────────────────────────────────
                    [
                        'phase'      => 'Access Control',
                        'label'      => 'IDOR Exploitation',
                        'skill'      => 'exploiting-idor-vulnerabilities',
                        'edge_label' => 'IDOR Evidence',
                    ],
                    [
                        'phase'      => 'Access Control',
                        'label'      => 'Broken Access Control',
                        'skill'      => 'testing-for-broken-access-control',
                        'edge_label' => 'BAC Findings',
                    ],
                    [
                        'phase'      => 'Access Control',
                        'label'      => 'CORS Misconfiguration',
                        'skill'      => 'testing-cors-misconfiguration',
                        'edge_label' => 'CORS Risk',
                    ],
                    // ── API & Logic ────────────────────────────────────────
                    [
                        'phase'      => 'API Security',
                        'label'      => 'API OWASP Top 10',
                        'skill'      => 'testing-api-security-with-owasp-top-10',
                        'edge_label' => 'API Vulns',
                    ],
                    [
                        'phase'      => 'Business Logic',
                        'label'      => 'Business Logic Testing',
                        'skill'      => 'testing-for-business-logic-vulnerabilities',
                        'edge_label' => 'Logic Flaws',
                    ],
                    [
                        'phase'      => 'Evasion',
                        'label'      => 'WAF Bypass Testing',
                        'skill'      => 'performing-web-application-firewall-bypass',
                        'edge_label' => 'WAF Bypassed',
                    ],
                    // ── Reporting ──────────────────────────────────────────
                    [
                        'phase'      => 'Reporting',
                        'label'      => 'Web Application Pentest Report',
                        'skill'      => 'Web Application Penetration Test',
                        'edge_label' => 'Full Report',
                    ],
                ],
            ],

            // ══════════════════════════════════════════════════════════════════
            // WHITE-BOX WEB APPLICATION TESTING
            // Tester has full knowledge: source code, architecture, all credentials
            // ══════════════════════════════════════════════════════════════════
            [
                'name'        => 'White-Box Web Application Testing',
                'description' => 'Full-knowledge web application security assessment: static analysis, secret and dependency scanning, cryptographic review, authenticated functional testing, and deep API/logic validation with complete access to source code and infrastructure.',
                'color_key'   => 'whitebox',
                'phases'      => [
                    // ── Static Analysis ────────────────────────────────────
                    [
                        'phase'      => 'Static Analysis',
                        'label'      => 'SAST Pipeline Integration',
                        'skill'      => 'integrating-sast-into-github-actions-pipeline',
                        'edge_label' => 'Code Flaws',
                    ],
                    [
                        'phase'      => 'Static Analysis',
                        'label'      => 'Semgrep Custom Rules',
                        'skill'      => 'implementing-semgrep-for-custom-sast-rules',
                        'edge_label' => 'Pattern Hits',
                    ],
                    [
                        'phase'      => 'Static Analysis',
                        'label'      => 'Secret Scanning (Gitleaks)',
                        'skill'      => 'implementing-secret-scanning-with-gitleaks',
                        'edge_label' => 'Secrets Found',
                    ],
                    // ── Supply Chain ───────────────────────────────────────
                    [
                        'phase'      => 'Supply Chain',
                        'label'      => 'SCA Dependency Scanning (Snyk)',
                        'skill'      => 'performing-sca-dependency-scanning-with-snyk',
                        'edge_label' => 'CVE List',
                    ],
                    [
                        'phase'      => 'Supply Chain',
                        'label'      => 'SBOM Vulnerability Analysis',
                        'skill'      => 'analyzing-sbom-for-supply-chain-vulnerabilities',
                        'edge_label' => 'Component Risk',
                    ],
                    // ── Configuration & Crypto ─────────────────────────────
                    [
                        'phase'      => 'Configuration',
                        'label'      => 'Cryptographic Audit',
                        'skill'      => 'performing-cryptographic-audit-of-application',
                        'edge_label' => 'Crypto Gaps',
                    ],
                    [
                        'phase'      => 'Configuration',
                        'label'      => 'Security Headers Audit',
                        'skill'      => 'performing-security-headers-audit',
                        'edge_label' => 'Header Gaps',
                    ],
                    [
                        'phase'      => 'Configuration',
                        'label'      => 'SSL/TLS Assessment',
                        'skill'      => 'performing-ssl-tls-security-assessment',
                        'edge_label' => 'TLS Findings',
                    ],
                    // ── Authenticated Testing ──────────────────────────────
                    [
                        'phase'      => 'Authenticated Testing',
                        'label'      => 'Authenticated Vulnerability Scan',
                        'skill'      => 'performing-authenticated-vulnerability-scan',
                        'edge_label' => 'Vuln Map',
                    ],
                    [
                        'phase'      => 'Authentication & Session',
                        'label'      => 'JWT Token Security Testing',
                        'skill'      => 'testing-jwt-token-security',
                        'edge_label' => 'JWT Flaws',
                    ],
                    [
                        'phase'      => 'Authentication & Session',
                        'label'      => 'OAuth2 Implementation Flaws',
                        'skill'      => 'testing-oauth2-implementation-flaws',
                        'edge_label' => 'OAuth Bypass',
                    ],
                    // ── Deep Injection ─────────────────────────────────────
                    [
                        'phase'      => 'Injection',
                        'label'      => 'SQL Injection Testing',
                        'skill'      => 'SQL Injection Testing',
                        'edge_label' => 'SQLi Results',
                    ],
                    [
                        'phase'      => 'Injection',
                        'label'      => 'Second-Order SQL Injection',
                        'skill'      => 'performing-second-order-sql-injection',
                        'edge_label' => 'Stored SQLi',
                    ],
                    [
                        'phase'      => 'Injection',
                        'label'      => 'Insecure Deserialization',
                        'skill'      => 'exploiting-insecure-deserialization',
                        'edge_label' => 'RCE Path',
                    ],
                    [
                        'phase'      => 'Injection',
                        'label'      => 'Blind SSRF Exploitation',
                        'skill'      => 'performing-blind-ssrf-exploitation',
                        'edge_label' => 'SSRF Chain',
                    ],
                    // ── Access Control & API ───────────────────────────────
                    [
                        'phase'      => 'Access Control',
                        'label'      => 'Broken Access Control',
                        'skill'      => 'testing-for-broken-access-control',
                        'edge_label' => 'BAC Findings',
                    ],
                    [
                        'phase'      => 'Access Control',
                        'label'      => 'CSRF Attack Simulation',
                        'skill'      => 'performing-csrf-attack-simulation',
                        'edge_label' => 'CSRF Vectors',
                    ],
                    [
                        'phase'      => 'API Security',
                        'label'      => 'API Security Testing (Postman)',
                        'skill'      => 'performing-api-security-testing-with-postman',
                        'edge_label' => 'API Vulns',
                    ],
                    [
                        'phase'      => 'API Security',
                        'label'      => 'Mass Assignment Exploitation',
                        'skill'      => 'exploiting-mass-assignment-in-rest-apis',
                        'edge_label' => 'Privilege Escalated',
                    ],
                    // ── Reporting ──────────────────────────────────────────
                    [
                        'phase'      => 'Reporting',
                        'label'      => 'Web Application Pentest Report',
                        'skill'      => 'Web Application Penetration Test',
                        'edge_label' => 'Full Report',
                    ],
                ],
            ],
        ];
    }
}
