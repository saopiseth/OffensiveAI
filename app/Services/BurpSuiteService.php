<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BurpSuiteService
{
    /**
     * Pull live data from Burp Suite Pro REST API and return formatted context vars.
     * Returns an empty array (with a warning log) if Burp is unreachable.
     *
     * @param  array{enabled:bool,proxy:string,api_url:string,api_key:string}  $cfg
     * @return array{burp_proxy:string,burp_issues:string,burp_endpoints:string,burp_summary:string}
     */
    public function fetchContext(array $cfg): array
    {
        $apiUrl = rtrim($cfg['api_url'] ?? 'http://127.0.0.1:1337', '/');
        $apiKey = $cfg['api_key'] ?? '';
        $proxy  = $cfg['proxy']   ?? 'http://127.0.0.1:8080';

        $headers = array_filter(['Authorization' => $apiKey]);
        $timeout = 5;

        $issues    = $this->getIssues($apiUrl, $headers, $timeout);
        $endpoints = $this->getEndpoints($apiUrl, $headers, $timeout);

        return [
            'burp_proxy'     => $proxy,
            'burp_issues'    => $this->formatIssues($issues),
            'burp_endpoints' => $this->formatEndpoints($endpoints),
            'burp_summary'   => $this->buildSummary($issues, $endpoints, $proxy),
        ];
    }

    // ── API calls ─────────────────────────────────────────────────────────────

    private function getIssues(string $base, array $headers, int $timeout): array
    {
        try {
            $r = Http::withHeaders($headers)->timeout($timeout)
                     ->get("{$base}/v0.1/scanner/issues");

            if ($r->successful()) {
                return $r->json('issues', []);
            }
        } catch (\Throwable $e) {
            Log::warning("BurpSuite: could not fetch issues — {$e->getMessage()}");
        }
        return [];
    }

    private function getEndpoints(string $base, array $headers, int $timeout): array
    {
        // Try proxy history first; fall back to site-map tree
        try {
            $r = Http::withHeaders($headers)->timeout($timeout)
                     ->get("{$base}/v0.1/proxy/history", ['count' => 100]);

            if ($r->successful()) {
                return $this->extractEndpointsFromHistory($r->json(null, []));
            }
        } catch (\Throwable $e) {
            Log::warning("BurpSuite: could not fetch proxy history — {$e->getMessage()}");
        }

        try {
            $r = Http::withHeaders($headers)->timeout($timeout)
                     ->get("{$base}/v0.1/target/site-map/tree");

            if ($r->successful()) {
                return $this->extractEndpointsFromSiteMap($r->json(null, []));
            }
        } catch (\Throwable $e) {
            Log::warning("BurpSuite: could not fetch site map — {$e->getMessage()}");
        }

        return [];
    }

    // ── Extraction helpers ────────────────────────────────────────────────────

    private function extractEndpointsFromHistory(array $history): array
    {
        $seen = [];
        foreach ($history as $entry) {
            $method = strtoupper($entry['request']['method'] ?? 'GET');
            $url    = $entry['request']['url'] ?? '';
            if (!$url) continue;
            $key = "{$method} {$url}";
            if (!isset($seen[$key])) {
                $seen[$key] = [
                    'method'  => $method,
                    'url'     => $url,
                    'status'  => $entry['response']['status_code'] ?? null,
                ];
            }
        }
        return array_values($seen);
    }

    private function extractEndpointsFromSiteMap(array $tree): array
    {
        $results = [];
        $this->flattenSiteMap($tree, $results);
        return $results;
    }

    private function flattenSiteMap(array $node, array &$out): void
    {
        if (!empty($node['url'])) {
            $out[] = ['method' => 'GET', 'url' => $node['url'], 'status' => null];
        }
        foreach ($node['children'] ?? [] as $child) {
            $this->flattenSiteMap($child, $out);
        }
    }

    // ── Formatting ────────────────────────────────────────────────────────────

    private function formatIssues(array $issues): string
    {
        if (empty($issues)) {
            return 'No scanner issues found (Burp scanner may not have run yet).';
        }

        // Sort: Critical → High → Medium → Low → Info
        $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'information' => 4];
        usort($issues, fn($a, $b) =>
            ($order[strtolower($a['severity'] ?? '')] ?? 5) <=>
            ($order[strtolower($b['severity'] ?? '')] ?? 5)
        );

        $lines = ["Burp Suite scanner findings (" . count($issues) . " issues):"];
        foreach (array_slice($issues, 0, 30) as $issue) {
            $sev    = strtoupper($issue['severity'] ?? 'INFO');
            $name   = $issue['issue_type']['name'] ?? ($issue['name'] ?? 'Unknown');
            $path   = $issue['path']               ?? '';
            $conf   = $issue['confidence']         ?? '';
            $lines[] = "- [{$sev}] {$name} — {$path}" . ($conf ? " (confidence: {$conf})" : '');
        }

        if (count($issues) > 30) {
            $lines[] = '  … and ' . (count($issues) - 30) . ' more issues';
        }

        return implode("\n", $lines);
    }

    private function formatEndpoints(array $endpoints): string
    {
        if (empty($endpoints)) {
            return 'No endpoints captured in Burp proxy history yet.';
        }

        $lines = ["Discovered endpoints (" . count($endpoints) . "):"];
        foreach (array_slice($endpoints, 0, 60) as $ep) {
            $status = $ep['status'] ? " [{$ep['status']}]" : '';
            $lines[] = "- {$ep['method']} {$ep['url']}{$status}";
        }

        if (count($endpoints) > 60) {
            $lines[] = '  … and ' . (count($endpoints) - 60) . ' more endpoints';
        }

        return implode("\n", $lines);
    }

    private function buildSummary(array $issues, array $endpoints, string $proxy): string
    {
        $critHigh = count(array_filter($issues, fn($i) =>
            in_array(strtolower($i['severity'] ?? ''), ['critical', 'high'])
        ));

        $parts = [];
        if (!empty($issues)) {
            $parts[] = count($issues) . ' scanner issue(s) found (' . $critHigh . ' critical/high)';
        }
        if (!empty($endpoints)) {
            $parts[] = count($endpoints) . ' endpoint(s) discovered';
        }
        $parts[] = "Burp proxy listening at {$proxy}";

        return 'Burp Suite integration active: ' . implode('; ', $parts) . '.';
    }
}
