<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\SkillStep;
use App\Models\User;
use Illuminate\Database\Seeder;

class NetworkPenetrationSkillsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first() ?? User::first();

        foreach ($this->skills() as $skillData) {
            $steps = $skillData['steps'];
            unset($skillData['steps']);

            $skill = Skill::updateOrCreate(
                ['name' => $skillData['name']],
                array_merge($skillData, [
                    'created_by' => $admin->id,
                    'is_active'  => true,
                    'version'    => '1.0.0',
                ])
            );

            foreach ($steps as $order => $step) {
                SkillStep::updateOrCreate(
                    ['skill_id' => $skill->id, 'execution_order' => $order + 1],
                    array_merge($step, [
                        'execution_order' => $order + 1,
                        'ai_provider'     => 'openai',
                        'model'           => 'gpt-4o',
                        'temperature'     => 0.2,
                        'max_tokens'      => 4096,
                        'is_active'       => true,
                    ])
                );
            }

            $this->command->info("  ✓ {$skill->name}  [{$skill->category}]  " . count($steps) . ' steps');
        }
    }

    private function skills(): array
    {
        return [
            // ── 1. Detect Insecure Port Usage ─────────────────────────────────────
            [
                'name'        => 'Detect Insecure Port Usage',
                'description' => 'Identify services running on insecure or risky ports and highlight potential security issues.',
                'category'    => 'network-penetration',
                'tags'        => ['network', 'ports', 'nmap', 'nessus', 'openvas', 'port-scanning', 'service-enumeration', 'network-security'],
                'steps'       => [
                    [
                        'name'            => 'Insecure Port Analysis',
                        'description'     => 'Parse scan output and identify insecure or high-risk open ports with remediation advice.',
                        'system_prompt'   => 'You are a cybersecurity analyst. Analyze the provided scan result and identify any insecure or high-risk ports. Focus on commonly exploited ports such as FTP (21), Telnet (23), SMB (445), RDP (3389), and any unnecessary open ports. Provide clear findings and remediation advice.',
                        'prompt_template' => <<<'PROMPT'
Analyze the following scan result and identify insecure ports:

{{target_content}}

Return a JSON object that strictly matches this schema:

{
  "findings": [
    {
      "port": <number>,
      "service": "<string>",
      "risk_level": "<Low | Medium | High | Critical>",
      "description": "<string — why this port/service is risky>",
      "recommendation": "<string — specific remediation steps>"
    }
  ]
}

Rules:
- Include only ports that represent a genuine security risk. If no insecure configuration is found, return an empty findings array.
- Assign risk_level using the following guidance:
    Critical : Unauthenticated remote access or known critical CVEs (e.g. MS17-010 on 445)
    High     : Cleartext credential protocols or broadly exploitable services (Telnet 23, FTP 21, rlogin 513)
    Medium   : Services with limited exposure or requiring preconditions (RDP 3389 exposed externally, VNC 5900)
    Low      : Informational or minor risk (e.g. SNMP v1/v2c on 161, HTTPS on non-standard port)
- base all findings strictly on the provided scan output; do not fabricate data.
- Always include at least one concrete remediation step per finding.
- Output valid JSON only — no markdown, no code fences, no extra commentary.
PROMPT
                        ,
                        'input_schema'    => [
                            'target_content' => 'string',
                        ],
                        'output_schema'   => [
                            'findings' => [
                                [
                                    'port'           => 'number',
                                    'service'        => 'string',
                                    'risk_level'     => 'Low | Medium | High | Critical',
                                    'description'    => 'string',
                                    'recommendation' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ── 2. Detect Weak SSL/TLS Configuration ──────────────────────────────
            [
                'name'        => 'Detect Weak SSL/TLS Configuration',
                'description' => 'Analyze SSL/TLS scan results and detect weak ciphers, outdated protocols, and misconfigurations.',
                'category'    => 'network-penetration',
                'tags'        => ['ssl', 'tls', 'cipher', 'sslyze', 'testssl', 'nmap', 'protocol', 'pki', 'certificate', 'network-security'],
                'steps'       => [
                    [
                        'name'            => 'SSL/TLS Weakness Detection',
                        'description'     => 'Parse SSL/TLS scan output and identify weak ciphers, deprecated protocols, and misconfigurations.',
                        'system_prompt'   => 'You are a security expert specializing in SSL/TLS. Analyze the provided scan output and identify weak ciphers, deprecated protocols (SSLv2, SSLv3, TLS 1.0, TLS 1.1), and insecure configurations. Provide clear explanations and remediation steps.',
                        'prompt_template' => <<<'PROMPT'
Analyze the following SSL/TLS scan result:

{{target_content}}

Return a JSON object that strictly matches this schema:

{
  "issues": [
    {
      "type": "<Weak Cipher | Deprecated Protocol | Misconfiguration>",
      "severity": "<Low | Medium | High | Critical>",
      "description": "<string — what the issue is and why it matters>",
      "evidence": "<string — verbatim excerpt or specific detail from the scan output>",
      "recommendation": "<string — specific remediation steps>"
    }
  ]
}

Rules:
- Include only genuine SSL/TLS weaknesses. If no insecure configuration is found, return an empty issues array.
- Assign severity using the following guidance:
    Critical : Protocol/cipher allows practical decryption or MITM without preconditions (SSLv2, EXPORT ciphers, DROWN, POODLE)
    High     : Deprecated protocols still accepted (SSLv3, TLS 1.0, TLS 1.1) or RC4/DES/3DES ciphers
    Medium   : Weak key sizes, self-signed certificates, missing HSTS, or missing certificate pinning
    Low      : Minor misconfigurations such as missing OCSP stapling or suboptimal cipher ordering
- Map each issue to exactly one of the three types: Weak Cipher, Deprecated Protocol, or Misconfiguration.
- Set evidence to a verbatim line or value from the scan output that supports the finding.
- Base all findings strictly on the provided scan output; do not fabricate data.
- Always include at least one concrete remediation step per issue.
- Output valid JSON only — no markdown, no code fences, no extra commentary.
PROMPT
                        ,
                        'input_schema'    => [
                            'target_content' => 'string',
                        ],
                        'output_schema'   => [
                            'issues' => [
                                [
                                    'type'           => 'Weak Cipher | Deprecated Protocol | Misconfiguration',
                                    'severity'       => 'Low | Medium | High | Critical',
                                    'description'    => 'string',
                                    'evidence'       => 'string',
                                    'recommendation' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
