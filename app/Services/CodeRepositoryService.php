<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CodeRepositoryService
{
    // ── Limits ────────────────────────────────────────────────────────────────
    private const MAX_FILES       = 22;
    private const MAX_FILE_CHARS  = 5000;
    private const MAX_TOTAL_CHARS = 38000;
    private const TIMEOUT         = 12;

    // File extensions worth reading for security review
    private const CODE_EXTS = [
        'php','py','rb','java','js','ts','go','cs','cpp','c',
        'sh','bash','ps1','env','yaml','yml','xml','json','toml',
        'conf','ini','config','properties','dockerfile',
    ];

    // Path/name patterns ranked by security relevance (lower = higher priority)
    private const PRIORITY = [
        0  => ['auth', 'login', 'register', 'password', 'jwt', 'token', 'oauth', 'session', 'credential'],
        1  => ['config', 'setting', 'database', 'db', '.env', 'secret', 'key'],
        2  => ['route', 'url', 'endpoint', 'api', 'middleware', 'guard', 'gate'],
        3  => ['user', 'admin', 'account', 'role', 'permission', 'acl'],
        4  => ['upload', 'file', 'download', 'attachment', 'media'],
        5  => ['sql', 'query', 'model', 'repository', 'dao', 'orm'],
        6  => ['exec', 'command', 'shell', 'process', 'system', 'eval', 'serialize'],
        7  => ['csrf', 'cors', 'xss', 'sanitize', 'validate', 'filter', 'escape'],
        8  => ['xml', 'parser', 'deserialize', 'pickle', 'marshal'],
        9  => ['controller', 'handler', 'action', 'view', 'template'],
        10 => ['bootstrap', 'startup', 'init', 'main', 'app', 'index'],
    ];

    // ── Public entry point ────────────────────────────────────────────────────

    /**
     * @param  array{enabled:bool,url:string,branch:string,token:string,username:string}  $cfg
     * @return array{repo_url,repo_branch,repo_provider,repo_structure,repo_code,repo_summary}
     */
    public function fetchContext(array $cfg): array
    {
        $url      = trim($cfg['url']      ?? '');
        $branch   = trim($cfg['branch']   ?? 'main') ?: 'main';
        $token    = trim($cfg['token']    ?? '');
        $username = trim($cfg['username'] ?? '');

        if (! $url) {
            return $this->empty('', $branch, '');
        }

        try {
            $info = $this->parseUrl($url);

            $tree   = $this->fetchTree($info, $branch, $token, $username);
            $picked = $this->pickFiles($tree);
            $code   = $this->fetchContents($picked, $info, $branch, $token, $username);

            return [
                'repo_url'       => $url,
                'repo_branch'    => $branch,
                'repo_provider'  => $info['provider'],
                'repo_structure' => $this->formatTree($tree),
                'repo_code'      => $this->formatCode($code),
                'repo_summary'   => $this->summary($info, $branch, $tree, $code),
            ];
        } catch (\Throwable $e) {
            Log::warning("CodeRepository: {$url} — {$e->getMessage()}");
            return $this->empty($url, $branch, "Repository access failed: {$e->getMessage()}");
        }
    }

    // ── URL parsing ───────────────────────────────────────────────────────────

    private function parseUrl(string $raw): array
    {
        $url    = rtrim(preg_replace('/\.git$/i', '', $raw), '/');
        $parsed = parse_url($url);
        $host   = strtolower($parsed['host'] ?? '');
        $scheme = $parsed['scheme'] ?? 'https';
        $parts  = array_values(array_filter(explode('/', trim($parsed['path'] ?? '', '/'))));

        if (str_contains($host, 'github')) {
            return [
                'provider' => 'github',
                'api'      => 'https://api.github.com',
                'owner'    => $parts[0] ?? '',
                'repo'     => $parts[1] ?? '',
            ];
        }

        if (str_contains($host, 'bitbucket')) {
            return [
                'provider'  => 'bitbucket',
                'api'       => 'https://api.bitbucket.org/2.0',
                'workspace' => $parts[0] ?? '',
                'repo'      => $parts[1] ?? '',
            ];
        }

        // GitLab: gitlab.com or any self-hosted instance
        return [
            'provider'     => 'gitlab',
            'api'          => "{$scheme}://{$host}/api/v4",
            'project_path' => implode('/', $parts), // e.g. "group/subgroup/repo"
        ];
    }

    // ── Tree fetching ─────────────────────────────────────────────────────────

    private function fetchTree(array $info, string $branch, string $token, string $username): array
    {
        return match ($info['provider']) {
            'github'    => $this->githubTree($info, $branch, $token),
            'bitbucket' => $this->bitbucketTree($info, $branch, $token, $username),
            default     => $this->gitlabTree($info, $branch, $token),
        };
    }

    private function githubTree(array $info, string $branch, string $token): array
    {
        $headers = ['Accept' => 'application/vnd.github.v3+json'];
        if ($token) $headers['Authorization'] = "Bearer {$token}";

        $r = Http::withHeaders($headers)->timeout(self::TIMEOUT)
                 ->get("{$info['api']}/repos/{$info['owner']}/{$info['repo']}/git/trees/{$branch}",
                       ['recursive' => 1]);

        $r->throw();

        return collect($r->json('tree', []))
            ->where('type', 'blob')
            ->pluck('path')
            ->all();
    }

    private function gitlabTree(array $info, string $branch, string $token): array
    {
        $headers = [];
        if ($token) $headers['PRIVATE-TOKEN'] = $token;

        $project = rawurlencode($info['project_path']);
        $files   = [];
        $page    = 1;

        do {
            $r = Http::withHeaders($headers)->timeout(self::TIMEOUT)
                     ->get("{$info['api']}/projects/{$project}/repository/tree", [
                         'recursive' => true,
                         'ref'       => $branch,
                         'per_page'  => 100,
                         'page'      => $page,
                     ]);

            $r->throw();
            $items = $r->json(null, []);

            foreach ($items as $item) {
                if (($item['type'] ?? '') === 'blob') {
                    $files[] = $item['path'];
                }
            }

            $nextPage = (int) ($r->header('X-Next-Page') ?: 0);
            $page     = $nextPage > $page ? $nextPage : 0;
        } while ($page > 0 && count($files) < 500);

        return $files;
    }

    private function bitbucketTree(array $info, string $branch, string $token, string $username): array
    {
        $headers = [];
        if ($username && $token) {
            $headers['Authorization'] = 'Basic ' . base64_encode("{$username}:{$token}");
        } elseif ($token) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        $files   = [];
        $nextUrl = "{$info['api']}/repositories/{$info['workspace']}/{$info['repo']}/src/{$branch}/";

        while ($nextUrl && count($files) < 400) {
            $r = Http::withHeaders($headers)->timeout(self::TIMEOUT)
                     ->get($nextUrl, ['pagelen' => 100, 'q' => 'type="commit_file"']);

            $r->throw();
            $body = $r->json(null, []);

            foreach ($body['values'] ?? [] as $v) {
                if (($v['type'] ?? '') === 'commit_file') {
                    $files[] = $v['path'];
                }
            }

            $nextUrl = $body['next'] ?? null;
        }

        return $files;
    }

    // ── File selection ────────────────────────────────────────────────────────

    private function pickFiles(array $allPaths): array
    {
        // Filter to readable code extensions
        $filtered = array_filter($allPaths, function (string $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $base = strtolower(basename($path, ".{$ext}"));
            return in_array($ext, self::CODE_EXTS, true)
                || in_array($base, ['dockerfile', '.env', '.env.example', 'makefile'], true);
        });

        // Score each file by priority patterns
        $scored = [];
        foreach ($filtered as $path) {
            $lower = strtolower($path);
            $score = 999;

            foreach (self::PRIORITY as $rank => $patterns) {
                foreach ($patterns as $pat) {
                    if (str_contains($lower, $pat)) {
                        $score = min($score, $rank);
                        break 2;
                    }
                }
            }

            // Prefer shallower paths (higher chance of being a core file)
            $depth = substr_count($path, '/');
            $scored[$path] = $score * 100 + $depth;
        }

        asort($scored);

        return array_keys(array_slice($scored, 0, self::MAX_FILES, true));
    }

    // ── Content fetching ──────────────────────────────────────────────────────

    private function fetchContents(
        array  $paths,
        array  $info,
        string $branch,
        string $token,
        string $username
    ): array {
        $results    = [];
        $totalChars = 0;

        foreach ($paths as $path) {
            if ($totalChars >= self::MAX_TOTAL_CHARS) break;

            try {
                $content = match ($info['provider']) {
                    'github'    => $this->githubFile($info, $branch, $path, $token),
                    'bitbucket' => $this->bitbucketFile($info, $branch, $path, $token, $username),
                    default     => $this->gitlabFile($info, $branch, $path, $token),
                };

                if ($content === null) continue;

                // Truncate oversized files
                if (strlen($content) > self::MAX_FILE_CHARS) {
                    $content = substr($content, 0, self::MAX_FILE_CHARS)
                        . "\n[... truncated — " . strlen($content) . " chars total]";
                }

                $results[$path] = $content;
                $totalChars    += strlen($content);
            } catch (\Throwable $e) {
                Log::debug("CodeRepository: skip {$path} — {$e->getMessage()}");
            }
        }

        return $results;
    }

    private function githubFile(array $info, string $branch, string $path, string $token): ?string
    {
        $headers = ['Accept' => 'application/vnd.github.v3+json'];
        if ($token) $headers['Authorization'] = "Bearer {$token}";

        $r = Http::withHeaders($headers)->timeout(self::TIMEOUT)
                 ->get("{$info['api']}/repos/{$info['owner']}/{$info['repo']}/contents/{$path}",
                       ['ref' => $branch]);

        if (! $r->successful()) return null;

        $data = $r->json(null, []);

        // GitHub returns base64-encoded content
        if (($data['encoding'] ?? '') === 'base64' && isset($data['content'])) {
            return base64_decode(str_replace("\n", '', $data['content']));
        }

        return null;
    }

    private function gitlabFile(array $info, string $branch, string $path, string $token): ?string
    {
        $headers = [];
        if ($token) $headers['PRIVATE-TOKEN'] = $token;

        $project  = rawurlencode($info['project_path']);
        $filePath = rawurlencode($path);

        $r = Http::withHeaders($headers)->timeout(self::TIMEOUT)
                 ->get("{$info['api']}/projects/{$project}/repository/files/{$filePath}/raw",
                       ['ref' => $branch]);

        return $r->successful() ? $r->body() : null;
    }

    private function bitbucketFile(
        array  $info,
        string $branch,
        string $path,
        string $token,
        string $username
    ): ?string {
        $headers = [];
        if ($username && $token) {
            $headers['Authorization'] = 'Basic ' . base64_encode("{$username}:{$token}");
        } elseif ($token) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        $r = Http::withHeaders($headers)->timeout(self::TIMEOUT)
                 ->get("{$info['api']}/repositories/{$info['workspace']}/{$info['repo']}/src/{$branch}/{$path}");

        return $r->successful() ? $r->body() : null;
    }

    // ── Formatting ────────────────────────────────────────────────────────────

    private function formatTree(array $paths): string
    {
        if (empty($paths)) return 'Repository tree unavailable.';

        // Build a condensed directory listing (max 120 paths shown)
        $shown   = array_slice($paths, 0, 120);
        $lines   = ['Repository structure (' . count($paths) . ' files):'];

        // Group by top-level dir
        $dirs = [];
        foreach ($shown as $p) {
            $parts = explode('/', $p, 2);
            $dirs[$parts[0] ?? ''][] = $parts[1] ?? $p;
        }

        foreach ($dirs as $dir => $children) {
            $lines[] = $dir . '/ (' . count($children) . ' files)';
            foreach (array_slice($children, 0, 8) as $child) {
                $lines[] = "  ├─ {$child}";
            }
            if (count($children) > 8) {
                $lines[] = '  └─ … and ' . (count($children) - 8) . ' more';
            }
        }

        if (count($paths) > 120) {
            $lines[] = '… and ' . (count($paths) - 120) . ' more files';
        }

        return implode("\n", $lines);
    }

    private function formatCode(array $files): string
    {
        if (empty($files)) {
            return 'No source files could be retrieved.';
        }

        $parts = ["=== SOURCE FILES RETRIEVED FOR SECURITY REVIEW ===\n"];

        foreach ($files as $path => $content) {
            $parts[] = str_repeat('─', 60);
            $parts[] = "FILE: {$path}";
            $parts[] = str_repeat('─', 60);
            $parts[] = $content;
            $parts[] = '';
        }

        return implode("\n", $parts);
    }

    private function summary(array $info, string $branch, array $tree, array $code): string
    {
        $totalFiles   = count($tree);
        $fetched      = count($code);
        $provider     = ucfirst($info['provider']);
        $repoName     = match ($info['provider']) {
            'github'    => "{$info['owner']}/{$info['repo']}",
            'bitbucket' => "{$info['workspace']}/{$info['repo']}",
            default     => $info['project_path'],
        };

        return "{$provider} repository `{$repoName}` (branch: {$branch}) — "
             . "{$totalFiles} total files; {$fetched} security-relevant files fetched for code review.";
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function empty(string $url, string $branch, string $reason): array
    {
        return [
            'repo_url'       => $url,
            'repo_branch'    => $branch,
            'repo_provider'  => '',
            'repo_structure' => '',
            'repo_code'      => '',
            'repo_summary'   => $reason ?: 'No repository configured.',
        ];
    }
}
