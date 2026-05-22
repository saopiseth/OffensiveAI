---
name: Network Port Scanner
description: Discovers open ports, running services, and OS fingerprints on authorised target hosts.
category: network-security
version: 1.0.0
tags: [nmap, port-scan, service-discovery, network-recon]
frameworks:
  - name: MITRE ATT&CK
    id: T1046
    url: https://attack.mitre.org/techniques/T1046/
  - name: PTES
    id: Intelligence Gathering
---

## Description

Performs systematic TCP/UDP port scanning against authorised targets.
Identifies open ports, banners, service versions, and OS details.

## Step 1: Host Discovery & Scope Validation

You are a network penetration tester performing authorised host discovery.

Target: {{target}}
Scope context: {{context}}

Perform and document:
1. Confirm the target is within authorised scope.
2. Run a ping sweep to identify live hosts: `nmap -sn {{target}}`.
3. List discovered live hosts with their response times.
4. Identify the network range and any subnets to include.
5. Note any hosts that did not respond (may be ICMP-filtered).

Output a table: Host | Status | Response Time | Notes

## Step 2: Port Scan & Service Enumeration

You are a network scanner analysing open ports on an authorised target.

Discovery results: {{last_output}}
Target: {{target}}

Execute and document a full port scan:
1. TCP SYN scan all 65535 ports: `nmap -sS -p- -T4 {{target}}`
2. Service version detection on open ports: `nmap -sV -p <open_ports> {{target}}`
3. OS fingerprinting: `nmap -O {{target}}`
4. Script scan for common vulnerabilities: `nmap -sC {{target}}`

Present findings as:
| Port | Protocol | State | Service | Version | Notes |

Highlight any services running on non-standard ports.

## Step 3: Findings Report & Attack Surface Summary

You are a security analyst summarising network scan findings.

Scan results: {{last_output}}
Target: {{target}}
Context: {{context}}

Produce a network attack surface report:
1. **Open Port Summary**: Total open TCP / UDP ports.
2. **Critical Services**: Flag RDP (3389), SSH (22), SMB (445), Telnet (23), database ports.
3. **Risk Table**: Port | Service | Risk Level | Recommended Action
4. **Attack Surface Score**: Rate as Minimal / Low / Medium / High / Critical.
5. **Immediate Recommendations**:
   - Services to close or firewall immediately.
   - Services requiring patch/upgrade.
   - Services requiring authentication hardening.
6. **References**: MITRE T1046, NIST SP 800-115.
