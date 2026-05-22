<?php

namespace App\Services;

use App\Models\Execution;
use App\Models\Finding;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

class WordReportService
{
    // Page content width in twips (A4 portrait minus 2.5 cm margins on each side)
    private const PAGE_WIDTH = 9026;

    // Brand colours
    private const C_HEADING1      = '111827';
    private const C_HEADING2      = '6D28D9';
    private const C_HEADING3      = '7C3AED';
    private const C_HEADING4      = '374151';
    private const C_BODY          = '1F2937';
    private const C_MUTED         = '6B7280';
    private const C_TABLE_HDR     = '4C1D95';
    private const C_TABLE_HDR_TXT = 'FFFFFF';
    private const C_ROW_ALT       = 'F5F3FF';
    private const C_CODE_TXT      = '065F46';
    private const C_CODE_BG       = 'F0FDF4';
    private const C_INLINE_CODE   = '6D28D9';
    private const C_SEPARATOR     = 'E5E7EB';
    private const C_COVER_LINE    = '7C3AED';

    private const CHECKLIST_COLORS = [
        '✓' => '15803D',   // green
        '✗' => 'B91C1C',   // red
        '▶' => '1D4ED8',   // blue
        '○' => 'B45309',   // amber
        '?' => '6B7280',   // gray
    ];

    private const SEVERITY_COLORS = [
        'critical'      => 'B91C1C',
        'high'          => 'C2410C',
        'medium'        => 'B45309',
        'low'           => '1D4ED8',
        'informational' => '6B7280',
        'info'          => '6B7280',
        'pass'          => '15803D',
        'finding'       => 'B91C1C',
        'not tested'    => '6B7280',
        // Checklist status colours
        'completed'     => '15803D',
        'failed'        => 'B91C1C',
        'running'       => '1D4ED8',
        'pending'       => 'B45309',
        'unknown'       => '6B7280',
    ];

    private PhpWord $word;
    private $section;

    public function clearCache(string $executionId): void
    {
        @unlink(storage_path('app/reports/' . $executionId . '.docx'));
    }

    private function cachedReportPath(Execution $execution): string
    {
        $dir = storage_path('app/reports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . DIRECTORY_SEPARATOR . $execution->id . '.docx';
    }

    public function generate(Execution $execution): string
    {
        $cached = $this->cachedReportPath($execution);
        if (file_exists($cached)) {
            return $cached;
        }

        $content = $execution->output_data['report']['content'] ?? '';
        if (empty($content)) {
            throw new \RuntimeException('No report content found. Generate the AI report first.');
        }

        // Use bundled PclZip so the native ZipArchive extension is not required.
        Settings::setZipClass(Settings::PCLZIP);
        // Enable XML escaping (off by default for BC). Without this, & < > in
        // heading text are written raw via writeRaw(), producing invalid XML that
        // Word refuses to open.
        Settings::setOutputEscapingEnabled(true);

        $this->word = new PhpWord();
        $this->word->getSettings()->setUpdateFields(true);
        $this->word->setDefaultFontName('Calibri');
        $this->word->setDefaultFontSize(11);
        $this->registerStyles();

        // Cover page (own section with no header/footer)
        $coverSection = $this->word->addSection([
            'paperSize'  => 'A4',
            'marginTop'  => 1440,
            'marginLeft' => 1440,
            'marginRight'=> 1440,
            'marginBottom'=> 1440,
        ]);
        $this->addCoverPage($coverSection, $execution, $content);

        // Content section
        $this->section = $this->word->addSection([
            'paperSize'   => 'A4',
            'marginTop'   => 1440,
            'marginLeft'  => 1440,
            'marginRight' => 1440,
            'marginBottom'=> 1440,
        ]);

        // Split main report from appendix so we can insert a page break between them
        $appendixMarker = "\n\n---\n\n## Appendix A";
        $appendixPos    = strpos($content, $appendixMarker);
        if ($appendixPos !== false) {
            $mainContent     = substr($content, 0, $appendixPos);
            $appendixContent = substr($content, $appendixPos + strlen("\n\n---\n\n"));
        } else {
            $mainContent     = $content;
            $appendixContent = null;
        }

        $findings = Finding::where('execution_id', $execution->id)->orderBy('finding_order')->get();

        $this->parseMarkdownWithCharts($mainContent, $findings);


        if ($appendixContent !== null) {
            $this->section->addPageBreak();
            $this->addAppendix($appendixContent);
        }

        if ($findings->isNotEmpty()) {
            $this->section->addPageBreak();
            $this->addTechnicalFindings($findings);
        }

        IOFactory::createWriter($this->word, 'Word2007')->save($cached);
        return $cached;
    }

    // ── Style registration ─────────────────────────────────────────────────────

    private function registerStyles(): void
    {
        $this->word->addTitleStyle(1, [
            'name'  => 'Calibri Light',
            'size'  => 28,
            'bold'  => true,
            'color' => self::C_HEADING1,
        ], ['spaceAfter' => 240, 'spaceBefore' => 0]);

        $this->word->addTitleStyle(2, [
            'name'  => 'Calibri Light',
            'size'  => 16,
            'bold'  => true,
            'color' => self::C_HEADING2,
        ], ['spaceAfter' => 120, 'spaceBefore' => 360]);

        $this->word->addTitleStyle(3, [
            'name'  => 'Calibri',
            'size'  => 13,
            'bold'  => true,
            'color' => self::C_HEADING3,
        ], ['spaceAfter' => 80, 'spaceBefore' => 280]);

        $this->word->addTitleStyle(4, [
            'name'  => 'Calibri',
            'size'  => 11,
            'bold'  => true,
            'color' => self::C_HEADING4,
        ], ['spaceAfter' => 60, 'spaceBefore' => 200]);

        $this->word->addParagraphStyle('Body', [
            'spaceAfter'  => 120,
            'spaceBefore' => 0,
            'lineHeight'  => 1.15,
        ]);
        $this->word->addParagraphStyle('Code', [
            'spaceAfter'  => 160,
            'spaceBefore' => 160,
            'indentation' => ['left' => 360],
        ]);
    }

    // ── Cover page ─────────────────────────────────────────────────────────────

    private function addCoverPage($section, Execution $execution, string $content): void
    {
        $title = 'Security Assessment Report';
        if (preg_match('/^# (.+)$/m', $content, $m)) {
            $title = trim($m[1]);
        }

        $workflowName = $execution->workflow?->name ?? 'Security Assessment';
        $targetInfo   = $this->resolveTarget($execution);
        $date         = ($execution->completed_at ?? now())->format('F j, Y');

        // Vertical spacer
        for ($i = 0; $i < 6; $i++) {
            $section->addTextBreak();
        }

        // Large title
        $section->addTitle($this->sanitizeHeading($workflowName), 1);

        // Coloured underline (simulate with table row)
        $accent = $section->addTable(['borderSize' => 0, 'cellMargin' => 0]);
        $accentRow = $accent->addRow(60);
        $accentRow->addCell(self::PAGE_WIDTH, ['bgColor' => self::C_COVER_LINE])->addText('');

        $section->addTextBreak();

        // Subtitle
        $sub = $section->addTextRun(['spaceAfter' => 320]);
        $sub->addText('Security Assessment Report', [
            'name'   => 'Calibri Light',
            'size'   => 20,
            'color'  => self::C_MUTED,
        ]);

        $section->addTextBreak(3);

        // Details table (two-column, borderless)
        $details = $section->addTable(['borderSize' => 0, 'cellMargin' => 80]);
        foreach ([
            ['Target',          $targetInfo],
            ['Date',            $date],
            ['Standard',        'OWASP WSTG v4.2 · CVSS v3.1 · MITRE CWE'],
            ['Classification',  'CONFIDENTIAL'],
        ] as [$label, $value]) {
            $r = $details->addRow();
            $r->addCell(2200)->addText($label, ['bold' => true, 'size' => 11, 'color' => self::C_MUTED]);
            $r->addCell(6800)->addText($value,  ['size' => 11, 'color' => self::C_BODY]);
        }

        $section->addTextBreak(8);

        // Footer notice
        $notice = $section->addTextRun(['spaceAfter' => 0]);
        $notice->addText(
            'CONFIDENTIAL — This document contains sensitive security information. '
            . 'Unauthorised distribution or reproduction is strictly prohibited.',
            ['size' => 9, 'italic' => true, 'color' => 'DC2626']
        );
    }

    // ── Markdown + inline chart injection ─────────────────────────────────────

    /**
     * Parse markdown, inserting chart images directly after the
     * "## Executive Summary" heading.
     */
    private function parseMarkdownWithCharts(string $content, Collection $findings): void
    {
        // Split at the Executive Summary heading so we can inject charts right after it
        $marker    = "\n## Executive Summary";
        $markerPos = strpos($content, $marker);

        if ($markerPos !== false && $findings->isNotEmpty()) {
            // Everything up to and including the Executive Summary heading
            $before = substr($content, 0, $markerPos + strlen($marker));
            $after  = substr($content, $markerPos + strlen($marker));

            $this->parseMarkdown($before);
            $this->addExecutiveSummaryCharts($findings);
            $this->parseMarkdown($after);
        } else {
            $this->parseMarkdown($content);
        }
    }

    private function addExecutiveSummaryCharts(Collection $findings): void
    {
        $this->section->addTextBreak();

        $intro = $this->section->addTextRun('Body');
        $intro->addText(
            'The following charts summarise the distribution of identified vulnerabilities '
            . 'by severity, OWASP category, and CWE classification.',
            ['size' => 10, 'italic' => true, 'color' => self::C_MUTED]
        );
        $this->section->addTextBreak();

        // Build aggregates
        $severityOrder   = ['Critical' => 0, 'High' => 1, 'Medium' => 2, 'Low' => 3, 'Informational' => 4];
        $severityColors  = ['Critical' => 'EF4444', 'High' => 'F97316', 'Medium' => 'EAB308', 'Low' => '3B82F6', 'Informational' => '6B7280'];
        $palette         = ['7C3AED','6D28D9','2563EB','0891B2','059669','D97706','DC2626','DB2777','4F46E5','0D9488'];

        $sevCounts = $findings->groupBy('severity')
            ->map(fn($g) => $g->count())
            ->sortBy(fn($_, $k) => $severityOrder[$k] ?? 99);

        $owaspCounts = $findings->filter(fn($f) => !empty($f->owasp_category))
            ->groupBy(fn($f) => $this->wordShortLabel($f->owasp_category))
            ->map(fn($g) => $g->count())->sortByDesc(fn($c) => $c);

        $cweCounts = $findings->filter(fn($f) => !empty($f->cwe_id))
            ->groupBy(fn($f) => $this->wordShortLabel($f->cwe_id))
            ->map(fn($g) => $g->count())->sortByDesc(fn($c) => $c);

        // Build all chart configs upfront, then fetch in parallel
        $configs = [];

        if ($sevCounts->isNotEmpty()) {
            $labels = $sevCounts->keys()->values()->toArray();
            $data   = $sevCounts->values()->toArray();
            $configs['severity'] = [
                'type' => 'doughnut',
                'data' => [
                    'labels'   => $labels,
                    'datasets' => [['data' => $data, 'backgroundColor' => array_map(fn($l) => '#' . ($severityColors[$l] ?? '6B7280'), $labels)]],
                ],
                'options' => [
                    'plugins' => [
                        'title'  => ['display' => true, 'text' => 'Vulnerabilities by Severity', 'color' => '#111827', 'font' => ['size' => 14]],
                        'legend' => ['position' => 'right'],
                    ],
                ],
            ];
        }

        if ($owaspCounts->isNotEmpty()) {
            $labels = $owaspCounts->keys()->values()->toArray();
            $configs['owasp'] = [
                'type' => 'doughnut',
                'data' => [
                    'labels'   => $labels,
                    'datasets' => [['data' => $owaspCounts->values()->toArray(), 'backgroundColor' => array_map(fn($i) => '#' . $palette[$i % count($palette)], range(0, count($labels) - 1))]],
                ],
                'options' => [
                    'plugins' => [
                        'title'  => ['display' => true, 'text' => 'Vulnerabilities by OWASP Category', 'color' => '#111827', 'font' => ['size' => 14]],
                        'legend' => ['position' => 'right'],
                    ],
                ],
            ];
        }

        if ($cweCounts->isNotEmpty()) {
            $labels = $cweCounts->keys()->values()->toArray();
            $configs['cwe'] = [
                'type' => 'horizontalBar',
                'data' => [
                    'labels'   => $labels,
                    'datasets' => [[
                        'label'           => 'Findings',
                        'data'            => $cweCounts->values()->toArray(),
                        'backgroundColor' => array_map(fn($i) => '#' . $palette[$i % count($palette)], range(0, count($labels) - 1)),
                    ]],
                ],
                'options' => [
                    'plugins' => [
                        'title'  => ['display' => true, 'text' => 'Vulnerabilities by CWE', 'color' => '#111827', 'font' => ['size' => 14]],
                        'legend' => ['display' => false],
                    ],
                    'scales' => ['xAxes' => [['ticks' => ['beginAtZero' => true, 'precision' => 0]]]],
                ],
            ];
        }

        // Fetch all charts in parallel, embed in order, then clean up
        foreach ($this->fetchQuickChartMulti($configs) as $tmp) {
            if ($tmp) {
                $this->embedChartImage($tmp);
                @unlink($tmp);
            }
        }

        $this->section->addTextBreak();
    }

    private function fetchQuickChartMulti(array $configs): array
    {
        if (empty($configs)) {
            return [];
        }

        $dir = storage_path('app/tmp');
        if (!is_dir($dir)) { mkdir($dir, 0755, true); }

        $mh      = curl_multi_init();
        $handles = [];

        foreach ($configs as $key => $config) {
            $url = 'https://quickchart.io/chart?w=520&h=280&bkg=white&c='
                 . urlencode(json_encode($config, JSON_UNESCAPED_UNICODE));
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        // Execute all requests concurrently
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) curl_multi_select($mh);
        } while ($active && $status === CURLM_OK);

        $results = [];
        foreach ($handles as $key => $ch) {
            $png = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($png && strlen($png) >= 100) {
                $tmp = $dir . '/chart_' . uniqid() . '.png';
                file_put_contents($tmp, $png);
                $results[$key] = $tmp;
            } else {
                $results[$key] = null;
            }
        }

        curl_multi_close($mh);
        return $results;
    }

    private function embedChartImage(string $path): void
    {
        // Max width = page content width in EMUs; convert twips → EMUs (1 twip = 635 EMUs)
        $widthEmu  = (int) (self::PAGE_WIDTH * 635);
        // Maintain 520:280 aspect ratio
        $heightEmu = (int) ($widthEmu * 280 / 520);

        $this->section->addImage($path, [
            'width'            => self::PAGE_WIDTH / 914,  // twips → points (914 twips/inch, 72 pts/inch)
            'height'           => (self::PAGE_WIDTH / 914) * (280 / 520),
            'alignment'        => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
            'wrappingStyle'    => 'inline',
            'marginTop'        => 0.2,
            'marginBottom'     => 0.2,
        ]);
        $this->section->addTextBreak();
    }

    private function wordShortLabel(string $raw): string
    {
        $pos = mb_strpos($raw, ' – ');
        return $pos !== false ? trim(mb_substr($raw, 0, $pos)) : explode(' ', trim($raw))[0];
    }

    // ── Technical Findings renderer ────────────────────────────────────────────

    private function addTechnicalFindings(\Illuminate\Support\Collection $findings): void
    {
        // Section heading
        $this->section->addTitle('Technical Findings', 2);
        $accentBar = $this->section->addTable(['borderSize' => 0, 'cellMargin' => 0]);
        $accentBar->addRow(40)->addCell(self::PAGE_WIDTH, ['bgColor' => self::C_COVER_LINE])->addText('');
        $this->section->addTextBreak();

        $introRun = $this->section->addTextRun('Body');
        $introRun->addText(
            'The following table summarises all identified findings for this assessment.',
            ['size' => 11, 'color' => self::C_MUTED]
        );
        $this->section->addTextBreak();

        // Summary table
        $summaryTable = $this->section->addTable([
            'borderSize'       => 4,
            'borderColor'      => 'D1D5DB',
            'cellMarginTop'    => 60,
            'cellMarginBottom' => 60,
            'cellMarginLeft'   => 100,
            'cellMarginRight'  => 100,
        ]);
        $hdr = $summaryTable->addRow();
        foreach (['#', 'Title', 'Severity', 'OWASP', 'CWE'] as $col) {
            $hdr->addCell(0, ['bgColor' => self::C_TABLE_HDR])
                ->addText($col, ['bold' => true, 'size' => 10, 'color' => self::C_TABLE_HDR_TXT]);
        }

        $colWidths = [400, 4000, 1200, 2000, 1426];
        foreach ($findings as $idx => $f) {
            $row = $summaryTable->addRow();
            $alt = $idx % 2 === 0 ? 'FFFFFF' : self::C_ROW_ALT;
            $sev = strtolower($f->severity);
            $sevColor = self::SEVERITY_COLORS[$sev] ?? self::C_BODY;
            $cells = [
                [$colWidths[0], 'F-' . str_pad($f->finding_order, 3, '0', STR_PAD_LEFT), self::C_MUTED,  false],
                [$colWidths[1], $f->title,                                                  self::C_BODY,   true],
                [$colWidths[2], $f->severity,                                               $sevColor,      true],
                [$colWidths[3], $f->owasp_category ?? '—',                                  self::C_MUTED,  false],
                [$colWidths[4], $f->cwe_id ?? '—',                                          self::C_MUTED,  false],
            ];
            foreach ($cells as [$w, $txt, $color, $bold]) {
                $row->addCell($w, ['bgColor' => $alt])
                    ->addText($this->sanitizeHeading((string) $txt), ['size' => 10, 'bold' => $bold, 'color' => $color]);
            }
        }
        $this->section->addTextBreak(2);

        // Detail block per finding
        foreach ($findings as $f) {
            $findingId = 'F-' . str_pad($f->finding_order, 3, '0', STR_PAD_LEFT);
            $sev       = strtolower($f->severity);
            $sevColor  = self::SEVERITY_COLORS[$sev] ?? self::C_BODY;

            // Finding title with severity badge
            $titleRun = $this->section->addTextRun(['spaceBefore' => 320, 'spaceAfter' => 80]);
            $titleRun->addText("{$findingId}  ", ['size' => 10, 'color' => self::C_MUTED]);
            $titleRun->addText($this->sanitizeHeading($f->title) . '  ', [
                'name' => 'Calibri', 'size' => 13, 'bold' => true, 'color' => self::C_HEADING3,
            ]);
            $titleRun->addText('[' . strtoupper($f->severity) . ']', [
                'name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => $sevColor,
            ]);

            // Meta table
            $meta = $this->section->addTable([
                'borderSize'       => 4,
                'borderColor'      => 'E5E7EB',
                'cellMarginTop'    => 50,
                'cellMarginBottom' => 50,
                'cellMarginLeft'   => 100,
                'cellMarginRight'  => 100,
            ]);
            foreach ([
                ['Severity',      $f->severity,       $sevColor],
                ['OWASP Category', $f->owasp_category ?? '—', self::C_BODY],
                ['CWE',           $f->cwe_id ?? '—',  self::C_BODY],
            ] as [$label, $val, $color]) {
                $r = $meta->addRow();
                $r->addCell(2200, ['bgColor' => 'F9FAFB'])
                    ->addText($label, ['bold' => true, 'size' => 10, 'color' => self::C_MUTED]);
                $r->addCell(self::PAGE_WIDTH - 2200, ['bgColor' => 'FFFFFF'])
                    ->addText((string) $val, ['size' => 10, 'color' => $color, 'bold' => $color !== self::C_BODY && $color !== self::C_MUTED]);
            }
            $this->section->addTextBreak();

            // Text fields
            foreach ([
                ['Description',    $f->description],
                ['Impact',         $f->impact],
                ['Recommendation', $f->recommendation],
            ] as [$label, $value]) {
                if (empty($value)) continue;
                $this->section->addText($label, ['bold' => true, 'size' => 11, 'color' => self::C_BODY], ['spaceBefore' => 120, 'spaceAfter' => 40]);
                foreach (explode("\n", $value) as $ln) {
                    $this->addParagraph($ln);
                }
            }

            // Evidence blocks
            foreach ([['Request', $f->request], ['Response', $f->response]] as [$label, $value]) {
                if (empty($value)) continue;
                $this->section->addText($label, ['bold' => true, 'size' => 11, 'color' => self::C_BODY], ['spaceBefore' => 120, 'spaceAfter' => 40]);
                $this->addCodeBlock((string) $value);
            }

            $this->addHRule();
        }
    }

    // ── Appendix renderer ─────────────────────────────────────────────────────

    private function addAppendix(string $content): void
    {
        $lines = explode("\n", $content);
        $total = count($lines);
        $i     = 0;

        while ($i < $total) {
            $line    = $lines[$i];
            $trimmed = trim($line);

            // ── Appendix title heading (## Appendix A: …) ──────────────────
            if (str_starts_with($line, '## ')) {
                $this->section->addTitle($this->sanitizeHeading(trim(substr($line, 3))), 2);
                // Violet accent bar beneath the heading
                $tbl = $this->section->addTable(['borderSize' => 0, 'cellMargin' => 0]);
                $tbl->addRow(40)->addCell(self::PAGE_WIDTH, ['bgColor' => self::C_COVER_LINE])->addText('');
                $this->section->addTextBreak();
                $i++; continue;
            }

            // ── Category header: **Category: Name** ────────────────────────
            if (preg_match('/^\*\*Category:\s*(.+?)\*\*$/u', $trimmed, $m)) {
                $catName = trim($m[1]);
                // Coloured category bar
                $tbl = $this->section->addTable([
                    'borderSize'   => 0,
                    'cellMargin'   => 80,
                ]);
                $row = $tbl->addRow();
                $cell = $row->addCell(self::PAGE_WIDTH, ['bgColor' => 'EDE9FE']);
                $cell->addText($catName, [
                    'name'  => 'Calibri',
                    'size'  => 11,
                    'bold'  => true,
                    'color' => self::C_HEADING2,
                ], ['spaceAfter' => 0, 'spaceBefore' => 0]);
                $this->section->addTextBreak();
                $i++; continue;
            }

            // ── Checklist item: ☑ or ☐ followed by activity name ──────────
            if (preg_match('/^([☑☐])\s+(.+)$/u', $trimmed, $m)) {
                $box      = $m[1];
                $label    = trim($m[2]);
                $isDone   = $box === '☑';
                $boxColor = $isDone ? '15803D' : '6B7280';   // green : gray

                // Two-column borderless row: box char | activity name
                $row = $this->section->addTable([
                    'borderSize' => 0,
                    'cellMargin' => 40,
                ])->addRow(280);

                // Box character cell
                $row->addCell(380, ['valign' => 'center'])
                    ->addText($box, [
                        'name'  => 'Calibri',
                        'size'  => 13,
                        'bold'  => true,
                        'color' => $boxColor,
                    ], ['spaceAfter' => 20, 'spaceBefore' => 20]);

                // Activity name cell
                $row->addCell(self::PAGE_WIDTH - 380, ['valign' => 'center'])
                    ->addText($label, [
                        'size'          => 10,
                        'color'         => $isDone ? self::C_BODY : self::C_MUTED,
                        'strikethrough' => false,
                    ], ['spaceAfter' => 20, 'spaceBefore' => 20]);

                $i++; continue;
            }

            // ── Legend line: **Legend:** ☑ … ──────────────────────────────
            if (str_starts_with($trimmed, '**Legend:**')) {
                $this->section->addTextBreak();
                $run = $this->section->addTextRun(['spaceAfter' => 60]);
                $run->addText('Legend: ', ['bold' => true, 'size' => 10, 'color' => self::C_MUTED]);
                $run->addText('☑', ['bold' => true, 'size' => 11, 'color' => '15803D']);
                $run->addText(' Completed  ', ['size' => 10, 'color' => self::C_MUTED]);
                $run->addText('☐', ['bold' => true, 'size' => 11, 'color' => '6B7280']);
                $run->addText(' Not Completed', ['size' => 10, 'color' => self::C_MUTED]);
                $i++; continue;
            }

            // ── Horizontal rule ────────────────────────────────────────────
            if ($trimmed === '---' || $trimmed === '***' || $trimmed === '___') {
                $this->addHRule();
                $i++; continue;
            }

            // ── Blank line ─────────────────────────────────────────────────
            if ($trimmed === '') {
                $this->section->addTextBreak(1, null, ['spaceBefore' => 0, 'spaceAfter' => 0]);
                $i++; continue;
            }

            // ── Normal paragraph (intro text, summary, etc.) ───────────────
            $this->addParagraph($line);
            $i++;
        }
    }

    // ── Markdown parser ────────────────────────────────────────────────────────

    private function parseMarkdown(string $content): void
    {
        $lines = explode("\n", $content);
        $total = count($lines);
        $i     = 0;

        while ($i < $total) {
            $line = $lines[$i];

            // Skip the opening H1 (already used on cover)
            if ($i === 0 && str_starts_with($line, '# ')) {
                $i++;
                continue;
            }

            // Headings
            if (str_starts_with($line, '#### ')) {
                $this->section->addTitle($this->sanitizeHeading(trim(substr($line, 5))), 4);
                $i++; continue;
            }
            if (str_starts_with($line, '### ')) {
                $h3text = trim(substr($line, 4));
                if (preg_match('/\s+[—–\-]\s+(CRITICAL|HIGH|MEDIUM|LOW|INFORMATIONAL)\s*$/i', $h3text, $sm, PREG_OFFSET_CAPTURE)) {
                    $severity = strtolower($sm[1][0]);
                    $title    = $this->sanitizeHeading(trim(substr($h3text, 0, $sm[0][1])));
                    $sevColor = self::SEVERITY_COLORS[$severity] ?? self::C_BODY;
                    $run = $this->section->addTextRun(['spaceBefore' => 280, 'spaceAfter' => 80]);
                    $run->addText($title . '  ', ['name' => 'Calibri', 'size' => 13, 'bold' => true, 'color' => self::C_HEADING3]);
                    $run->addText('[' . strtoupper($severity) . ']', ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => $sevColor]);
                } else {
                    $this->section->addTitle($this->sanitizeHeading($h3text), 3);
                }
                $i++; continue;
            }
            if (str_starts_with($line, '## ')) {
                $this->section->addTitle($this->sanitizeHeading(trim(substr($line, 3))), 2);
                $i++; continue;
            }
            if (str_starts_with($line, '# ')) {
                $this->section->addTitle($this->sanitizeHeading(trim(substr($line, 2))), 1);
                $i++; continue;
            }

            // Fenced code block
            if (preg_match('/^\s*```/', $line)) {
                $codeLines = [];
                $i++;
                while ($i < $total && !preg_match('/^\s*```/', $lines[$i])) {
                    $codeLines[] = $lines[$i];
                    $i++;
                }
                $i++; // closing ```
                $this->addCodeBlock(implode("\n", $codeLines));
                continue;
            }

            // Markdown table
            if (str_starts_with(ltrim($line), '|')) {
                $tableLines = [];
                while ($i < $total && str_starts_with(ltrim($lines[$i]), '|')) {
                    $tableLines[] = $lines[$i];
                    $i++;
                }
                $this->addMarkdownTable($tableLines);
                continue;
            }

            // Horizontal rule
            $trimmed = trim($line);
            if (in_array($trimmed, ['---', '***', '___'])) {
                $this->addHRule();
                $i++; continue;
            }

            // Bullet list (including checklist items: - ✓ / - ✗ / - ▶ / - ○)
            if (preg_match('/^[ \t]*[-*+] /', $line)) {
                while ($i < $total && preg_match('/^[ \t]*[-*+] /', $lines[$i])) {
                    $text = preg_replace('/^[ \t]*[-*+] /', '', $lines[$i]);
                    // Detect checklist symbol at the start of the text
                    if (preg_match('/^([✓✗▶○?])\s+(.+)$/u', $text, $cm)) {
                        $symbol    = $cm[1];
                        $rest      = $cm[2];
                        $symColor  = self::CHECKLIST_COLORS[$symbol] ?? self::C_BODY;
                        $run = $this->section->addTextRun(['spaceBefore' => 40, 'spaceAfter' => 40, 'indentation' => ['left' => 360]]);
                        $run->addText($symbol . '  ', ['size' => 11, 'bold' => true, 'color' => $symColor]);
                        // Parse any inline bold/italic/code in the rest of the text
                        $parts = preg_split('/(\*\*[^*\n]+\*\*|\*[^*\n]+\*|`[^`\n]+`)/', $rest, -1, PREG_SPLIT_DELIM_CAPTURE);
                        foreach ($parts as $part) {
                            if (str_starts_with($part, '**') && str_ends_with($part, '**')) {
                                $run->addText(substr($part, 2, -2), ['bold' => true, 'size' => 11, 'color' => self::C_BODY]);
                            } elseif (str_starts_with($part, '*') && str_ends_with($part, '*')) {
                                $run->addText(substr($part, 1, -1), ['italic' => true, 'size' => 11, 'color' => self::C_BODY]);
                            } elseif (str_starts_with($part, '`') && str_ends_with($part, '`')) {
                                $run->addText(substr($part, 1, -1), ['name' => 'Courier New', 'size' => 10, 'color' => self::C_INLINE_CODE]);
                            } else {
                                $run->addText($part, ['size' => 11, 'color' => self::C_BODY]);
                            }
                        }
                    } else {
                        $this->section->addListItem(
                            $this->strip($text), 0,
                            ['size' => 11, 'color' => self::C_BODY],
                            'listBullet'
                        );
                    }
                    $i++;
                }
                continue;
            }

            // Ordered list
            if (preg_match('/^\d+\. /', $line)) {
                $depth = 0;
                while ($i < $total && preg_match('/^\d+\. /', $lines[$i])) {
                    $text = preg_replace('/^\d+\. /', '', $lines[$i]);
                    $this->section->addListItem(
                        $this->strip($text), 0,
                        ['size' => 11, 'color' => self::C_BODY],
                        'listNumber' . $depth
                    );
                    $i++;
                    $depth++;
                }
                continue;
            }

            // Blank line
            if ($trimmed === '') {
                $this->section->addTextBreak(1, null, ['spaceBefore' => 0, 'spaceAfter' => 0]);
                $i++; continue;
            }

            // Normal paragraph
            $this->addParagraph($line);
            $i++;
        }
    }

    // ── Element builders ───────────────────────────────────────────────────────

    private function addCodeBlock(string $code): void
    {
        // Truncate very long code blocks to avoid bloating the file
        if (mb_strlen($code) > 4000) {
            $code = mb_substr($code, 0, 4000) . "\n[... truncated for brevity ...]";
        }

        $run = $this->section->addTextRun('Code');
        $run->addText($code, [
            'name'  => 'Courier New',
            'size'  => 9,
            'color' => self::C_CODE_TXT,
        ]);
    }

    private function addHRule(): void
    {
        $tbl = $this->section->addTable(['borderSize' => 0, 'cellMargin' => 0]);
        $row = $tbl->addRow(20);
        $row->addCell(self::PAGE_WIDTH, ['bgColor' => self::C_SEPARATOR])->addText('');
        $this->section->addTextBreak();
    }

    private function addMarkdownTable(array $lines): void
    {
        // Remove separator rows (|---|---|)
        $content = array_filter($lines, fn($l) => !preg_match('/^\s*\|[\s\-:| ]+\|\s*$/', $l));
        $rows    = array_values($content);

        if (empty($rows)) return;

        $parsed = array_map(function ($line) {
            return array_map('trim', explode('|', trim($line, "| \t")));
        }, $rows);

        $colCount = max(array_map('count', $parsed));
        // Divide available width equally
        $colW = intdiv(self::PAGE_WIDTH, max(1, $colCount));

        $table = $this->section->addTable([
            'borderSize'        => 4,
            'borderColor'       => 'D1D5DB',
            'cellMarginTop'     => 60,
            'cellMarginBottom'  => 60,
            'cellMarginLeft'    => 100,
            'cellMarginRight'   => 100,
        ]);

        foreach ($parsed as $ri => $cells) {
            $isHeader = ($ri === 0);
            // Pad to full column count
            while (count($cells) < $colCount) $cells[] = '';

            $row = $table->addRow();
            foreach ($cells as $ci => $cellText) {
                $bgColor = $isHeader
                    ? self::C_TABLE_HDR
                    : ($ri % 2 === 0 ? 'FFFFFF' : self::C_ROW_ALT);

                $cell = $row->addCell($colW, ['bgColor' => $bgColor]);
                $clean = $this->strip($cellText);

                $fontColor = $isHeader
                    ? self::C_TABLE_HDR_TXT
                    : ($this->getSeverityColor($clean) ?? self::C_BODY);

                $cell->addText($clean, [
                    'size'  => 10,
                    'bold'  => $isHeader,
                    'color' => $fontColor,
                ]);
            }
        }

        $this->section->addTextBreak();
    }

    private function addParagraph(string $line): void
    {
        $run   = $this->section->addTextRun('Body');
        $parts = preg_split('/(\*\*[^*\n]+\*\*|\*[^*\n]+\*|`[^`\n]+`)/', $line, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $part) {
            if (str_starts_with($part, '**') && str_ends_with($part, '**')) {
                $run->addText(substr($part, 2, -2), ['bold' => true, 'size' => 11, 'color' => self::C_BODY]);
            } elseif (str_starts_with($part, '*') && str_ends_with($part, '*')) {
                $run->addText(substr($part, 1, -1), ['italic' => true, 'size' => 11, 'color' => self::C_BODY]);
            } elseif (str_starts_with($part, '`') && str_ends_with($part, '`')) {
                $run->addText(substr($part, 1, -1), ['name' => 'Courier New', 'size' => 10, 'color' => self::C_INLINE_CODE]);
            } else {
                $run->addText($part, ['size' => 11, 'color' => self::C_BODY]);
            }
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * PhpWord writes heading text raw inside TOC bookmark <w:t> nodes without
     * XML-escaping, so & becomes an invalid entity reference. Replace with the
     * word "and" to produce valid XML that Word can open.
     */
    private function sanitizeHeading(string $text): string
    {
        return str_replace('&', 'and', $text);
    }

    private function strip(string $text): string
    {
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text);
        $text = preg_replace('/\*([^*]+)\*/', '$1', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        return trim($text);
    }

    private function getSeverityColor(string $text): ?string
    {
        $key = strtolower(trim($text));
        // Try exact match first
        if (isset(self::SEVERITY_COLORS[$key])) {
            return self::SEVERITY_COLORS[$key];
        }
        // Try if the cell text starts with a known keyword
        foreach (self::SEVERITY_COLORS as $keyword => $color) {
            if (str_starts_with($key, $keyword)) {
                return $color;
            }
        }
        return null;
    }

    private function resolveTarget(Execution $execution): string
    {
        if ($execution->target) {
            return "{$execution->target->name} ({$execution->target->value})";
        }
        $input = $execution->input_data ?? [];
        return $input['target_name'] ?? $input['target'] ?? 'Not specified';
    }
}
