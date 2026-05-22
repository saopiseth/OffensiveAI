<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\SkillStep;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;

class CybersecuritySkillsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@redto.app')->first();
        $adminId = $admin?->id;

        $skills = $this->skillDefinitions();
        $createdSkills = [];

        foreach ($skills as $def) {
            $skill = Skill::firstOrCreate(
                ['name' => $def['name']],
                [
                    'created_by'  => $adminId,
                    'description' => $def['description'],
                    'version'     => '1.0.0',
                    'category'    => $def['category'],
                    'is_active'   => true,
                    'tags'        => $def['tags'],
                ]
            );

            if ($skill->wasRecentlyCreated) {
                foreach ($def['steps'] as $i => $step) {
                    SkillStep::create([
                        'skill_id'        => $skill->id,
                        'name'            => $step['name'],
                        'description'     => $step['description'],
                        'prompt_template' => $step['prompt_template'],
                        'input_schema'    => $step['input_schema'] ?? null,
                        'output_schema'   => $step['output_schema'] ?? null,
                        'execution_order' => $i + 1,
                        'ai_provider'     => 'claude',
                        'model'           => 'claude-opus-4-7',
                        'temperature'     => $step['temperature'] ?? 0.3,
                        'max_tokens'      => $step['max_tokens'] ?? 4096,
                        'is_active'       => true,
                    ]);
                }
            }

            $createdSkills[$def['name']] = $skill;
        }

        $this->seedWorkflows($adminId, $createdSkills);

        $this->command->info(sprintf(
            '✅ Cybersecurity skills seeded: %d skills · %d steps · 5 workflows',
            count($createdSkills),
            array_sum(array_map(fn($s) => count($s['steps']), $this->skillDefinitions()))
        ));
    }

    // ─── Workflow Templates ───────────────────────────────────────────────────

    private function seedWorkflows(?string $adminId, array $skills): void
    {
        $workflows = [
            [
                'name'        => 'Incident Response Pipeline',
                'description' => 'Full incident response workflow: triage → threat intel enrichment → executive report. Maps to NIST CSF Respond function.',
                'skill_names' => [
                    'Incident Response Triage',
                    'Threat Intelligence Report',
                    'Malware Behavioral Analysis',
                ],
            ],
            [
                'name'        => 'Red Team Assessment Pipeline',
                'description' => 'Offensive security workflow: OSINT reconnaissance → web application pentest → findings report.',
                'skill_names' => [
                    'OSINT Target Profiling',
                    'Web Application Penetration Test',
                    'SQL Injection Detection & Analysis',
                ],
            ],
            [
                'name'        => 'Threat Hunting Campaign',
                'description' => 'Proactive threat hunting: network anomaly detection → malware analysis → threat intelligence correlation.',
                'skill_names' => [
                    'Network Traffic Anomaly Detection',
                    'Malware Behavioral Analysis',
                    'Threat Intelligence Report',
                ],
            ],
            [
                'name'        => 'Cloud Security Audit',
                'description' => 'Cloud posture assessment: configuration review → AD privilege analysis → remediation roadmap.',
                'skill_names' => [
                    'Cloud Security Posture Review',
                    'AD ACL Abuse Analysis',
                ],
            ],
            [
                'name'        => 'Phishing Incident Response',
                'description' => 'Email-based threat response: phishing analysis → OSINT profiling → incident triage.',
                'skill_names' => [
                    'Phishing Email Analysis',
                    'OSINT Target Profiling',
                    'Incident Response Triage',
                ],
            ],
        ];

        foreach ($workflows as $wf) {
            $skillIds = array_filter(array_map(
                fn($name) => $skills[$name] ?? null,
                $wf['skill_names']
            ));

            if (empty($skillIds)) {
                continue;
            }

            $nodes = [];
            $edges = [];
            $xPos  = 100;

            foreach (array_values($skillIds) as $idx => $skill) {
                $nodeId  = 'node-' . ($idx + 1);
                $nodes[] = [
                    'id'       => $nodeId,
                    'type'     => 'skillNode',
                    'position' => ['x' => $xPos, 'y' => 200],
                    'data'     => [
                        'skillId' => $skill->id,
                        'label'   => $skill->name,
                        'category'=> $skill->category,
                    ],
                ];

                if ($idx > 0) {
                    $prevNodeId = 'node-' . $idx;
                    $edges[]    = [
                        'id'     => "e{$prevNodeId}-{$nodeId}",
                        'source' => $prevNodeId,
                        'target' => $nodeId,
                        'type'   => 'smoothstep',
                        'animated' => true,
                    ];
                }

                $xPos += 320;
            }

            Workflow::firstOrCreate(
                ['name' => $wf['name']],
                [
                    'created_by'  => $adminId,
                    'description' => $wf['description'],
                    'graph_data'  => ['nodes' => $nodes, 'edges' => $edges],
                    'is_active'   => true,
                    'status'      => 'published',
                ]
            );
        }
    }

    // ─── Skill Definitions ────────────────────────────────────────────────────

    private function skillDefinitions(): array
    {
        return [

            // ── 1. AD ACL Abuse Analysis ────────────────────────────────────
            [
                'name'        => 'AD ACL Abuse Analysis',
                'description' => 'Detect dangerous ACL misconfigurations in Active Directory. Identifies GenericAll, WriteDACL, WriteOwner, and AllExtendedRights abuse paths and maps them to MITRE ATT&CK privilege escalation techniques.',
                'category'    => 'identity-security',
                'tags'        => ['active-directory', 'acl', 'privilege-escalation', 'ldap', 'mitre-attack', 'T1484'],
                'steps'       => [
                    [
                        'name'            => 'Collect AD Objects & ACE Data',
                        'description'     => 'Parse provided AD LDAP dump or PowerView output to enumerate objects and their ACEs.',
                        'temperature'     => 0.2,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
You are an Active Directory security analyst performing an ACL abuse assessment.

Analyze the following AD data and enumerate all Access Control Entries (ACEs):

TARGET DOMAIN / AD DATA:
{{ad_data}}

Tasks:
1. List every object found (users, groups, OUs, computers, GPOs)
2. For each object, list its ACEs: trustee SID/name, access mask (hex), ace_type (allow/deny)
3. Identify unique trustees across all ACEs
4. Summarize permission distribution across object types

Output as structured markdown with tables. Include raw access mask values.
PROMPT,
                        'input_schema'  => ['ad_data' => 'string'],
                        'output_schema' => ['objects' => 'array', 'trustees' => 'array', 'ace_summary' => 'string'],
                    ],
                    [
                        'name'            => 'Identify Dangerous Permissions',
                        'description'     => 'Flag ACEs granting GenericAll, WriteDACL, WriteOwner, GenericWrite, or AllExtendedRights to non-admin trustees.',
                        'temperature'     => 0.2,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
You are performing an AD ACL abuse analysis. Review the ACE enumeration below and identify dangerous permissions.

PREVIOUS ANALYSIS:
{{previous_output}}

Dangerous permissions to flag (check access masks):
- GenericAll: 0x10000000 — full control
- WriteDACL: 0x00040000 — modify ACL
- WriteOwner: 0x00080000 — change owner
- GenericWrite: 0x40000000 — write properties
- AllExtendedRights: 0x00000100 — includes DCSync, ResetPassword
- ForceChangePassword: extended right on users

For EACH dangerous ACE found:
1. Trustee (who has the permission)
2. Target object (what they have rights over)
3. Permission name and hex mask
4. Why it is dangerous (what attack it enables)
5. Whether trustee is a privileged account (SKIP if Domain Admins, SYSTEM, etc.)

Focus only on non-privileged trustees with elevated rights.
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['dangerous_aces' => 'array'],
                    ],
                    [
                        'name'            => 'Map Attack Paths to Domain Admin',
                        'description'     => 'Chain dangerous ACEs into step-by-step attack paths reaching Domain Admin or DA-equivalent.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
You are an AD red team analyst. Given the dangerous ACE findings below, construct attack paths.

DANGEROUS ACE FINDINGS:
{{previous_output}}

For each exploitable finding:
1. Step-by-step attack path (e.g., "helpdesk has GenericAll on Domain Admins → add self to group → DA")
2. Required tools (BloodHound, PowerView, Impacket, etc.)
3. MITRE ATT&CK technique IDs (T1484.001 – Group Policy Modification, T1098 – Account Manipulation, etc.)
4. Difficulty: Easy / Medium / Hard
5. Detectability: Low / Medium / High (what logs it generates)

Also note:
- Which findings can be chained together
- The shortest path to Domain Admin
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['attack_paths' => 'array', 'shortest_path' => 'string'],
                    ],
                    [
                        'name'            => 'Generate Remediation Report',
                        'description'     => 'Produce a structured JSON security report with prioritized remediation actions.',
                        'temperature'     => 0.4,
                        'max_tokens'      => 4096,
                        'prompt_template' => <<<'PROMPT'
Generate a formal AD ACL security remediation report based on all findings from this assessment.

ASSESSMENT FINDINGS:
{{previous_output}}

Output a valid JSON object with this exact schema:
{
  "report_title": "Active Directory ACL Abuse Assessment",
  "assessment_date": "<today>",
  "executive_summary": "<2-3 sentence risk summary>",
  "risk_score": <1-10>,
  "critical_findings_count": <n>,
  "findings": [
    {
      "id": "ADACL-001",
      "severity": "critical|high|medium|low",
      "trustee": "<account>",
      "target_object": "<AD object>",
      "permission": "<permission name>",
      "attack_path": "<1-sentence description>",
      "mitre_techniques": ["T1484", "T1098"],
      "remediation": "<specific PowerShell command or action to fix>",
      "effort": "low|medium|high"
    }
  ],
  "prioritized_actions": ["<action 1>", "<action 2>"],
  "detection_recommendations": ["<log/SIEM rule 1>"],
  "references": ["https://attack.mitre.org/techniques/T1484/"]
}
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['json_report' => 'object'],
                    ],
                ],
            ],

            // ── 2. Network Traffic Anomaly Detection ────────────────────────
            [
                'name'        => 'Network Traffic Anomaly Detection',
                'description' => 'Analyze network traffic (PCAP, NetFlow, firewall logs) to detect anomalies including data exfiltration, C2 beaconing, lateral movement, and DNS tunneling. Maps to MITRE ATT&CK T1048, T1071.',
                'category'    => 'network-security',
                'tags'        => ['network', 'anomaly-detection', 'pcap', 'netflow', 'c2-detection', 'exfiltration', 'T1048', 'T1071'],
                'steps'       => [
                    [
                        'name'            => 'Establish Traffic Baseline',
                        'description'     => 'Parse raw traffic data and build a behavioral baseline of normal activity.',
                        'temperature'     => 0.2,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
You are a network security analyst. Analyze the following network traffic data and establish a behavioral baseline.

TRAFFIC DATA (PCAP summary / NetFlow / Firewall logs):
{{traffic_data}}

Analyze and report:
1. Top 10 source hosts by volume (bytes sent)
2. Top 10 destination IPs/domains
3. Protocol distribution (TCP/UDP/ICMP percentages)
4. Top ports in use (src and dst)
5. Geographic distribution of external connections
6. Traffic volume by hour (identify business hours vs off-hours)
7. Average connection duration
8. DNS query rate and unique domains

Identify what constitutes "normal" for this environment.
PROMPT,
                        'input_schema'  => ['traffic_data' => 'string'],
                        'output_schema' => ['baseline' => 'object'],
                    ],
                    [
                        'name'            => 'Detect Anomalies & Suspicious Patterns',
                        'description'     => 'Identify deviations from baseline including beaconing, exfiltration, and lateral movement.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 4096,
                        'prompt_template' => <<<'PROMPT'
Based on the network baseline established:

BASELINE:
{{previous_output}}

Identify ALL anomalies. Check for each category:

**C2 Beaconing:**
- Regular interval connections to external IPs
- Jitter-based beaconing (slight variation in intervals)
- Low-and-slow data transfers

**Data Exfiltration:**
- Unusually large outbound transfers
- HTTPS POST to unusual destinations
- DNS exfiltration (long subdomains, high query rate)

**Lateral Movement:**
- Internal scanning (port sweeps)
- Unusual SMB/RPC/WMI connections between hosts
- Pass-the-hash/Pass-the-ticket patterns

**Tunneling:**
- ICMP tunneling (oversized ICMP payloads)
- DNS tunneling (high entropy subdomain queries)
- HTTP/S tunneling

**Other:**
- Connections to Tor exit nodes
- Known malicious IP ranges
- Protocol anomalies (HTTP on non-80 ports)

Rate each anomaly: Critical / High / Medium / Low
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['anomalies' => 'array'],
                    ],
                    [
                        'name'            => 'Classify Threats & Map to ATT&CK',
                        'description'     => 'Classify each anomaly as a specific threat type and map to MITRE ATT&CK.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Classify the network anomalies detected and map them to the MITRE ATT&CK framework.

ANOMALIES:
{{previous_output}}

For each anomaly:
1. Threat type: APT / Ransomware / Cryptominer / Insider Threat / Botnet / Other
2. Kill chain stage: Reconnaissance / Delivery / C2 / Exfiltration / Impact
3. MITRE ATT&CK tactic and technique (e.g., Exfiltration – T1048.003)
4. Confidence level: High / Medium / Low
5. Affected hosts (source and destination)
6. IOCs to block: IP addresses, domains, signatures

Group related anomalies that likely belong to the same threat actor or campaign.
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['threat_classifications' => 'array'],
                    ],
                    [
                        'name'            => 'Generate SIEM-Ready Alert Report',
                        'description'     => 'Produce a structured alert report with IOCs, Sigma rules, and response actions.',
                        'temperature'     => 0.4,
                        'max_tokens'      => 4096,
                        'prompt_template' => <<<'PROMPT'
Generate a SIEM-ready network threat report.

THREAT CLASSIFICATIONS:
{{previous_output}}

Output valid JSON:
{
  "report_title": "Network Traffic Threat Analysis",
  "alert_severity": "critical|high|medium|low",
  "affected_hosts": ["<ip>"],
  "threat_summary": "<2-sentence description>",
  "ioc_list": [
    {"type": "ip|domain|hash|url", "value": "<value>", "confidence": "high|medium|low", "context": "<why flagged>"}
  ],
  "detections": [
    {
      "rule_type": "sigma|snort|yara",
      "rule_name": "<name>",
      "rule_content": "<rule definition>"
    }
  ],
  "containment_actions": ["Block <ip> at perimeter", "Isolate <host>"],
  "forensic_artifacts": ["<file path>", "<registry key>", "<memory artifact>"],
  "mitre_techniques": [{"id": "T1048", "name": "Exfiltration Over Alternative Protocol"}],
  "escalation_required": true
}
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['json_report' => 'object'],
                    ],
                ],
            ],

            // ── 3. Web Application Penetration Test ─────────────────────────
            [
                'name'        => 'Web Application Penetration Test',
                'description' => 'Systematic web application security assessment covering OWASP Top 10: reconnaissance, vulnerability discovery, exploitation verification, and pentest reporting. Covers A01–A10.',
                'category'    => 'web-application-security',
                'tags'        => ['owasp', 'web-pentest', 'xss', 'sqli', 'ssrf', 'broken-auth', 'A01', 'A03', 'A10'],
                'steps'       => [
                    [
                        'name'            => 'Reconnaissance & Tech Stack Fingerprinting',
                        'description'     => 'Enumerate exposed endpoints, identify frameworks, and map the application attack surface.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
You are a web application penetration tester. Begin the assessment of the following target.

TARGET:
URL: {{target_url}}
Scope: {{scope}}
Additional context: {{context}}

Perform reconnaissance analysis:
1. **Tech Stack Identification** — Identify likely: web server, framework, language, CMS, CDN, WAF
2. **Endpoint Enumeration** — List all discoverable: pages, API endpoints, admin panels, login forms
3. **Input Vectors** — Map every user-controllable input: query params, POST body, headers, cookies, file uploads
4. **Authentication Mechanisms** — Identify: login forms, OAuth, JWT usage, session management
5. **Third-party Components** — Note external JS libraries, API integrations, subdomains
6. **Security Headers** — Check presence of: CSP, HSTS, X-Frame-Options, CORS policy

Document the full attack surface before proceeding to exploitation.
PROMPT,
                        'input_schema'  => ['target_url' => 'string', 'scope' => 'string', 'context' => 'string'],
                        'output_schema' => ['attack_surface' => 'object'],
                    ],
                    [
                        'name'            => 'Vulnerability Discovery',
                        'description'     => 'Systematically test each attack surface item for OWASP Top 10 vulnerabilities.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 4096,
                        'prompt_template' => <<<'PROMPT'
Based on the attack surface mapped:

RECONNAISSANCE RESULTS:
{{previous_output}}

Test each input vector for the following vulnerability classes:

**Injection (A03):**
- SQL Injection: test with `' OR '1'='1`, error-based, blind, time-based payloads
- Command Injection: test with `; id`, `| whoami`
- LDAP/XPath/NoSQL injection patterns

**Broken Authentication (A07):**
- Default credentials
- Brute-force protection absent
- JWT algorithm confusion (alg:none, RS256→HS256)
- Session fixation/hijacking

**XSS (A03):**
- Reflected: `<script>alert(1)</script>` in all params
- Stored: persistent payloads in user content
- DOM-based: JavaScript sink analysis

**Insecure Direct Object References (A01):**
- IDOR in user IDs, file names, order numbers
- Horizontal/vertical privilege escalation

**Security Misconfiguration (A05):**
- Exposed debug endpoints, stack traces
- Default admin panels (/admin, /phpmyadmin)
- Directory listing enabled
- CORS wildcard (`Access-Control-Allow-Origin: *`)

**SSRF (A10):**
- URL parameters fetching remote content
- Internal network probing via `http://localhost`, `http://169.254.169.254`

List every finding with: endpoint, parameter, payload used, expected vs actual response.
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['vulnerabilities' => 'array'],
                    ],
                    [
                        'name'            => 'Exploitation & Impact Verification',
                        'description'     => 'Confirm exploitability and determine the real-world impact of each confirmed vulnerability.',
                        'temperature'     => 0.4,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Verify exploitation and assess real-world impact for each discovered vulnerability.

VULNERABILITIES FOUND:
{{previous_output}}

For each confirmed vulnerability:
1. **Proof of Concept** — Minimal working exploit (payload + expected server response)
2. **CVSS 3.1 Score** — Calculate: AV/AC/PR/UI/S/C/I/A metrics
3. **Business Impact** — What data or functionality is at risk?
4. **Exploitation Prerequisites** — Authentication required? Special conditions?
5. **Chaining Opportunities** — Can this be combined with other findings?
6. **False Positive Check** — Confirm it's not a false positive with secondary test

Prioritize by exploitability × impact. Flag any critical RCE or authentication bypass first.
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['confirmed_vulns' => 'array'],
                    ],
                    [
                        'name'            => 'Pentest Report Generation',
                        'description'     => 'Generate a professional penetration test report for developers and management.',
                        'temperature'     => 0.5,
                        'max_tokens'      => 4096,
                        'prompt_template' => <<<'PROMPT'
Generate a professional web application penetration test report.

EXPLOITATION FINDINGS:
{{previous_output}}

Output JSON:
{
  "report_metadata": {
    "title": "Web Application Penetration Test Report",
    "target": "<target URL>",
    "test_type": "Black Box / Grey Box / White Box",
    "methodology": "OWASP Testing Guide v4.2"
  },
  "executive_summary": {
    "overall_risk": "critical|high|medium|low",
    "critical_count": <n>,
    "high_count": <n>,
    "key_findings": ["<finding 1>", "<finding 2>"]
  },
  "findings": [
    {
      "id": "WEB-001",
      "title": "<vulnerability name>",
      "severity": "critical|high|medium|low|informational",
      "cvss_score": <0.0-10.0>,
      "owasp_category": "A01-A10",
      "affected_endpoint": "<URL>",
      "parameter": "<param name>",
      "description": "<technical description>",
      "proof_of_concept": "<payload or steps>",
      "impact": "<business impact>",
      "remediation": "<developer-ready fix>",
      "cwe": "CWE-<number>"
    }
  ],
  "remediation_roadmap": [
    {"priority": 1, "action": "<fix>", "effort": "low|medium|high"}
  ]
}
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['pentest_report' => 'object'],
                    ],
                ],
            ],

            // ── 4. Malware Behavioral Analysis ──────────────────────────────
            [
                'name'        => 'Malware Behavioral Analysis',
                'description' => 'Comprehensive malware analysis combining static and dynamic techniques to classify malware families, extract IOCs, and map behaviors to MITRE ATT&CK. Covers T1059, T1055, T1105.',
                'category'    => 'malware-analysis',
                'tags'        => ['malware', 'reverse-engineering', 'static-analysis', 'dynamic-analysis', 'ioc', 'T1059', 'T1055'],
                'steps'       => [
                    [
                        'name'            => 'Static Analysis',
                        'description'     => 'Analyze file metadata, strings, imports, and entropy without execution.',
                        'temperature'     => 0.2,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
You are a malware analyst performing static analysis. Analyze the provided sample information.

SAMPLE INFORMATION:
{{sample_data}}

Perform static analysis:
1. **File Metadata** — Hash values (MD5/SHA1/SHA256), file type, size, compile timestamp, architecture
2. **PE Header Analysis** (if PE): sections (.text/.data/.rsrc), entropy per section, unusual section names, packed indicators
3. **Import Table** — List all imported DLLs and suspicious APIs (CreateRemoteThread, VirtualAllocEx, WriteProcessMemory, InternetOpenUrl, CryptoAPI, registry APIs)
4. **String Analysis** — Extract and categorize: URLs, IPs, domains, file paths, registry keys, error messages, encoded/encrypted strings
5. **Certificate Analysis** — Signed? Valid certificate? Known malware signing cert?
6. **Packing/Obfuscation** — High entropy sections, UPX/custom packing indicators, code obfuscation
7. **YARA Rule Matches** — List any known YARA signatures that match

Rate packing likelihood and initial threat classification.
PROMPT,
                        'input_schema'  => ['sample_data' => 'string'],
                        'output_schema' => ['static_analysis' => 'object'],
                    ],
                    [
                        'name'            => 'Dynamic Behavioral Profiling',
                        'description'     => 'Profile runtime behavior including process activity, network calls, and persistence mechanisms.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Perform dynamic behavioral analysis based on sandbox report and static findings.

STATIC ANALYSIS RESULTS:
{{previous_output}}

SANDBOX/DYNAMIC DATA (if available):
{{dynamic_data}}

Document runtime behavior:

**Process Activity:**
- Child processes spawned (with command lines)
- Process injection targets
- Hollowing or DLL injection observed
- Anti-debugging/VM detection attempts

**File System Activity:**
- Files created, modified, deleted
- Dropped payloads (paths, hashes)
- Ransom note creation (if ransomware)

**Registry Activity:**
- Run key modifications (persistence)
- Credential storage keys accessed
- COM hijacking attempts

**Network Activity:**
- DNS queries made
- HTTP/S connections (URLs, headers, user-agents)
- Raw TCP/UDP connections (IPs, ports)
- Exfiltration patterns

**Cryptographic Operations:**
- Key generation, encryption routines
- Certificate operations

Map each behavior to the most likely malware capability.
PROMPT,
                        'input_schema'  => ['previous_output' => 'string', 'dynamic_data' => 'string'],
                        'output_schema' => ['behavioral_profile' => 'object'],
                    ],
                    [
                        'name'            => 'IOC Extraction & Threat Classification',
                        'description'     => 'Extract actionable IOCs and classify the malware family and threat actor TTPs.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Extract IOCs and classify the malware from behavioral analysis.

BEHAVIORAL PROFILE:
{{previous_output}}

Output:

**IOC Extraction:**
List ALL indicators of compromise:
- File hashes (MD5, SHA256) for all samples and dropped files
- IP addresses with ports
- Domain names and URLs (C2, download sites)
- Registry keys (persistence, configuration)
- Mutexes (unique identifiers)
- File paths and names

**Malware Classification:**
- Malware family (Ransomware/RAT/Trojan/Stealer/Wiper/Botnet/Loader)
- Specific family name if identifiable (e.g., Cobalt Strike, Emotet, Ryuk)
- Confidence: High / Medium / Low

**MITRE ATT&CK Mapping:**
- Map each behavior to ATT&CK tactic + technique
- Example: T1059.001 – PowerShell execution, T1055.001 – Dynamic-link Library Injection

**Threat Actor Assessment:**
- Attribution indicators (TTPs, infrastructure, tooling similarities)
- Likely threat actor group if attributable
- Motivation: Financial / Espionage / Sabotage / Hacktivism
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['iocs' => 'array', 'classification' => 'object', 'mitre_map' => 'array'],
                    ],
                    [
                        'name'            => 'Malware Analysis Report',
                        'description'     => 'Generate structured malware analysis report suitable for SOC/CERT sharing.',
                        'temperature'     => 0.4,
                        'max_tokens'      => 4096,
                        'prompt_template' => <<<'PROMPT'
Generate a comprehensive malware analysis report for SOC/CERT distribution.

ANALYSIS FINDINGS:
{{previous_output}}

Output JSON (STIX 2.1-inspired format):
{
  "report_title": "Malware Analysis Report",
  "threat_severity": "critical|high|medium|low",
  "malware_name": "<family name>",
  "malware_type": "ransomware|rat|trojan|stealer|wiper|loader|botnet",
  "executive_summary": "<3-sentence non-technical description>",
  "technical_summary": "<4-sentence technical description>",
  "iocs": {
    "hashes": [{"algorithm": "sha256", "value": "<hash>"}],
    "ips": [{"ip": "<ip>", "port": <port>, "protocol": "tcp|udp", "context": "<c2|download>"}],
    "domains": [{"domain": "<domain>", "context": "<c2|phishing>"}],
    "urls": ["<url>"],
    "mutexes": ["<mutex name>"],
    "registry_keys": ["<key path>"],
    "file_paths": ["<path>"]
  },
  "mitre_techniques": [{"id": "T1059", "name": "<name>", "tactic": "<tactic>"}],
  "detection_rules": {
    "sigma": "<sigma rule YAML>",
    "yara": "<YARA rule>"
  },
  "containment_actions": ["<action>"],
  "attribution": {
    "threat_actor": "<name or unknown>",
    "confidence": "high|medium|low",
    "motivation": "<financial|espionage|sabotage>"
  }
}
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['malware_report' => 'object'],
                    ],
                ],
            ],

            // ── 5. Incident Response Triage ──────────────────────────────────
            [
                'name'        => 'Incident Response Triage',
                'description' => 'Structured incident response following NIST SP 800-61 framework. Covers initial assessment, scope determination, evidence collection, containment strategy, and executive communication.',
                'category'    => 'security-operations',
                'tags'        => ['incident-response', 'triage', 'NIST-800-61', 'containment', 'forensics', 'soc'],
                'steps'       => [
                    [
                        'name'            => 'Initial Scope & Severity Assessment',
                        'description'     => 'Determine incident scope, classify severity, and establish an initial timeline.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
You are a senior incident responder performing initial triage. Analyze the reported incident.

INCIDENT REPORT / ALERT DATA:
{{incident_data}}

Perform initial assessment:
1. **Incident Classification** — Type: Malware / Data Breach / Unauthorized Access / DDoS / Insider Threat / Ransomware / Other
2. **Severity Rating** — P1 Critical / P2 High / P3 Medium / P4 Low (with justification)
3. **Affected Assets** — List: systems, users, data, services impacted
4. **Initial Timeline** — Reconstruct from available logs: when first activity observed, key events
5. **Blast Radius Estimate** — Is the incident contained or spreading?
6. **Active Threat?** — Is the attacker still active in the environment?
7. **Regulatory Impact** — Does this trigger GDPR/HIPAA/PCI-DSS notification requirements?

Assign severity and recommend immediate escalation path.
PROMPT,
                        'input_schema'  => ['incident_data' => 'string'],
                        'output_schema' => ['triage_assessment' => 'object'],
                    ],
                    [
                        'name'            => 'Evidence Collection Plan',
                        'description'     => 'Define what forensic evidence to collect, from which systems, and in what priority order.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Based on the incident triage, create a forensic evidence collection plan.

TRIAGE ASSESSMENT:
{{previous_output}}

Define evidence collection procedure:

**Volatile Evidence (collect first, will be lost on reboot):**
- Running processes with parent-child relationships
- Network connections (established, listening)
- Logged-in users and active sessions
- Memory contents (RAM dump priority hosts)
- Clipboard contents
- Scheduled tasks, services, startup items

**Non-Volatile Evidence:**
- Windows Event Logs (Security, System, Application, PowerShell/4104)
- Linux: /var/log/auth.log, syslog, bash history, cron
- Web server access logs
- Firewall / proxy logs
- EDR/AV quarantine and detection logs

**Priority Systems** (rank by: closest to initial access vector → most sensitive data):
1. Patient-zero / initial compromise system
2. Lateral movement pivot points
3. Data exfiltration staging servers
4. Identity systems (Domain Controller, LDAP)

**Chain of Custody** requirements for each evidence type.

Output collection commands (bash/PowerShell) for each artifact.
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['evidence_plan' => 'object'],
                    ],
                    [
                        'name'            => 'Containment & Eradication Strategy',
                        'description'     => 'Define immediate containment actions to stop the threat without destroying evidence.',
                        'temperature'     => 0.4,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Define containment and eradication strategy for this incident.

INCIDENT SCOPE & EVIDENCE PLAN:
{{previous_output}}

Develop a containment strategy balancing speed vs. evidence preservation:

**Immediate Containment (next 1 hour):**
- Network isolation actions (block specific IPs/domains, segment affected hosts)
- Account lockouts (which accounts to disable immediately)
- Service shutdowns (which services/applications to take offline)
- Firewall rule changes

**Short-term Containment (next 24 hours):**
- Password resets (all impacted accounts, admins, service accounts)
- Certificate revocation
- API key rotation
- MFA enforcement

**Eradication Steps:**
- Malware removal procedures
- Backdoor elimination checklist
- Persistence mechanism cleanup (registry, startup, cron, services)
- Compromised system rebuild vs. remediate decision matrix

**Recovery Path:**
- Systems recovery order (least → most critical)
- Verification testing before returning to production
- Monitoring during recovery phase

**Do NOT do** (actions that destroy evidence or extend the incident):
- List specific actions to avoid for this incident type
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['containment_plan' => 'object'],
                    ],
                    [
                        'name'            => 'Executive Incident Brief',
                        'description'     => 'Generate a concise non-technical executive brief for leadership communication.',
                        'temperature'     => 0.5,
                        'max_tokens'      => 2048,
                        'prompt_template' => <<<'PROMPT'
Generate a concise executive incident brief for C-suite and board communication.

INCIDENT DETAILS AND RESPONSE PLAN:
{{previous_output}}

Write a professional brief that:
- Avoids technical jargon
- Focuses on business impact
- Communicates what happened in plain language
- States what is being done right now
- Sets expectations for next update

Output JSON:
{
  "incident_name": "<descriptive name>",
  "severity": "Critical|High|Medium|Low",
  "status": "Ongoing|Contained|Resolved",
  "when_detected": "<date/time>",
  "what_happened": "<2-3 sentence plain language description>",
  "business_impact": {
    "systems_affected": <n>,
    "users_impacted": <n>,
    "data_at_risk": "<description or none>",
    "services_disrupted": ["<service>"]
  },
  "actions_taken": ["<action 1>", "<action 2>"],
  "immediate_next_steps": ["<step 1>", "<step 2>"],
  "estimated_resolution": "<timeframe>",
  "regulatory_notification_required": true,
  "next_update": "<when>"
}
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['executive_brief' => 'object'],
                    ],
                ],
            ],

            // ── 6. Threat Intelligence Report ───────────────────────────────
            [
                'name'        => 'Threat Intelligence Report',
                'description' => 'Generate structured threat intelligence reports from IOCs, incident data, or raw threat feeds. Enriches indicators, profiles threat actors, maps TTPs to MITRE ATT&CK, and produces PIR-formatted intelligence.',
                'category'    => 'threat-intelligence',
                'tags'        => ['threat-intelligence', 'ioc-enrichment', 'threat-actor', 'ttp', 'mitre-attack', 'PIR', 'STIX'],
                'steps'       => [
                    [
                        'name'            => 'IOC Enrichment & Contextualization',
                        'description'     => 'Enrich raw IOCs with threat context, reputation data, and related infrastructure.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
You are a threat intelligence analyst. Enrich the following indicators of compromise.

RAW IOCs / THREAT DATA:
{{ioc_data}}

For each indicator, provide enrichment analysis:

**IP Addresses:**
- Geolocation (country, city, ASN, ISP)
- Classification: VPN / Tor Exit / Datacenter / Residential / CDN
- Historical malicious use
- Related domains/infrastructure
- Abuse score estimate

**Domain Names:**
- Registration details (registrar, age, privacy protected)
- DNS history (IP associations over time)
- Related subdomains
- Passive DNS data
- Phishing/typosquatting indicators

**File Hashes:**
- Malware family (if known)
- First/last seen dates
- Detection ratio estimate
- Associated samples

**URLs:**
- Hosting infrastructure
- Content category
- Phishing/malware distribution indicators

Correlate IOCs to identify shared infrastructure or campaigns.
PROMPT,
                        'input_schema'  => ['ioc_data' => 'string'],
                        'output_schema' => ['enriched_iocs' => 'array'],
                    ],
                    [
                        'name'            => 'Threat Actor Profiling',
                        'description'     => 'Profile likely threat actor(s) based on TTPs, infrastructure, and victimology.',
                        'temperature'     => 0.4,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Profile the threat actor(s) based on enriched IOCs and observed TTPs.

ENRICHED IOC DATA:
{{previous_output}}

Develop threat actor profile:

**Actor Identification:**
- Known threat group aliases (APT28, Lazarus, etc.) if matching patterns observed
- Nation-state / eCrime / Hacktivist classification
- Attribution confidence: High / Medium / Low / Unknown

**Victimology:**
- Targeted industries (based on infrastructure and tools)
- Geographic focus
- Targeting criteria

**TTP Analysis (MITRE ATT&CK):**
Map ALL observed behaviors to ATT&CK:
- Initial Access (TA0001)
- Execution (TA0002)
- Persistence (TA0003)
- Privilege Escalation (TA0004)
- Defense Evasion (TA0005)
- Credential Access (TA0006)
- Discovery (TA0007)
- Lateral Movement (TA0008)
- Collection (TA0009)
- C2 (TA0011)
- Exfiltration (TA0010)

**Capability Assessment:** Basic / Intermediate / Advanced / Nation-State
**Motivation:** Financial / Espionage / Sabotage / Hacktivism
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['threat_actor_profile' => 'object'],
                    ],
                    [
                        'name'            => 'Campaign Analysis',
                        'description'     => 'Analyze the campaign timeline, infection chain, and infrastructure overlap.',
                        'temperature'     => 0.4,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Analyze the broader campaign context from the threat actor profile.

THREAT ACTOR PROFILE:
{{previous_output}}

Campaign analysis:

**Infection Chain Reconstruction:**
- Initial vector (spearphishing, watering hole, supply chain, vulnerability exploitation)
- Step-by-step infection chain from initial access to objective
- Key tooling at each stage

**Infrastructure Analysis:**
- C2 infrastructure type (dedicated / fast flux / domain generation algorithm / bulletproof hosting)
- Infrastructure overlap with previous campaigns
- Estimated infrastructure cost/sophistication

**Campaign Timeline:**
- Estimated start date
- Key milestones and escalation events
- Current phase: Reconnaissance / Initial Access / Established foothold / Active operations / Exfiltration

**Detection Opportunities:**
- Where in the kill chain can this campaign be detected?
- Which of their TTPs are most detectable?
- Key defensive gaps their TTPs exploit

**Predictions:**
- Next likely moves based on historical actor behavior
- Additional targets at risk
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['campaign_analysis' => 'object'],
                    ],
                    [
                        'name'            => 'Intelligence Report (PIR Format)',
                        'description'     => 'Produce a formal Priority Intelligence Requirement report for defensive teams.',
                        'temperature'     => 0.5,
                        'max_tokens'      => 4096,
                        'prompt_template' => <<<'PROMPT'
Generate a formal threat intelligence report in PIR (Priority Intelligence Requirement) format.

CAMPAIGN ANALYSIS:
{{previous_output}}

Output JSON:
{
  "report_classification": "TLP:AMBER",
  "report_title": "Threat Intelligence Report: <campaign/actor name>",
  "report_date": "<today>",
  "confidence_overall": "high|medium|low",
  "executive_summary": "<3-sentence summary>",
  "threat_actor": {
    "name": "<group name or UNKNOWN>",
    "aliases": [],
    "classification": "nation-state|ecrime|hacktivist",
    "capability": "advanced|intermediate|basic",
    "motivation": "<motivation>"
  },
  "campaign_summary": "<technical campaign description>",
  "iocs": {
    "ips": [], "domains": [], "hashes": [], "urls": []
  },
  "mitre_techniques": [{"id": "T1566", "name": "<name>", "tactic": "<tactic>"}],
  "recommended_detections": ["<rule 1>"],
  "recommended_defensive_actions": ["<action 1>"],
  "affected_industries": [],
  "geographic_focus": [],
  "confidence_assessment": "<why high/medium/low confidence>"
}
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['intelligence_report' => 'object'],
                    ],
                ],
            ],

            // ── 7. Cloud Security Posture Review ────────────────────────────
            [
                'name'        => 'Cloud Security Posture Review',
                'description' => 'Assess cloud infrastructure security posture against CIS Benchmarks and CSP security frameworks. Covers IAM misconfigurations, storage exposure, network security groups, encryption gaps, and logging deficiencies for AWS/Azure/GCP.',
                'category'    => 'cloud-security',
                'tags'        => ['cloud-security', 'aws', 'azure', 'gcp', 'iam', 'cspm', 'cis-benchmark', 'misconfiguration'],
                'steps'       => [
                    [
                        'name'            => 'IAM & Access Control Audit',
                        'description'     => 'Audit cloud IAM policies, roles, service accounts, and privilege assignments.',
                        'temperature'     => 0.2,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
You are a cloud security architect performing an IAM security audit.

CLOUD CONFIGURATION DATA:
Platform: {{cloud_platform}}
IAM/Policy Data: {{iam_data}}

Audit IAM for these risk patterns:

**Overprivileged Identities:**
- Users/roles with AdministratorAccess or wildcard permissions (*:*)
- Service accounts with more permissions than required (least-privilege violations)
- Cross-account trust relationships

**Authentication Weaknesses:**
- Root account usage (AWS) / Global Admin (Azure)
- MFA not enforced on privileged accounts
- Long-lived API keys / service account keys

**Permission Boundaries:**
- Missing permission boundaries on delegated roles
- SCPs (AWS) / Management Group policies (Azure) not restricting blast radius

**Secret Management:**
- Hardcoded credentials in code, environment variables, or config
- API keys / passwords in CloudFormation/Terraform templates

**Federation & SSO:**
- SAML/OIDC configuration issues
- Dangerous role trust policies (confused deputy, privilege escalation via sts:AssumeRole)

Rate each finding: Critical / High / Medium / Low with CIS Benchmark reference.
PROMPT,
                        'input_schema'  => ['cloud_platform' => 'string', 'iam_data' => 'string'],
                        'output_schema' => ['iam_findings' => 'array'],
                    ],
                    [
                        'name'            => 'Network & Data Exposure Analysis',
                        'description'     => 'Identify publicly exposed resources, open security groups, and unencrypted data.',
                        'temperature'     => 0.2,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Analyze cloud network configuration and data exposure risks.

IAM AUDIT RESULTS:
{{previous_output}}

NETWORK & DATA CONFIG:
{{network_data}}

Check for:

**Network Exposure:**
- Security groups with 0.0.0.0/0 inbound rules (especially ports 22/3389/1433/3306)
- Public subnets hosting databases or sensitive services
- Missing VPC Flow Logs
- Network ACLs with overly permissive rules

**Storage Exposure:**
- S3 buckets / Azure Blob containers with public access
- Objects with public ACLs
- Buckets accessible from any AWS account
- Missing bucket policies or overly permissive policies

**Encryption Gaps:**
- Unencrypted EBS volumes / Azure Managed Disks
- S3 buckets without SSE enabled
- Database instances without encryption at rest
- Data in transit without TLS (HTTP endpoints)
- Customer-managed keys vs. provider-managed (KMS)

**Logging & Monitoring Gaps:**
- CloudTrail / Azure Monitor / GCP Audit Logs disabled
- VPC Flow Logs not enabled
- S3 server access logging disabled
- GuardDuty / Defender for Cloud / Security Command Center not enabled

Provide CIS Benchmark control numbers for each finding.
PROMPT,
                        'input_schema'  => ['previous_output' => 'string', 'network_data' => 'string'],
                        'output_schema' => ['network_data_findings' => 'array'],
                    ],
                    [
                        'name'            => 'Risk Prioritization & CIS Scoring',
                        'description'     => 'Score findings against CIS Cloud Benchmark and produce a prioritized risk register.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Prioritize all cloud security findings and produce a risk register.

ALL FINDINGS (IAM + Network + Data):
{{previous_output}}

For each finding:
1. **CIS Control Reference** — Which CIS AWS/Azure/GCP Benchmark control this violates
2. **Exploitability** — How easily can an attacker exploit this remotely?
3. **Blast Radius** — What's the maximum damage if exploited?
4. **Detection Difficulty** — Would this go unnoticed? Are logs capturing it?
5. **Risk Score** — Exploitability × Impact (1-25 matrix)
6. **Remediation Effort** — Hours of engineering work to fix

Sort all findings by Risk Score descending.
Identify the top 5 findings that should be fixed immediately (highest risk, lowest effort).
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['risk_register' => 'array'],
                    ],
                    [
                        'name'            => 'Infrastructure-as-Code Remediation Plan',
                        'description'     => 'Generate Terraform/CloudFormation fixes and a phased remediation roadmap.',
                        'temperature'     => 0.5,
                        'max_tokens'      => 4096,
                        'prompt_template' => <<<'PROMPT'
Generate a cloud security remediation plan with IaC-ready fixes.

RISK REGISTER:
{{previous_output}}

Output JSON:
{
  "posture_score": <0-100>,
  "cis_compliance_percentage": <0-100>,
  "executive_summary": "<3-sentence cloud risk summary>",
  "critical_findings": <n>,
  "remediation_phases": [
    {
      "phase": 1,
      "timeline": "Immediate (0-48h)",
      "actions": [
        {
          "finding_id": "CLOUD-001",
          "title": "<finding title>",
          "severity": "critical",
          "fix_description": "<what to do>",
          "terraform_fix": "<Terraform HCL snippet or 'N/A'>",
          "cli_command": "<AWS CLI / az cli command>",
          "verification": "<how to confirm fix worked>"
        }
      ]
    }
  ],
  "architectural_recommendations": ["<recommendation>"],
  "monitoring_improvements": ["<CloudWatch alarm>", "<alert rule>"]
}
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['remediation_plan' => 'object'],
                    ],
                ],
            ],

            // ── 8. OSINT Target Profiling ────────────────────────────────────
            [
                'name'        => 'OSINT Target Profiling',
                'description' => 'Comprehensive open-source intelligence gathering for an organization or individual. Covers digital footprint, exposed infrastructure, credential leaks, social engineering surface, and third-party exposure.',
                'category'    => 'osint',
                'tags'        => ['osint', 'reconnaissance', 'passive-recon', 'shodan', 'whois', 'linkedin', 'dorking', 'T1598'],
                'steps'       => [
                    [
                        'name'            => 'Passive Reconnaissance',
                        'description'     => 'Map the digital footprint using passive sources without touching target systems.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
You are an OSINT analyst performing authorized passive reconnaissance.

TARGET:
Name / Organization: {{target}}
Scope: {{scope}}

Perform passive reconnaissance analysis. Document what can be found from public sources:

**Domain Intelligence:**
- Primary domains and known subdomains
- WHOIS registration details (registrant, creation date, registrar)
- DNS records (A, MX, TXT, SPF/DKIM/DMARC, CNAME)
- Certificate Transparency logs (crt.sh subdomains)
- Historical DNS (passive DNS)

**Infrastructure Mapping:**
- IP ranges and ASN ownership
- Cloud providers in use (AWS/Azure/GCP fingerprinting via DNS/certs)
- CDN and DDoS protection providers
- Email infrastructure (MX records, email provider)

**Technology Stack:**
- Web technologies (headers, JS libraries, CMS signatures)
- Programming languages/frameworks from job postings or GitHub
- Exposed version numbers

**Social & Corporate Presence:**
- LinkedIn employee count and key personnel
- GitHub/GitLab organization (repos, contributors)
- Public job postings (reveal internal tech stack)
- Social media presence

Compile findings with source URLs/methods for each item.
PROMPT,
                        'input_schema'  => ['target' => 'string', 'scope' => 'string'],
                        'output_schema' => ['recon_findings' => 'object'],
                    ],
                    [
                        'name'            => 'Digital Asset Discovery',
                        'description'     => 'Enumerate exposed services, subdomains, login portals, and misconfigured assets.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Expand the reconnaissance to discover exposed digital assets.

PASSIVE RECON RESULTS:
{{previous_output}}

Enumerate exposed assets using OSINT techniques:

**Subdomain Discovery:**
- Certificate transparency analysis results
- DNS brute force likely hits (common subdomains: admin, api, dev, staging, vpn, mail, remote)
- Reverse DNS for known IP ranges

**Exposed Services (from Shodan/Censys data):**
- Open ports and services visible on internet
- Default/exposed admin panels (Kibana, Grafana, Jenkins, etc.)
- VPN/remote access portals (Pulse Secure, Citrix, GlobalProtect)
- Exposed databases (MongoDB, Elasticsearch, Redis, exposed MySQL)

**Google/Bing Dorking:**
- site: operator for exposed files
- filetype:pdf/xlsx/docx for leaked documents
- inurl:admin / inurl:login for portals
- "powered by" / "index of /" for misconfigs

**GitHub/GitLab Exposure:**
- Leaked credentials in public repos
- Internal tool repos inadvertently public
- Exposed API keys, config files with secrets

**S3/Cloud Storage:**
- Public buckets following naming patterns
- Exposed backup files

List each asset with: URL/IP, service, risk level, and exploitation potential.
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['exposed_assets' => 'array'],
                    ],
                    [
                        'name'            => 'Credential & Data Leak Assessment',
                        'description'     => 'Check for leaked credentials, PII, internal data, and breach exposure.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Assess credential and data leak exposure for the target.

ASSET DISCOVERY:
{{previous_output}}

Check public breach and leak intelligence:

**Credential Exposure:**
- Known breach databases (HaveIBeenPwned-style checks for corporate email domains)
- Paste site exposure (Pastebin, GitHub Gists, Ghostbin)
- Dark web forum mentions (surface-level indicators only)
- Exposed credentials in GitHub repos

**Email & Account Intelligence:**
- Employee email pattern discovery (firstname.lastname@domain.com)
- Key personnel with breach exposure (VIPs, admins, executives)
- Social media accounts linked to corporate identities

**Data Leaks:**
- Internal documents indexed by search engines
- Exposed configuration files (.env, web.config, appsettings.json)
- Database connection strings in public repos
- AWS/Azure/GCP credentials in code

**Third-party Risk:**
- Vendors/partners with poor security postures
- Supply chain exposure via contractor GitHub repos
- Shadow IT (unsanctioned SaaS tools with corporate SSO)

Assess overall credential hygiene and identity exposure risk.
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['leak_assessment' => 'object'],
                    ],
                    [
                        'name'            => 'Target Intelligence Summary',
                        'description'     => 'Compile full OSINT profile with attack surface map and social engineering risk assessment.',
                        'temperature'     => 0.5,
                        'max_tokens'      => 4096,
                        'prompt_template' => <<<'PROMPT'
Compile a complete OSINT target profile.

ALL RECON DATA:
{{previous_output}}

Output JSON:
{
  "target_profile": {
    "name": "<org/person name>",
    "primary_domains": [],
    "ip_ranges": [],
    "employee_count_estimate": <n>,
    "tech_stack": [],
    "cloud_providers": [],
    "key_personnel": [
      {"name": "<name>", "role": "<title>", "exposure_risk": "high|medium|low"}
    ]
  },
  "attack_surface": {
    "exposed_services": [
      {"url": "<url>", "service": "<type>", "risk": "critical|high|medium|low"}
    ],
    "login_portals": [],
    "total_attack_surface_score": <1-100>
  },
  "credential_exposure": {
    "leaked_accounts_count": <n>,
    "breach_databases": [],
    "high_value_credentials_at_risk": true
  },
  "social_engineering_vectors": [
    "<spearphishing via LinkedIn>", "<vishing targets>"
  ],
  "critical_findings": ["<finding 1>"],
  "recommended_initial_access_vectors": ["<vector 1>"],
  "defensive_recommendations": ["<rec 1>"]
}
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['target_profile' => 'object'],
                    ],
                ],
            ],

            // ── 9. Phishing Email Analysis ───────────────────────────────────
            [
                'name'        => 'Phishing Email Analysis',
                'description' => 'Analyze suspicious emails for phishing indicators. Inspects headers, SPF/DKIM/DMARC authentication, payload analysis, IOC extraction, and generates response recommendations including block rules and user guidance.',
                'category'    => 'email-security',
                'tags'        => ['phishing', 'email-security', 'spf', 'dkim', 'dmarc', 'social-engineering', 'T1566', 'bec'],
                'steps'       => [
                    [
                        'name'            => 'Email Header & Authentication Analysis',
                        'description'     => 'Analyze email headers for forgery, authentication failures, and routing anomalies.',
                        'temperature'     => 0.2,
                        'max_tokens'      => 2048,
                        'prompt_template' => <<<'PROMPT'
You are a security analyst examining a potentially malicious email.

EMAIL DATA:
{{email_data}}

Analyze the email headers and authentication:

**Routing Analysis:**
- Full Received chain (trace actual delivery path)
- Originating IP (first untrusted Received header)
- Geolocation of originating IP
- Time zone discrepancies in timestamps
- Suspicious intermediate hops

**Authentication Results:**
- SPF: Pass/Fail/SoftFail/None — was it sent from an authorized server?
- DKIM: Pass/Fail/None — is the body signature valid?
- DMARC: Pass/Fail/None — does it align with domain policy?
- ARC: Present? Indicates forwarding chain

**Sender Forgery Indicators:**
- From: vs Reply-To: discrepancy
- Display name spoofing (e.g., "CEO Name" <attacker@gmail.com>)
- Lookalike domains (paypa1.com, microsofft.com)
- Free email provider impersonating corporate

**Header Anomalies:**
- Unusual X-headers from mass mailing platforms
- Missing headers that legitimate mail always has
- Message-ID format anomalies

Verdict: Legitimate / Suspicious / Likely Phishing / Confirmed Phishing
PROMPT,
                        'input_schema'  => ['email_data' => 'string'],
                        'output_schema' => ['header_analysis' => 'object'],
                    ],
                    [
                        'name'            => 'Content & Social Engineering Analysis',
                        'description'     => 'Analyze email body for social engineering tactics, malicious links, and pretexting.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 2048,
                        'prompt_template' => <<<'PROMPT'
Analyze email content for social engineering indicators.

HEADER ANALYSIS:
{{previous_output}}

EMAIL BODY/CONTENT:
{{email_body}}

Analyze:

**Social Engineering Tactics:**
- Urgency/scarcity language ("Your account will be suspended in 24 hours")
- Authority impersonation (CEO/IT/legal/bank)
- Fear/threat messaging
- Too-good-to-be-true offers
- Request for sensitive action (wire transfer, credential entry, software install)

**Business Email Compromise (BEC) Indicators:**
- CEO fraud / whaling patterns
- Invoice fraud / vendor impersonation
- Payroll diversion
- Gift card requests

**Link Analysis:**
- All URLs present in the email
- Redirect chains (shortened URLs)
- Visual vs. actual URL mismatch
- Lookalike domains in links
- Login pages / credential harvesting pages

**Attachment Assessment (if present):**
- File type and name
- Double extension tricks (.pdf.exe)
- Macro-enabled Office documents
- Password-protected archives (evasion technique)
- Encoded/obfuscated content

Phishing type: Spearphishing / Mass phishing / BEC / Vishing setup / Smishing / Whaling
PROMPT,
                        'input_schema'  => ['previous_output' => 'string', 'email_body' => 'string'],
                        'output_schema' => ['content_analysis' => 'object'],
                    ],
                    [
                        'name'            => 'IOC Extraction & Threat Lookup',
                        'description'     => 'Extract all IOCs from the email for threat intelligence and blocking.',
                        'temperature'     => 0.2,
                        'max_tokens'      => 2048,
                        'prompt_template' => <<<'PROMPT'
Extract all IOCs from this phishing email analysis.

HEADER + CONTENT ANALYSIS:
{{previous_output}}

Extract and categorize all IOCs:

**Infrastructure IOCs:**
- Originating IP(s) — check against known phishing infrastructure
- Sending mail server IPs
- URLs in email body (defanged format: hxxps://example[.]com)
- Redirect intermediaries
- Final destination URLs

**File IOCs (if attachments present):**
- Filenames
- File hashes (if derivable from metadata)
- Macro command strings

**Identity IOCs:**
- Sending email addresses (all From/Reply-To/Return-Path)
- Lookalike domains
- Sender display names used for impersonation

**Campaign Indicators:**
- Unique strings that may identify the phishing kit
- Similar campaigns observed (same infrastructure patterns)

Present all IOCs in both normal and defanged format.
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['ioc_list' => 'array'],
                    ],
                    [
                        'name'            => 'Verdict, Block Rules & User Guidance',
                        'description'     => 'Issue final verdict, email gateway block rules, and user-facing communication.',
                        'temperature'     => 0.4,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Issue final verdict and generate response recommendations for this phishing email.

FULL ANALYSIS:
{{previous_output}}

Output JSON:
{
  "verdict": "malicious|suspicious|benign",
  "confidence": "high|medium|low",
  "phishing_type": "spearphishing|mass-phishing|bec|credential-harvest|malware-delivery",
  "threat_summary": "<2-sentence plain language description>",
  "email_gateway_rules": [
    {
      "rule_type": "sender-block|ip-block|domain-block|subject-regex|header-match",
      "value": "<value to block>",
      "action": "quarantine|reject|tag",
      "reason": "<why>"
    }
  ],
  "ioc_block_list": {
    "ips": [], "domains": [], "urls": [], "email_addresses": []
  },
  "user_communication": {
    "subject": "Security Alert: Phishing Email Detected",
    "body": "<plain-language email to send to affected users>",
    "action_required": "<what users should do if they clicked/submitted data>"
  },
  "if_user_clicked": {
    "immediate_actions": ["<reset password>", "<revoke sessions>"],
    "forensic_actions": ["<collect browser history>"],
    "escalation": true
  },
  "mitre_technique": "T1566.001|T1566.002|T1566.003"
}
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['phishing_response' => 'object'],
                    ],
                ],
            ],

            // ── 10. SQL Injection Detection & Analysis ───────────────────────
            [
                'name'        => 'SQL Injection Detection & Analysis',
                'description' => 'Systematic SQL injection assessment covering error-based, blind boolean, time-based, and out-of-band techniques. Maps to OWASP A03:2021 and CWE-89. Generates developer-ready parameterized query fixes.',
                'category'    => 'web-application-security',
                'tags'        => ['sql-injection', 'sqli', 'owasp-A03', 'CWE-89', 'sqlmap', 'database', 'parameterized-queries'],
                'steps'       => [
                    [
                        'name'            => 'Input Vector Mapping',
                        'description'     => 'Enumerate all SQL-queryable input vectors in the target application.',
                        'temperature'     => 0.2,
                        'max_tokens'      => 2048,
                        'prompt_template' => <<<'PROMPT'
You are a web security researcher performing SQL injection assessment.

TARGET APPLICATION:
URL: {{target_url}}
Application Context: {{app_context}}

Map ALL input vectors that likely touch SQL queries:

**GET Parameters:**
- URL path segments used as IDs (/users/123, /products/laptop)
- Query string parameters (?id=, ?search=, ?category=, ?order=, ?page=)
- Filter parameters

**POST Body:**
- Login forms (username, password fields)
- Search forms
- Data submission forms
- JSON API body parameters

**HTTP Headers:**
- Cookie values used in queries
- X-Forwarded-For (logged in SQL)
- User-Agent, Referer (logged in SQL)
- Custom authentication headers

**Second-Order Injection Points:**
- Registration fields stored then used in later queries
- Profile fields (name, bio) used in search/display queries

For each input vector, assess:
- Expected data type (integer, string, boolean)
- Backend database likelihood (MySQL, MSSQL, PostgreSQL, Oracle, SQLite)
- Query context (WHERE clause, ORDER BY, LIMIT, INSERT, etc.)
PROMPT,
                        'input_schema'  => ['target_url' => 'string', 'app_context' => 'string'],
                        'output_schema' => ['input_vectors' => 'array'],
                    ],
                    [
                        'name'            => 'Injection Technique Testing',
                        'description'     => 'Test each input vector with error-based, blind, time-based, and UNION payloads.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Test the identified input vectors for SQL injection vulnerabilities.

INPUT VECTORS:
{{previous_output}}

For each input vector, design and analyze test payloads:

**Error-Based Detection:**
- Single quote test: `'` → triggers syntax error?
- Comment test: `-- -` / `#` / `/**/`
- Database error messages revealing DBMS type

**Boolean-Based Blind:**
- True condition: `1=1` / `'a'='a'`
- False condition: `1=2` / `'a'='b'`
- Response difference between true/false indicates blind SQLi

**Time-Based Blind:**
- MySQL: `1' AND SLEEP(5)-- -`
- MSSQL: `1'; WAITFOR DELAY '0:0:5'-- -`
- PostgreSQL: `1'; SELECT pg_sleep(5)-- -`
- Oracle: `1' AND 1=DBMS_PIPE.RECEIVE_MESSAGE('a',5)-- -`

**UNION-Based:**
- Column count: `ORDER BY 1-- -`, increase until error
- Column data types: `UNION SELECT NULL,NULL,NULL-- -`
- Data extraction: `UNION SELECT table_name,NULL FROM information_schema.tables-- -`

**Out-of-Band:**
- DNS exfiltration via LOAD_FILE / UTL_HTTP
- XXE via SQL server features

For each finding: parameter name, technique that worked, payload, response, and DBMS confirmed.
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['sqli_findings' => 'array'],
                    ],
                    [
                        'name'            => 'Exploitation Scope Assessment',
                        'description'     => 'Determine what data can be extracted and what privilege escalation is possible.',
                        'temperature'     => 0.3,
                        'max_tokens'      => 3000,
                        'prompt_template' => <<<'PROMPT'
Assess exploitation scope for confirmed SQL injection vulnerabilities.

CONFIRMED VULNERABILITIES:
{{previous_output}}

Determine the real-world impact:

**Data Access Assessment:**
- What database and tables are accessible via the injection?
- Can we enumerate: users, passwords, PII, payment data, session tokens?
- Database version and user context (are we db_owner / DBA / root?)

**Privilege Escalation Potential:**
- MySQL: Can we write files (`SELECT INTO OUTFILE`)?
- MySQL: Can we read files (`LOAD_FILE`)?
- MSSQL: Is xp_cmdshell enabled? Can we enable it?
- PostgreSQL: Can we use COPY TO/FROM for RCE?
- Oracle: Can we execute Java stored procedures?

**Authentication Bypass:**
- Can the SQLi bypass login (`' OR '1'='1`)?
- Can we dump the users table and crack hashes offline?
- Can we directly modify session/authentication data?

**Chaining Opportunities:**
- SQLi → credential dump → admin access
- SQLi → file write → webshell → RCE
- SQLi → stored XSS via data insertion

CVSS 3.1 score for each confirmed finding.
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['exploitation_scope' => 'object'],
                    ],
                    [
                        'name'            => 'Developer Remediation Guide',
                        'description'     => 'Generate language-specific parameterized query fixes and WAF rules for each finding.',
                        'temperature'     => 0.4,
                        'max_tokens'      => 4096,
                        'prompt_template' => <<<'PROMPT'
Generate a developer-ready SQL injection remediation guide.

EXPLOITATION FINDINGS:
{{previous_output}}

Output JSON with both executive summary and technical fixes:
{
  "summary": {
    "severity": "critical|high|medium",
    "cvss_score": <0.0-10.0>,
    "owasp_category": "A03:2021 – Injection",
    "cwe": "CWE-89",
    "finding_count": <n>
  },
  "findings": [
    {
      "id": "SQLI-001",
      "endpoint": "<URL>",
      "parameter": "<param>",
      "technique": "error-based|blind|time-based|union",
      "dbms": "mysql|mssql|postgresql|oracle",
      "impact": "<what can be extracted/done>",
      "cvss_score": <0.0-10.0>
    }
  ],
  "remediation": {
    "php": "// Vulnerable:\n$query = \"SELECT * FROM users WHERE id = $id\";\n// Fixed (PDO):\n$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');\n$stmt->execute([$id]);",
    "python": "// Vulnerable + Fixed with parameterized query",
    "java": "// PreparedStatement example",
    "nodejs": "// Parameterized query with mysql2/postgres",
    "orm_note": "Always use ORM parameterization (Eloquent, SQLAlchemy, Hibernate) — never string concatenation"
  },
  "waf_rules": [
    {
      "type": "modsecurity|cloudflare|aws-waf",
      "rule": "<rule definition>"
    }
  ],
  "additional_controls": [
    "Input validation: whitelist expected character sets",
    "Stored procedure usage",
    "Database user least privilege",
    "Error handling: never expose SQL errors to users"
  ]
}
PROMPT,
                        'input_schema'  => ['previous_output' => 'string'],
                        'output_schema' => ['remediation_guide' => 'object'],
                    ],
                ],
            ],

        ];
    }
}
