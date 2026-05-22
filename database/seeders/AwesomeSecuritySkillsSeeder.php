<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\SkillStep;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AwesomeSecuritySkillsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first() ?? User::first();

        foreach ($this->skills() as $skillData) {
            $steps = $skillData['steps'];
            unset($skillData['steps'], $skillData['slug']);

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
                        'model'           => 'claude-opus-4-7',
                        'temperature'     => 0.3,
                        'max_tokens'      => 4096,
                        'is_active'       => true,
                    ])
                );
            }

            $this->command->info("  ✓ {$skill->name}  [{$skill->category}]  " . count($steps) . " steps");
        }
    }

    private function skills(): array
    {
        return [
            // ── 1. SQL Injection Testing ──────────────────────────────────────────
            [
                'name'        => 'SQL Injection Testing',
                'slug'        => 'sql-injection-testing',
                'description' => 'Structured SQL injection assessment covering detection, payload selection, exploitation, and remediation guidance for authorised engagements.',
                'category'    => 'web-application-security',
                'steps' => [
                    [
                        'name'            => 'Reconnaissance & Context Gathering',
                        'description'     => 'Identify the target endpoint, database technology, and injection surface.',
                        'prompt_template' => <<<'PROMPT'
You are a senior penetration tester specialising in SQL injection for an authorised engagement.

Target: {{target}}
Context provided by operator: {{context}}

Perform the following reconnaissance steps and return structured findings:

1. Identify the likely database platform (MySQL, PostgreSQL, MSSQL, Oracle, SQLite) based on any context clues.
2. List all injectable parameters or entry points relevant to this target.
3. Describe the HTTP method, parameter type (GET, POST, cookie, header), and encoding in use.
4. Summarise the injection surface in a table: Parameter | Type | Suspected DB | Notes.
5. Recommend the first detection payloads to confirm SQLi (error-based, boolean-based).

Output format: structured Markdown with sections for Surface Map, DB Fingerprint, and Recommended First Payloads.
Reminder: This assessment is authorised. Do not test beyond the stated scope.
PROMPT
                    ],
                    [
                        'name'            => 'Payload Execution & Exploitation',
                        'description'     => 'Select and document SQLi payloads appropriate to the identified database and injection type.',
                        'prompt_template' => <<<'PROMPT'
You are a SQL injection specialist continuing an authorised assessment.

Target: {{target}}
Reconnaissance output: {{last_output}}
Additional context: {{context}}

Using the surface map and DB fingerprint above:

1. Provide a prioritised payload list (union-based → error-based → blind → time-based) for the identified DB.
2. For each payload, show the raw injection string and the expected response indicator.
3. If the DB is unknown, provide a polyglot detection set covering the four major platforms.
4. Document any WAF evasion techniques relevant to the encoding observed.
5. Show how to extract: DB version, current user, database name, table names, column names, and a sample data row.

Present each payload in a fenced code block with a comment explaining what it tests.
PROMPT
                    ],
                    [
                        'name'            => 'Findings Report & Remediation',
                        'description'     => 'Compile a professional SQLi findings report with CVSS score and remediation steps.',
                        'prompt_template' => <<<'PROMPT'
You are a security consultant writing the SQL injection section of a penetration test report.

Target: {{target}}
Exploitation evidence: {{last_output}}
Context: {{context}}

Write a professional finding entry containing:

1. **Vulnerability Title**: SQL Injection – [Parameter Name]
2. **CVSS v3.1 Score**: Calculate base score (AV, AC, PR, UI, S, C, I, A) and provide the vector string.
3. **Severity Rating**: Critical / High / Medium / Low
4. **Description**: Plain-language explanation of the vulnerability.
5. **Evidence**: Summarise the proof-of-concept (payload used, response indicator).
6. **Impact**: What an attacker could achieve (data exfiltration, auth bypass, RCE via stacked queries, etc.).
7. **Remediation**:
   - Use parameterised queries / prepared statements (provide code example in the detected stack language).
   - Apply least-privilege DB accounts.
   - Enable WAF rules and input validation.
8. **References**: OWASP A03:2021, CWE-89.

Output in clean Markdown suitable for inclusion in a client report.
PROMPT
                    ],
                ],
            ],

            // ── 2. XSS Testing ───────────────────────────────────────────────────
            [
                'name'        => 'Cross-Site Scripting (XSS) Testing',
                'slug'        => 'xss-testing',
                'description' => 'End-to-end XSS assessment covering injection point discovery, payload crafting, filter bypass, and safe PoC development.',
                'category'    => 'web-application-security',
                'steps' => [
                    [
                        'name'            => 'Injection Point Analysis',
                        'description'     => 'Map XSS injection surfaces and understand output context.',
                        'prompt_template' => <<<'PROMPT'
You are an XSS specialist conducting an authorised web application assessment.

Target: {{target}}
Context: {{context}}

Analyse the target and return:

1. A table of potential XSS injection points: Location | Parameter | Injection Context (HTML body / HTML attribute / JavaScript / URL / CSS) | Reflection Type (Reflected / Stored / DOM).
2. For each context, identify the characters that appear to be reflected unfiltered.
3. Note any Content Security Policy (CSP) headers, X-XSS-Protection, or sanitisation libraries observed.
4. Classify each point by XSS type: Reflected, Stored, or DOM-based.
5. Prioritise injection points by exploitability (Stored > DOM > Reflected).
PROMPT
                    ],
                    [
                        'name'            => 'Payload Crafting & Filter Bypass',
                        'description'     => 'Select context-appropriate XSS payloads and bypass encoding or filtering.',
                        'prompt_template' => <<<'PROMPT'
You are an XSS payload specialist continuing an authorised assessment.

Injection surface map: {{last_output}}
Target: {{target}}
Context: {{context}}

For each injection point identified:

1. Provide three payloads per context (basic → encoded → obfuscated).
2. Show HTML attribute context escaping: closing the attribute, breaking out with event handlers.
3. Show JavaScript context payloads: string termination, template literal abuse.
4. Provide CSP bypass payloads if a CSP was detected (JSONP abuse, trusted domain bypass, nonce prediction).
5. All PoC payloads must use `alert(document.domain)` or `console.log('XSS-PoC')` — no credential harvesting or session theft.
6. Mark each payload: [Safe PoC] | [Filter Bypass] | [Obfuscated].
PROMPT
                    ],
                    [
                        'name'            => 'PoC Documentation & Remediation Report',
                        'description'     => 'Document the XSS finding with impact analysis and developer-ready fixes.',
                        'prompt_template' => <<<'PROMPT'
You are a security consultant writing the XSS section of a penetration test report.

Target: {{target}}
Payload evidence: {{last_output}}
Context: {{context}}

Produce a professional XSS finding entry:

1. **Vulnerability Title**: Cross-Site Scripting (XSS) – [Type] – [Parameter]
2. **CVSS v3.1**: Base score with vector string.
3. **Severity**: Critical / High / Medium / Low
4. **Description**: Clear explanation of the vulnerability and how the payload executes.
5. **Evidence**: Safe PoC payload used and the observed outcome.
6. **Impact**: Session hijacking, credential theft, defacement, CSRF bypass, phishing via stored XSS.
7. **Remediation**:
   - Output encoding (HTML entity encoding for each context — provide code snippet).
   - Content Security Policy configuration example.
   - HTTPOnly and SameSite cookie flags.
   - Input validation on server side.
8. **References**: OWASP A03:2021, CWE-79.
PROMPT
                    ],
                ],
            ],

            // ── 3. Wordlist & Credential Attack Assistant ─────────────────────────
            [
                'name'        => 'Wordlist & Credential Attack Assistant',
                'slug'        => 'wordlist-credential-attack-assistant',
                'description' => 'Recommends SecLists wordlists, builds targeted attack plans, and guides authorised credential testing with proper rate-limiting.',
                'category'    => 'penetration-testing',
                'steps' => [
                    [
                        'name'            => 'Target & Attack Surface Analysis',
                        'description'     => 'Understand the authentication mechanism to select the right wordlist strategy.',
                        'prompt_template' => <<<'PROMPT'
You are a penetration tester specialising in credential-based attacks for an authorised engagement.

Target: {{target}}
Context: {{context}}

Analyse the authentication surface:

1. Identify the authentication type (web login form, SSH, RDP, FTP, API key, hash cracking, username enumeration).
2. Describe any observed lockout policies, rate-limiting, or CAPTCHA.
3. Identify the technology stack if visible (WordPress, Active Directory, custom app, etc.).
4. List the recommended SecLists wordlists for this scenario:
   - Passwords: (e.g. rockyou.txt, darkweb2017-top10000.txt, common-passwords-win.txt)
   - Usernames: (e.g. top-usernames-shortlist.txt, xato-net-10-million-usernames.txt)
   - Context-specific: (admin panels → admin-panels.txt; API fuzzing → Fuzzing/*)
5. Estimate wordlist size vs. lockout threshold trade-off.
PROMPT
                    ],
                    [
                        'name'            => 'Attack Plan & Tool Configuration',
                        'description'     => 'Build a complete tool-ready credential attack plan with rate-limiting safeguards.',
                        'prompt_template' => <<<'PROMPT'
You are a senior penetration tester building a credential attack plan.

Surface analysis: {{last_output}}
Target: {{target}}
Context: {{context}}

Provide a complete, authorised attack plan:

1. **Tool Selection**: Choose between Hydra, Medusa, Burp Intruder, ffuf, CrackMapExec based on the service.
2. **Command Template**: Write the exact tool command with placeholders (replace with actual credentials file paths).
3. **Rate-Limiting**: Set delays (--wait / -t flags) to stay below lockout threshold.
4. **Username Enumeration First**: If applicable, enumerate valid usernames before password spraying.
5. **Password Spraying vs. Brute Force**: Recommend spraying (one password, many users) for AD environments.
6. **Hash Cracking**: If hashes are available, provide hashcat mode numbers and command for MD5 / NTLM / bcrypt.
7. **Monitoring**: How to detect if you're being blocked (HTTP 429, account lockout events).

Mark all commands clearly as [AUTHORISED TESTING ONLY].
PROMPT
                    ],
                    [
                        'name'            => 'Findings & Hardening Recommendations',
                        'description'     => 'Summarise credential attack findings and provide password policy hardening guidance.',
                        'prompt_template' => <<<'PROMPT'
You are a security consultant summarising credential attack findings.

Target: {{target}}
Attack results: {{last_output}}
Context: {{context}}

Produce the credential security finding:

1. **Finding**: Weak / Default / Reused Credentials (or Successful Enumeration)
2. **CVSS v3.1 Score** with vector string.
3. **Accounts Compromised**: List discovered credentials in [redacted] format for the report.
4. **Root Cause**: Weak password policy, no lockout, default credentials, credential reuse.
5. **Remediation**:
   - Minimum password complexity requirements.
   - Account lockout policy (5 attempts / 15-minute window recommended).
   - Multi-factor authentication deployment.
   - Privileged access management (PAM) for admin accounts.
   - Credential rotation schedule.
6. **References**: NIST SP 800-63B, CWE-521.
PROMPT
                    ],
                ],
            ],

            // ── 4. Web Shell Detection ───────────────────────────────────────────
            [
                'name'        => 'Web Shell Detection & Analysis',
                'slug'        => 'web-shell-detection-analysis',
                'description' => 'Defensive skill for detecting, analysing, and eradicating web shells using static signatures, YARA rules, and behavioural indicators.',
                'category'    => 'malware-analysis',
                'steps' => [
                    [
                        'name'            => 'Suspicious File Identification',
                        'description'     => 'Scan file system artefacts for web shell indicators.',
                        'prompt_template' => <<<'PROMPT'
You are a malware analyst and incident responder investigating a potential web shell infection.

Target system / evidence: {{target}}
Incident context: {{context}}

Perform initial web shell identification:

1. List the file system locations most commonly used to plant web shells for this server type (Apache/Nginx/IIS/Tomcat).
2. Provide CLI commands to find recently modified PHP/ASP/JSP files:
   - find by modification time (last 7 days)
   - find by suspicious file size (< 10KB standalone script)
   - find files with unusual names (random strings, double extensions)
3. List the most common web shell filenames (c99.php, r57.php, WSO, China Chopper, etc.).
4. Describe behavioural indicators in access logs: unusual POST to .php files, large response bodies from small files, base64-encoded POST parameters.
5. Provide a grep command to find dangerous PHP functions: eval, base64_decode, system, exec, passthru, shell_exec, preg_replace with /e modifier.
PROMPT
                    ],
                    [
                        'name'            => 'YARA Rule & Signature Generation',
                        'description'     => 'Generate detection signatures and YARA rules for identified web shell patterns.',
                        'prompt_template' => <<<'PROMPT'
You are a threat intelligence analyst creating detection signatures.

Identified indicators: {{last_output}}
Target: {{target}}
Context: {{context}}

Generate detection artefacts:

1. Write a YARA rule covering:
   - Common PHP web shell strings (eval, base64_decode combination)
   - China Chopper one-liner signature
   - WSO shell meta-strings
   - Generic "password-protected" shell patterns
2. Write a Snort/Suricata IDS rule for detecting web shell C2 traffic (HTTP POST with encoded payload).
3. Provide a Python script that:
   - Recursively scans a directory
   - Flags files matching suspicious function patterns
   - Outputs: filename, line number, matched pattern, file hash (MD5)
4. List the MITRE ATT&CK techniques covered: T1505.003 (Server Software Component: Web Shell).
PROMPT
                    ],
                    [
                        'name'            => 'Eradication & Hardening Report',
                        'description'     => 'Guide safe removal, root-cause closure, and post-incident hardening.',
                        'prompt_template' => <<<'PROMPT'
You are an incident responder writing the eradication and hardening section of an IR report.

Detection evidence: {{last_output}}
Target: {{target}}
Context: {{context}}

Produce the eradication and hardening plan:

1. **Containment**: Isolate affected server (network quarantine steps).
2. **Evidence Preservation**: Commands to capture memory dump, copy log files, hash all artefacts before removal.
3. **Eradication**: Safe steps to remove identified web shells; verify no persistence mechanisms remain (crontabs, .htaccess rules, DB-stored payloads).
4. **Root Cause**: Identify the likely initial access vector (file upload bypass, RCE, outdated CMS plugin).
5. **Hardening**:
   - Disable PHP execution in upload directories (Apache/Nginx config snippet).
   - Implement file integrity monitoring (AIDE, Tripwire).
   - Web Application Firewall (ModSecurity CRS rules for web shell detection).
   - Remove write permissions from web root (chmod recommendations).
6. **References**: MITRE T1505.003, CISA AA22-321A.
PROMPT
                    ],
                ],
            ],

            // ── 5. API Key & Secrets Scanner ─────────────────────────────────────
            [
                'name'        => 'API Key & Secrets Scanner',
                'slug'        => 'api-key-secrets-scanner',
                'description' => 'Scans codebases, repositories, and configuration files for exposed API keys, credentials, and sensitive data patterns.',
                'category'    => 'api-security',
                'steps' => [
                    [
                        'name'            => 'Scope Definition & Tooling Setup',
                        'description'     => 'Define scanning scope and prepare detection toolchain.',
                        'prompt_template' => <<<'PROMPT'
You are a security engineer conducting an authorised secrets scanning engagement.

Target repository / codebase: {{target}}
Context: {{context}}

Define the scanning strategy:

1. List all file types to scan: .env, .yaml, .json, .xml, .properties, .conf, .ini, .py, .js, .ts, .php, .rb, .go, .java.
2. List paths to exclude: node_modules/, vendor/, .git/objects/, build/, dist/.
3. Provide regex patterns for the following secret types:
   - AWS Access Key: `AKIA[0-9A-Z]{16}`
   - AWS Secret Key: `[0-9a-zA-Z/+]{40}`
   - GitHub Personal Access Token: `ghp_[a-zA-Z0-9]{36}`
   - Google API Key: `AIza[0-9A-Za-z-_]{35}`
   - Slack Token: `xox[baprs]-[0-9a-zA-Z-]+`
   - Generic API Key: `(?i)(api[_-]?key|apikey|api[_-]?secret)\s*[=:]\s*['\"]?[a-zA-Z0-9]{20,}`
   - Generic Password: `(?i)(password|passwd|pwd)\s*[=:]\s*['\"]?[^\s'"]{8,}`
   - Private Key Header: `-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----`
4. Recommend tools: truffleHog, gitleaks, git-secrets, semgrep.
PROMPT
                    ],
                    [
                        'name'            => 'Pattern Matching Execution',
                        'description'     => 'Execute secrets scan and report all matches with context.',
                        'prompt_template' => <<<'PROMPT'
You are a secrets scanner analyst processing scan results.

Scan configuration: {{last_output}}
Target: {{target}}
Context: {{context}}

Analyse and structure the scan results:

1. Provide the exact CLI commands to run truffleHog and gitleaks against the target:
   - Filesystem scan
   - Git history scan (all branches)
   - Docker image scan (if applicable)
2. For each secret type found, produce a table: File Path | Line | Secret Type | Severity | First 6 chars (masked) | Last seen in git history.
3. Identify secrets that appear in git commit history vs. currently staged files (higher priority if in history).
4. Check for false positives: test fixtures, placeholder values, example files.
5. Classify each finding: **Critical** (live credentials), **High** (unknown if rotated), **Medium** (internal service credentials), **Low** (test/placeholder).
PROMPT
                    ],
                    [
                        'name'            => 'Remediation Plan & Prevention Framework',
                        'description'     => 'Create rotation plan, git history cleanup, and prevention controls.',
                        'prompt_template' => <<<'PROMPT'
You are a DevSecOps engineer writing the secrets exposure remediation plan.

Scan findings: {{last_output}}
Target: {{target}}
Context: {{context}}

Produce the remediation and prevention report:

1. **Immediate Rotation**: List each exposed credential with its rotation procedure (AWS → IAM console, GitHub → Settings > Developer tokens, etc.).
2. **Git History Cleanup**: Commands using BFG Repo-Cleaner or `git filter-repo` to remove secrets from all commits.
3. **Pre-Commit Hooks**: Install gitleaks or git-secrets as a pre-commit hook (provide `.pre-commit-config.yaml` snippet).
4. **CI/CD Integration**: GitHub Actions / GitLab CI pipeline step to run secrets scan on every PR (provide workflow YAML).
5. **Secrets Management Migration**: Recommend HashiCorp Vault, AWS Secrets Manager, or Azure Key Vault; show how to replace hardcoded secrets with environment variable references.
6. **Developer Training**: Key points for a 5-minute developer briefing on secrets hygiene.
7. **References**: OWASP Secret Management Cheat Sheet, CWE-798.
PROMPT
                    ],
                ],
            ],

            // ── 6. Penetration Test Advisor ───────────────────────────────────────
            [
                'name'        => 'Penetration Test Advisor',
                'slug'        => 'penetration-test-advisor',
                'description' => 'Full-engagement penetration testing advisor covering scoping, methodology selection, tool recommendations, and professional reporting.',
                'category'    => 'penetration-testing',
                'steps' => [
                    [
                        'name'            => 'Engagement Scoping & Methodology',
                        'description'     => 'Define scope, objectives, rules of engagement, and testing methodology.',
                        'prompt_template' => <<<'PROMPT'
You are an expert penetration testing advisor assisting with an authorised engagement.

Target: {{target}}
Engagement context: {{context}}

Produce the engagement scoping document:

1. **Scope Statement**: Define in-scope assets (IPs, domains, applications, network segments).
2. **Out-of-Scope**: Identify what must not be tested (third-party services, production databases, etc.).
3. **Testing Type**: Classify as Black Box / Grey Box / White Box and justify.
4. **Methodology**: Recommend the appropriate standard:
   - Web app → OWASP Testing Guide v4.2
   - Network → PTES (Penetration Testing Execution Standard)
   - Red team → MITRE ATT&CK framework
5. **Rules of Engagement**: Testing windows, emergency stop conditions, communication contacts.
6. **Phase Plan**: Timeline with milestones — Recon → Scanning → Exploitation → Post-Exploitation → Reporting.
7. **Legal Framework**: Confirm written authorisation requirement; list applicable laws (CFAA, Computer Misuse Act).
PROMPT
                    ],
                    [
                        'name'            => 'Technical Execution Guidance',
                        'description'     => 'Guide the tester through reconnaissance, scanning, exploitation, and post-exploitation phases.',
                        'prompt_template' => <<<'PROMPT'
You are a senior penetration tester guiding technical execution.

Scoping document: {{last_output}}
Target: {{target}}
Context: {{context}}

Provide phase-by-phase technical guidance:

**Phase 1 — Reconnaissance**
- Passive: whois, DNS enumeration (subfinder, amass), Google dorks, Shodan, LinkedIn OSINT.
- Active: nmap discovery scan (`nmap -sn -T4 {{target}}`), service version scan.

**Phase 2 — Vulnerability Assessment**
- Web: nikto, OWASP ZAP, Burp Suite active scan.
- Network: nmap NSE scripts, OpenVAS, Nessus (if authorised).
- Provide the top 10 checks specific to the identified technology stack.

**Phase 3 — Exploitation**
- Map findings to Metasploit modules or manual exploit techniques.
- Prioritise critical/high findings; show exploitation path with PoC commands.
- Document every action with timestamp and command used.

**Phase 4 — Post-Exploitation**
- Privilege escalation checks (LinPEAS / WinPEAS commands).
- Lateral movement indicators (pass-the-hash, token impersonation).
- Data exfiltration simulation (identify sensitive data locations, do NOT exfiltrate).

Keep all actions within the authorised scope defined above.
PROMPT
                    ],
                    [
                        'name'            => 'Executive & Technical Report Generation',
                        'description'     => 'Generate a professional penetration test report with executive summary and technical findings.',
                        'prompt_template' => <<<'PROMPT'
You are a penetration testing consultant writing the final engagement report.

Technical findings: {{last_output}}
Target: {{target}}
Context: {{context}}

Generate the penetration test report structure:

**Executive Summary** (non-technical, 1 page):
- Engagement overview and objectives
- Overall security posture rating (Critical/High/Medium/Low)
- Top 3 most critical findings in plain language
- Strategic recommendations

**Risk Summary Table**:
| Finding | Severity | CVSS | Affected Asset | Status |
|---------|----------|------|----------------|--------|

**Detailed Technical Findings** (for each vulnerability):
- Title, CVSS v3.1 vector, severity
- Description, affected component
- Steps to reproduce
- Evidence (output/screenshot description)
- Business impact
- Remediation steps with code examples
- References (CVE, CWE, OWASP)

**Remediation Roadmap**:
- Immediate (0–7 days): Critical patches
- Short-term (7–30 days): High severity items
- Medium-term (30–90 days): Medium severity items
- Long-term (90+ days): Architectural improvements

**Appendix**: Tools used, testing timeline, scope confirmation.
PROMPT
                    ],
                ],
            ],

            // ── 7. CTF Assistant ──────────────────────────────────────────────────
            [
                'name'        => 'CTF Challenge Assistant',
                'slug'        => 'ctf-challenge-assistant',
                'description' => 'Guides CTF participants through web, crypto, binary exploitation, forensics, and OSINT challenges with step-by-step methodology and tool usage.',
                'category'    => 'red-teaming',
                'steps' => [
                    [
                        'name'            => 'Challenge Analysis & Category Classification',
                        'description'     => 'Identify the challenge type and plan the initial approach.',
                        'prompt_template' => <<<'PROMPT'
You are a CTF coach helping a security researcher solve a Capture The Flag challenge.

Challenge description / target: {{target}}
Additional context / hints: {{context}}

Perform initial challenge analysis:

1. **Category**: Classify as Web / Crypto / Binary Exploitation / Forensics / Reversing / OSINT / Misc.
2. **Sub-type**: (e.g., Web → SQL Injection / XSS / SSRF / Deserialization; Crypto → RSA / AES / Caesar).
3. **Key Observations**: List everything notable in the challenge description, source code, or files provided.
4. **Initial Hypotheses**: 3 ranked guesses for the intended vulnerability or technique.
5. **Recommended Tools**:
   - Web: Burp Suite, ffuf, curl
   - Crypto: CyberChef, pycryptodome, RsaCtfTool
   - Binary: GDB + pwndbg, pwntools, ROPgadget, checksec
   - Forensics: binwalk, foremost, Volatility, Wireshark, exiftool
   - Reversing: Ghidra, IDA Free, radare2, strings
   - OSINT: theHarvester, recon-ng, Maltego
6. **First Steps**: Ordered list of the first 5 actions to take.

Remember: CTF challenges are designed to be solved. Think creatively.
PROMPT
                    ],
                    [
                        'name'            => 'Exploitation Walkthrough',
                        'description'     => 'Guide through the exploitation technique step by step.',
                        'prompt_template' => <<<'PROMPT'
You are a CTF mentor guiding through the exploitation phase.

Challenge analysis: {{last_output}}
Challenge: {{target}}
Context / progress so far: {{context}}

Provide a structured exploitation walkthrough:

1. **Setup**: Environment requirements, Python version, library installs.
2. **Step-by-Step Exploit**:
   - Show each command or code snippet with explanation of what it does and why.
   - For crypto: show the math/algorithm before the code.
   - For binary: show checksec output, explain protections (NX, ASLR, canary, PIE) and how to bypass.
   - For web: show the exact HTTP request/payload.
3. **Common Pitfalls**: What typically goes wrong at each step and how to debug.
4. **Intermediate Checkpoints**: Expected output at each stage so the solver knows they're on track.
5. **Alternative Approaches**: If the primary method is blocked, provide a second path.
6. **Concept Explanation**: After the solution, explain the underlying security concept (teach, don't just solve).
PROMPT
                    ],
                    [
                        'name'            => 'Flag Capture & Learning Summary',
                        'description'     => 'Confirm flag format, write a post-solve write-up, and extract learning points.',
                        'prompt_template' => <<<'PROMPT'
You are a CTF mentor writing a post-solve analysis.

Exploitation walkthrough: {{last_output}}
Challenge: {{target}}
Context: {{context}}

Produce the post-solve summary:

1. **Flag Submission**: Describe the expected flag format (CTF{...}, flag{...}) and where/how to submit.
2. **Write-Up** (publishable format):
   - Challenge name and category
   - Brief problem description
   - Solution approach (2–3 paragraphs)
   - Key code/commands used
   - Flag
3. **Learning Points**:
   - The core vulnerability or algorithm exploited
   - Real-world equivalent of this vulnerability
   - Defensive countermeasure in production systems
4. **Difficulty Assessment**: Rate 1–10 and explain why.
5. **Further Practice**: Recommend 3 similar challenges or platforms (HackTheBox, PicoCTF, TryHackMe) to reinforce this skill.
PROMPT
                    ],
                ],
            ],

            // ── 8. Bug Bounty Hunter ─────────────────────────────────────────────
            [
                'name'        => 'Bug Bounty Hunter',
                'slug'        => 'bug-bounty-hunter',
                'description' => 'Systematic bug bounty hunting methodology from scope analysis through responsible disclosure, covering reconnaissance, vulnerability discovery, and professional report writing.',
                'category'    => 'vulnerability-management',
                'steps' => [
                    [
                        'name'            => 'Program Scope Analysis & Recon',
                        'description'     => 'Review program scope, identify high-value targets, and perform reconnaissance.',
                        'prompt_template' => <<<'PROMPT'
You are a professional bug bounty hunter analysing a new program.

Target program / asset: {{target}}
Program context / scope information: {{context}}

Perform scope analysis and reconnaissance planning:

1. **In-Scope Assets**: Parse and list all explicitly in-scope domains, subdomains, mobile apps, APIs.
2. **Out-of-Scope**: Identify what is explicitly excluded (third-party infrastructure, acquisition targets, etc.).
3. **High-Value Targets**: Rank in-scope assets by likely bug bounty payout (authentication flows, payment handling, admin panels, API endpoints).
4. **Recon Plan**:
   - Subdomain enumeration: subfinder, amass, crt.sh, dnsx
   - Technology fingerprinting: whatweb, wappalyzer, shodan
   - JavaScript analysis: LinkFinder, JSParser for hidden endpoints
   - GitHub recon: search for hardcoded secrets in org repositories
5. **Previous Disclosures**: Suggest searching HackerOne/Bugcrowd disclosed reports for this program to avoid duplicates.
6. **Focus Areas**: Based on the technology stack, prioritise the most common vulnerability classes.
PROMPT
                    ],
                    [
                        'name'            => 'Vulnerability Discovery & Testing',
                        'description'     => 'Systematic testing across OWASP Top 10 and business logic flaws within scope.',
                        'prompt_template' => <<<'PROMPT'
You are conducting systematic vulnerability testing for a bug bounty program.

Recon findings: {{last_output}}
Target: {{target}}
Context: {{context}}

Guide systematic vulnerability discovery:

1. **Authentication Testing**:
   - Password reset flow flaws (token reuse, predictable tokens, host header injection)
   - OAuth 2.0 misconfigurations (open redirect, state parameter bypass, implicit flow abuse)
   - MFA bypass techniques (backup code brute-force, SIM swap indicators)

2. **Injection Testing** (in-scope parameters only):
   - SQL injection (error-based, blind, time-based)
   - NoSQL injection (MongoDB operator injection)
   - Server-side template injection (SSTI): `{{7*7}}`, `${7*7}`, `<%= 7*7 %>`
   - SSRF: internal metadata endpoints, cloud IMDS (`169.254.169.254`)

3. **Access Control**:
   - IDOR: change numeric IDs, UUIDs in API requests
   - Privilege escalation: access admin endpoints with low-privilege tokens
   - BOLA/BFLA (Broken Object/Function Level Authorisation)

4. **Business Logic**:
   - Negative values in payment flows
   - Race conditions in coupon/voucher redemption
   - Mass assignment vulnerabilities

For each tested vector, record: URL, parameter, payload, response, and outcome.
PROMPT
                    ],
                    [
                        'name'            => 'Professional Disclosure Report',
                        'description'     => 'Write a high-quality vulnerability report for submission to the bug bounty program.',
                        'prompt_template' => <<<'PROMPT'
You are a security researcher writing a bug bounty vulnerability disclosure report.

Vulnerability findings: {{last_output}}
Target program: {{target}}
Context: {{context}}

Write a professional, platform-ready vulnerability report:

**Title**: [Vulnerability Type] in [Component] leads to [Impact]
(e.g. "Stored XSS in /profile/bio allows attacker to steal session cookies of any user")

**Severity**: Critical / High / Medium / Low / Informational
**CVSS v3.1 Vector & Score**:

**Summary** (2–3 sentences):
Plain-language description of the vulnerability and its impact.

**Steps to Reproduce**:
1. [Exact step with URL, method, parameters]
2. [Next step]
3. [Observed result]

**Proof of Concept**:
```
[HTTP request / payload / screenshot description]
```

**Impact**:
What can an attacker achieve? (account takeover, data exfiltration, privilege escalation, financial fraud, etc.)
Estimated number of affected users.

**Remediation Suggestion**:
Developer-ready fix recommendation.

**Additional Notes**:
- Not reproducible after: [date]
- Tested on: [browser/environment]
- Not tested against production user data

**References**: CWE, OWASP, CVE (if applicable)

Tone: professional, factual, collaborative — not adversarial.
PROMPT
                    ],
                ],
            ],
        ];
    }
}
