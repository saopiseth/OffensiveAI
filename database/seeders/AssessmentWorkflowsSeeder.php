<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;

class AssessmentWorkflowsSeeder extends Seeder
{
    // ── Layout constants ──────────────────────────────────────────────────────
    private const X_START  = 80;
    private const X_STEP   = 300;
    private const Y_BASE   = 180;
    private const Y_STEP   = 280;
    private const ROW_SIZE = 4;

    // ── Colour palette per workflow ───────────────────────────────────────────
    private const COLORS = [
        'network'    => ['edge' => '#3b82f6', 'input' => '#1d4ed8', 'output' => '#1e40af'],
        'webapp'     => ['edge' => '#8b5cf6', 'input' => '#6d28d9', 'output' => '#5b21b6'],
        'redteam'    => ['edge' => '#ef4444', 'input' => '#b91c1c', 'output' => '#991b1b'],
        'infogather' => ['edge' => '#06b6d4', 'input' => '#0891b2', 'output' => '#0e7490'],
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $admin = User::role('admin')->first() ?? User::first();

        // 1. Wipe every existing workflow
        $deleted = Workflow::count();
        Workflow::query()->delete();
        $this->command->warn("  Deleted {$deleted} existing workflow(s).");
        $this->command->newLine();

        // 2. Pre-load skill lookup (name → id)
        $skillMap = Skill::pluck('id', 'name')->all();

        // 3. Seed each assessment workflow
        foreach ($this->definitions() as $def) {
            $this->seedWorkflow($def, $skillMap, $admin->id);
        }
    }

    // ── Workflow builder ──────────────────────────────────────────────────────

    private function seedWorkflow(array $def, array $skillMap, string $userId): void
    {
        $color  = self::COLORS[$def['color_key']];
        $ts     = microtime(true) * 1000;
        $nodes  = [];
        $edges  = [];

        // Input node
        $inputId = "wf_{$ts}_input";
        $nodes[] = $this->inputNode($inputId, 'Engagement Start', self::X_START, self::Y_BASE, $color['input']);
        $prevId  = $inputId;

        // Skill nodes
        foreach ($def['phases'] as $i => $phase) {
            $skillId   = $skillMap[$phase['skill']] ?? null;
            $nodeId    = "wf_{$ts}_n{$i}";
            [$x, $y]   = $this->snakePos($i);

            $node            = $this->skillNode($nodeId, $phase['label'], $skillId, $phase['skill'], $phase['phase']);
            $node['position'] = ['x' => $x, 'y' => $y];
            $nodes[]         = $node;
            $edges[]         = $this->edge("{$prevId}__{$nodeId}", $prevId, $nodeId, $phase['edge_label'] ?? '', $color['edge']);

            $prevId = $nodeId;
        }

        // Output node
        $last    = end($nodes);
        $outId   = "wf_{$ts}_output";
        $outX    = $last['position']['x'] + self::X_STEP;
        $outY    = $last['position']['y'];
        $nodes[] = $this->outputNode($outId, 'Report Delivered', $outX, $outY, $color['output']);
        $edges[] = $this->edge("{$prevId}__{$outId}", $prevId, $outId, 'Findings', $color['edge']);

        $workflow = Workflow::create([
            'created_by'  => $userId,
            'name'        => $def['name'],
            'description' => $def['description'],
            'status'      => 'published',
            'is_active'   => true,
            'graph_data'  => ['nodes' => $nodes, 'edges' => $edges],
        ]);

        $mapped   = collect($def['phases'])->filter(fn($p) => isset($skillMap[$p['skill']]))->count();
        $unmapped = count($def['phases']) - $mapped;

        $this->command->info("  ✓ {$workflow->name}");
        $this->command->line("    nodes: " . count($nodes) . "  |  skills mapped: {$mapped}  |  unmapped: {$unmapped}");
        $this->command->newLine();
    }

    // ── Node factories ────────────────────────────────────────────────────────

    private function inputNode(string $id, string $label, int $x, int $y, string $color): array
    {
        return [
            'id'       => $id,
            'type'     => 'custom',
            'position' => ['x' => $x, 'y' => $y],
            'data'     => ['type' => 'input', 'label' => $label, 'color' => $color],
        ];
    }

    private function outputNode(string $id, string $label, int $x, int $y, string $color): array
    {
        return [
            'id'       => $id,
            'type'     => 'custom',
            'position' => ['x' => $x, 'y' => $y],
            'data'     => ['type' => 'output', 'label' => $label, 'color' => $color],
        ];
    }

    private function skillNode(string $id, string $label, ?string $skillId, string $skillName, string $phase): array
    {
        return [
            'id'       => $id,
            'type'     => 'custom',
            'position' => ['x' => 0, 'y' => 0], // overwritten below via snakePos
            'data'     => [
                'type'       => 'skill',
                'label'      => $label,
                'skill_id'   => $skillId,
                'skill_name' => $skillName,
                'phase'      => $phase,
            ],
        ];
    }

    private function edge(string $id, string $src, string $tgt, string $label, string $color): array
    {
        $e = [
            'id'        => $id,
            'source'    => $src,
            'target'    => $tgt,
            'style'     => ['stroke' => $color, 'strokeWidth' => 2],
            'markerEnd' => ['type' => 'arrowclosed', 'color' => $color],
        ];
        if ($label) $e['label'] = $label;
        return $e;
    }

    // ── Snake layout ──────────────────────────────────────────────────────────

    private function snakePos(int $i): array
    {
        $row = (int) floor($i / self::ROW_SIZE);
        $col = $i % self::ROW_SIZE;
        $y   = self::Y_BASE + ($row + 1) * self::Y_STEP;

        if ($row % 2 === 1) $col = self::ROW_SIZE - 1 - $col;

        $x = self::X_START + $col * self::X_STEP;
        return [$x, $y];
    }

    // ── Workflow definitions ──────────────────────────────────────────────────

    private function definitions(): array
    {
        return [

            // ══════════════════════════════════════════════════════════════════
            // 1. NETWORK PENETRATION TESTING
            // ══════════════════════════════════════════════════════════════════
            [
                'name'        => 'Network Penetration Testing',
                'description' => 'End-to-end network pentest: external recon → scanning → exploitation → lateral movement → reporting. Follows PTES methodology.',
                'color_key'   => 'network',
                'phases'      => [
                    [
                        'phase'      => 'Reconnaissance',
                        'label'      => 'External OSINT Recon',
                        'skill'      => 'conducting-external-reconnaissance-with-osint',
                        'edge_label' => 'Scope Confirmed',
                    ],
                    [
                        'phase'      => 'Reconnaissance',
                        'label'      => 'DNS Enumeration & Zone Transfer',
                        'skill'      => 'performing-dns-enumeration-and-zone-transfer',
                        'edge_label' => 'DNS Map',
                    ],
                    [
                        'phase'      => 'Scanning',
                        'label'      => 'Network Port Scanning',
                        'skill'      => 'Network Port Scanner',
                        'edge_label' => 'Host List',
                    ],
                    [
                        'phase'      => 'Scanning',
                        'label'      => 'Advanced Nmap Scan',
                        'skill'      => 'scanning-network-with-nmap-advanced',
                        'edge_label' => 'Service Map',
                    ],
                    [
                        'phase'      => 'Scanning',
                        'label'      => 'Vulnerability Scanning (Nessus)',
                        'skill'      => 'performing-vulnerability-scanning-with-nessus',
                        'edge_label' => 'Vuln List',
                    ],
                    [
                        'phase'      => 'Scanning',
                        'label'      => 'SSL/TLS Security Assessment',
                        'skill'      => 'performing-ssl-tls-security-assessment',
                        'edge_label' => 'TLS Findings',
                    ],
                    [
                        'phase'      => 'Exploitation',
                        'label'      => 'SMB Exploitation (Metasploit)',
                        'skill'      => 'exploiting-smb-vulnerabilities-with-metasploit',
                        'edge_label' => 'Access Gained',
                    ],
                    [
                        'phase'      => 'Exploitation',
                        'label'      => 'Credential Attack',
                        'skill'      => 'Wordlist & Credential Attack Assistant',
                        'edge_label' => 'Creds Harvested',
                    ],
                    [
                        'phase'      => 'Post-Exploitation',
                        'label'      => 'Privilege Escalation',
                        'skill'      => 'performing-privilege-escalation-assessment',
                        'edge_label' => 'Elevated Access',
                    ],
                    [
                        'phase'      => 'Post-Exploitation',
                        'label'      => 'Lateral Movement Detection',
                        'skill'      => 'detecting-lateral-movement-in-network',
                        'edge_label' => 'Movement Path',
                    ],
                    [
                        'phase'      => 'Analysis',
                        'label'      => 'Network Traffic Analysis',
                        'skill'      => 'analyzing-network-traffic-with-wireshark',
                        'edge_label' => 'Traffic Evidence',
                    ],
                    [
                        'phase'      => 'Analysis',
                        'label'      => 'Network Flow Analysis',
                        'skill'      => 'analyzing-network-flow-data-with-netflow',
                        'edge_label' => 'Flow Data',
                    ],
                    [
                        'phase'      => 'Reporting',
                        'label'      => 'Penetration Test Report',
                        'skill'      => 'Penetration Test Advisor',
                        'edge_label' => 'Findings',
                    ],
                ],
            ],

            // ══════════════════════════════════════════════════════════════════
            // 2. WEB APPLICATION PENETRATION TESTING
            // ══════════════════════════════════════════════════════════════════
            [
                'name'        => 'Web Application Penetration Testing',
                'description' => 'Full OWASP-aligned web application pentest: recon → injection → access control → API → business logic → reporting.',
                'color_key'   => 'webapp',
                'phases'      => [
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
                        'phase'      => 'Injection',
                        'label'      => 'SQL Injection Testing',
                        'skill'      => 'SQL Injection Testing',
                        'edge_label' => 'SQLi Results',
                    ],
                    [
                        'phase'      => 'Injection',
                        'label'      => 'SQL Injection with SQLMap',
                        'skill'      => 'exploiting-sql-injection-with-sqlmap',
                        'edge_label' => 'DB Extracted',
                    ],
                    [
                        'phase'      => 'Injection',
                        'label'      => 'XSS Testing',
                        'skill'      => 'Cross-Site Scripting (XSS) Testing',
                        'edge_label' => 'XSS Payloads',
                    ],
                    [
                        'phase'      => 'Injection',
                        'label'      => 'XSS with Burp Suite',
                        'skill'      => 'testing-for-xss-vulnerabilities-with-burpsuite',
                        'edge_label' => 'XSS Confirmed',
                    ],
                    [
                        'phase'      => 'Access Control',
                        'label'      => 'Broken Access Control',
                        'skill'      => 'testing-for-broken-access-control',
                        'edge_label' => 'BAC Findings',
                    ],
                    [
                        'phase'      => 'Access Control',
                        'label'      => 'IDOR Exploitation',
                        'skill'      => 'exploiting-idor-vulnerabilities',
                        'edge_label' => 'IDOR Evidence',
                    ],
                    [
                        'phase'      => 'Authentication',
                        'label'      => 'JWT Token Security',
                        'skill'      => 'testing-for-json-web-token-vulnerabilities',
                        'edge_label' => 'JWT Flaws',
                    ],
                    [
                        'phase'      => 'Authentication',
                        'label'      => 'OAuth Misconfiguration',
                        'skill'      => 'exploiting-oauth-misconfiguration',
                        'edge_label' => 'OAuth Bypass',
                    ],
                    [
                        'phase'      => 'Advanced',
                        'label'      => 'Server-Side Request Forgery',
                        'skill'      => 'exploiting-server-side-request-forgery',
                        'edge_label' => 'SSRF Path',
                    ],
                    [
                        'phase'      => 'Advanced',
                        'label'      => 'Template Injection (SSTI)',
                        'skill'      => 'exploiting-template-injection-vulnerabilities',
                        'edge_label' => 'SSTI RCE',
                    ],
                    [
                        'phase'      => 'API',
                        'label'      => 'API Security (OWASP Top 10)',
                        'skill'      => 'testing-api-security-with-owasp-top-10',
                        'edge_label' => 'API Vulns',
                    ],
                    [
                        'phase'      => 'API',
                        'label'      => 'API Secrets Scanner',
                        'skill'      => 'API Key & Secrets Scanner',
                        'edge_label' => 'Secrets Found',
                    ],
                    [
                        'phase'      => 'Logic',
                        'label'      => 'Business Logic Testing',
                        'skill'      => 'testing-for-business-logic-vulnerabilities',
                        'edge_label' => 'Logic Flaws',
                    ],
                    [
                        'phase'      => 'Logic',
                        'label'      => 'WAF Bypass Testing',
                        'skill'      => 'performing-web-application-firewall-bypass',
                        'edge_label' => 'WAF Bypassed',
                    ],
                    [
                        'phase'      => 'Reporting',
                        'label'      => 'Web Application Pentest Report',
                        'skill'      => 'Web Application Penetration Test',
                        'edge_label' => 'Full Report',
                    ],
                ],
            ],

            // ══════════════════════════════════════════════════════════════════
            // 3. INFORMATION GATHERING
            // ══════════════════════════════════════════════════════════════════
            [
                'name'        => 'Information Gathering',
                'description' => 'Comprehensive passive and active information gathering: OSINT → DNS/subdomain recon → infrastructure intel → dark-web exposure monitoring → threat actor profiling → consolidated threat intelligence report.',
                'color_key'   => 'infogather',
                'phases'      => [
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
                    [
                        'phase'      => 'Reporting',
                        'label'      => 'Threat Intelligence Report',
                        'skill'      => 'Threat Intelligence Report',
                        'edge_label' => 'Final Report',
                    ],
                ],
            ],

            // ══════════════════════════════════════════════════════════════════
            // 4. RED TEAMING ASSESSMENT
            // ══════════════════════════════════════════════════════════════════
            [
                'name'        => 'Red Teaming Assessment',
                'description' => 'Full-scope adversarial simulation: threat intel → initial access → C2 → AD exploitation → lateral movement → exfiltration → purple team validation.',
                'color_key'   => 'redteam',
                'phases'      => [
                    [
                        'phase'      => 'Planning',
                        'label'      => 'Threat Actor TTP Analysis',
                        'skill'      => 'analyzing-threat-actor-ttps-with-mitre-attack',
                        'edge_label' => 'TTPs Mapped',
                    ],
                    [
                        'phase'      => 'Planning',
                        'label'      => 'MITRE ATT&CK Mapping',
                        'skill'      => 'mapping-mitre-attack-techniques',
                        'edge_label' => 'Attack Plan',
                    ],
                    [
                        'phase'      => 'Planning',
                        'label'      => 'Red Team Engagement Planning',
                        'skill'      => 'executing-red-team-engagement-planning',
                        'edge_label' => 'ROE Signed',
                    ],
                    [
                        'phase'      => 'Reconnaissance',
                        'label'      => 'OSINT Intelligence Gathering',
                        'skill'      => 'performing-open-source-intelligence-gathering',
                        'edge_label' => 'Intel Report',
                    ],
                    [
                        'phase'      => 'Initial Access',
                        'label'      => 'Spearphishing Simulation',
                        'skill'      => 'conducting-spearphishing-simulation-campaign',
                        'edge_label' => 'Foothold',
                    ],
                    [
                        'phase'      => 'Initial Access',
                        'label'      => 'Initial Access via EvilGinx3',
                        'skill'      => 'performing-initial-access-with-evilginx3',
                        'edge_label' => 'Creds Captured',
                    ],
                    [
                        'phase'      => 'Command & Control',
                        'label'      => 'C2 Infrastructure (Sliver)',
                        'skill'      => 'building-c2-infrastructure-with-sliver-framework',
                        'edge_label' => 'C2 Active',
                    ],
                    [
                        'phase'      => 'Discovery',
                        'label'      => 'AD Recon with BloodHound',
                        'skill'      => 'conducting-internal-reconnaissance-with-bloodhound-ce',
                        'edge_label' => 'AD Graph',
                    ],
                    [
                        'phase'      => 'Privilege Escalation',
                        'label'      => 'Kerberoasting Attack',
                        'skill'      => 'exploiting-kerberoasting-with-impacket',
                        'edge_label' => 'Ticket Cracked',
                    ],
                    [
                        'phase'      => 'Privilege Escalation',
                        'label'      => 'AD Certificate Services ESC1',
                        'skill'      => 'exploiting-active-directory-certificate-services-esc1',
                        'edge_label' => 'DA Achieved',
                    ],
                    [
                        'phase'      => 'Credential Access',
                        'label'      => 'Credential Dumping (LaZagne)',
                        'skill'      => 'performing-credential-access-with-lazagne',
                        'edge_label' => 'Creds Dumped',
                    ],
                    [
                        'phase'      => 'Credential Access',
                        'label'      => 'DCSync Attack',
                        'skill'      => 'conducting-domain-persistence-with-dcsync',
                        'edge_label' => 'Hashes Extracted',
                    ],
                    [
                        'phase'      => 'Lateral Movement',
                        'label'      => 'Lateral Movement (WMIExec)',
                        'skill'      => 'performing-lateral-movement-with-wmiexec',
                        'edge_label' => 'Pivoted',
                    ],
                    [
                        'phase'      => 'Lateral Movement',
                        'label'      => 'AD Forest Trust Attack',
                        'skill'      => 'performing-active-directory-forest-trust-attack',
                        'edge_label' => 'Cross-Forest',
                    ],
                    [
                        'phase'      => 'Impact',
                        'label'      => 'Privilege Escalation on Linux',
                        'skill'      => 'performing-privilege-escalation-on-linux',
                        'edge_label' => 'Root Obtained',
                    ],
                    [
                        'phase'      => 'Validation',
                        'label'      => 'Purple Team Atomic Testing',
                        'skill'      => 'performing-purple-team-atomic-testing',
                        'edge_label' => 'Detections Mapped',
                    ],
                    [
                        'phase'      => 'Reporting',
                        'label'      => 'Red Team Final Report',
                        'skill'      => 'Penetration Test Advisor',
                        'edge_label' => 'Full Report',
                    ],
                ],
            ],
        ];
    }
}
