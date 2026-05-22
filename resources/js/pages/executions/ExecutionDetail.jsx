import React, { useEffect, useState, useCallback, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
    ArrowLeft, CheckCircle, XCircle, Clock, Loader2,
    ChevronDown, ChevronRight, Bot, Zap, Target,
    GitBranch, Cpu, Timer, Hash, FileText, Copy, Check,
    RefreshCw, AlertTriangle, Download, Shield, Plus,
    Trash2, Edit2, Wand2, X, BarChart2, List, ExternalLink,
} from 'lucide-react';
import {
    PieChart, Pie, Cell, Tooltip as ReTooltip, Legend,
    BarChart, Bar, XAxis, YAxis, CartesianGrid, ResponsiveContainer,
} from 'recharts';
import Badge from '../../components/ui/Badge.jsx';
import Card from '../../components/ui/Card.jsx';
import { executionService } from '../../services/execution.service.js';

const STATUS_VARIANT = { completed: 'success', failed: 'danger', running: 'info', pending: 'warning' };

const SEVERITY_BADGE = {
    Critical:      'bg-red-50 text-red-700 border border-red-200',
    High:          'bg-orange-50 text-orange-700 border border-orange-200',
    Medium:        'bg-yellow-50 text-yellow-700 border border-yellow-200',
    Low:           'bg-blue-50 text-blue-700 border border-blue-200',
    Informational: 'bg-gray-100 text-gray-600 border border-gray-300',
};

const STATUS_ICON = {
    completed: <CheckCircle size={15} className="text-green-500" />,
    failed:    <XCircle    size={15} className="text-red-500"   />,
    running:   <Loader2    size={15} className="text-blue-500 animate-spin" />,
    pending:   <Clock      size={15} className="text-amber-500" />,
};

function fmt(ms) {
    if (!ms) return null;
    return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`;
}

// ── Inline markdown tokenizer ──────────────────────────────────────────────────

function inlineRender(text) {
    const tokens = [];
    const regex = /(\*\*[^*\n]+\*\*|\*[^*\n]+\*|`[^`\n]+`)/g;
    let last = 0;
    let m;
    while ((m = regex.exec(text)) !== null) {
        if (m.index > last) tokens.push({ type: 'text', val: text.slice(last, m.index) });
        const raw = m[0];
        if (raw.startsWith('**'))     tokens.push({ type: 'bold',   val: raw.slice(2, -2) });
        else if (raw.startsWith('`')) tokens.push({ type: 'code',   val: raw.slice(1, -1) });
        else                          tokens.push({ type: 'italic', val: raw.slice(1, -1) });
        last = m.index + raw.length;
    }
    if (last < text.length) tokens.push({ type: 'text', val: text.slice(last) });

    return tokens.map((t, i) => {
        if (t.type === 'bold')   return <strong key={i} className="font-semibold text-gray-900">{t.val}</strong>;
        if (t.type === 'italic') return <em key={i} className="italic text-gray-500">{t.val}</em>;
        if (t.type === 'code')   return <code key={i} className="bg-violet-50 text-violet-700 border border-violet-200 px-1 py-0.5 rounded text-xs font-mono">{t.val}</code>;
        return <React.Fragment key={i}>{t.val}</React.Fragment>;
    });
}

// ── Markdown block renderer ────────────────────────────────────────────────────

const STRIPPED_SECTIONS = [
    'CWE & Severity Summary',
    'OWASP Testing Coverage',
];

function stripSections(content) {
    const lines = content.split('\n');
    const out = [];
    let skipping = false;
    for (const line of lines) {
        if (line.startsWith('## ')) {
            const heading = line.slice(3).trim();
            skipping = STRIPPED_SECTIONS.some(s => s.toLowerCase() === heading.toLowerCase());
        }
        if (!skipping) out.push(line);
    }
    return out.join('\n');
}

function MarkdownReport({ content }) {
    const lines = stripSections(content).split('\n');
    const result = [];
    let i = 0;
    let key = 0;

    while (i < lines.length) {
        const line = lines[i];

        // Fenced code block
        if (line.trimStart().startsWith('```')) {
            const codeLines = [];
            i++;
            while (i < lines.length && !lines[i].trimStart().startsWith('```')) {
                codeLines.push(lines[i]);
                i++;
            }
            result.push(
                <pre key={key++} className="bg-gray-950 border border-gray-800 rounded-lg p-3 text-xs text-emerald-400 font-mono my-3 overflow-auto whitespace-pre-wrap">
                    {codeLines.join('\n')}
                </pre>
            );
            i++; continue;
        }

        // Headings
        if (line.startsWith('# ')) {
            result.push(
                <h1 key={key++} className="text-xl font-bold text-gray-900 mt-6 mb-3 pb-2 border-b border-gray-200 first:mt-0">
                    {inlineRender(line.slice(2))}
                </h1>
            );
            i++; continue;
        }
        if (line.startsWith('## ')) {
            const h2text     = line.slice(3);
            const isAppendix = /^Appendix/i.test(h2text.trim());
            result.push(
                <h2 key={key++} className={`text-base font-bold mt-6 mb-2 ${
                    isAppendix
                        ? 'text-violet-700 border-t border-violet-100 pt-4'
                        : 'text-gray-900 border-b border-gray-200 pb-1'
                }`}>
                    {inlineRender(h2text)}
                </h2>
            );
            i++; continue;
        }
        if (line.startsWith('### ')) {
            const h3text = line.slice(4);
            const sevMatch = h3text.match(/\s+[—–-]\s+(CRITICAL|HIGH|MEDIUM|LOW|INFORMATIONAL)\s*$/i);
            if (sevMatch) {
                const title    = h3text.slice(0, sevMatch.index).trim();
                const sev      = sevMatch[1].toUpperCase();
                const badgeCls = {
                    CRITICAL:      'bg-red-50 text-red-700 border border-red-200',
                    HIGH:          'bg-orange-50 text-orange-700 border border-orange-200',
                    MEDIUM:        'bg-yellow-50 text-yellow-700 border border-yellow-200',
                    LOW:           'bg-blue-50 text-blue-700 border border-blue-200',
                    INFORMATIONAL: 'bg-gray-100 text-gray-600 border border-gray-300',
                }[sev] ?? 'bg-gray-100 text-gray-600 border border-gray-300';
                result.push(
                    <h3 key={key++} className="text-sm font-semibold text-gray-900 mt-5 mb-1.5 flex items-center gap-2 flex-wrap">
                        {inlineRender(title)}
                        <span className={`text-xs px-2 py-0.5 rounded font-bold tracking-wide ${badgeCls}`}>{sev}</span>
                    </h3>
                );
            } else {
                result.push(
                    <h3 key={key++} className="text-sm font-semibold text-violet-700 mt-5 mb-1.5">
                        {inlineRender(h3text)}
                    </h3>
                );
            }
            i++; continue;
        }
        if (line.startsWith('#### ')) {
            result.push(
                <h4 key={key++} className="text-sm font-semibold text-gray-700 mt-3 mb-1">
                    {inlineRender(line.slice(5))}
                </h4>
            );
            i++; continue;
        }

        // Horizontal rule
        if (line.trim() === '---' || line.trim() === '***' || line.trim() === '___') {
            result.push(<hr key={key++} className="border-gray-200 my-4" />);
            i++; continue;
        }

        // Markdown table
        if (/^\|.+\|$/.test(line.trim())) {
            const parseRow = (r) => r.trim().split('|').slice(1, -1).map(c => c.trim());
            const headers  = parseRow(line);
            i++;
            // consume separator row (|---|---|)
            if (i < lines.length && /^\|[-|: ]+\|$/.test(lines[i].trim())) i++;
            const rows = [];
            while (i < lines.length && /^\|.+\|$/.test(lines[i].trim())) {
                rows.push(parseRow(lines[i]));
                i++;
            }
            result.push(
                <div key={key++} className="my-3 overflow-x-auto rounded-lg border border-gray-200">
                    <table className="w-full text-sm border-collapse">
                        <thead>
                            <tr className="bg-gray-50 border-b border-gray-200">
                                {headers.map((h, hi) => (
                                    <th key={hi} className="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">
                                        {inlineRender(h)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {rows.map((row, ri) => (
                                <tr key={ri} className={ri % 2 === 1 ? 'bg-gray-50/50' : 'bg-white'}>
                                    {row.map((cell, ci) => (
                                        <td key={ci} className="px-4 py-2 text-sm text-gray-700">
                                            {inlineRender(cell)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            );
            continue;
        }

        // ☑ / ☐ checklist items (Appendix format)
        if (/^[☑☐] /.test(line)) {
            const checkItems = [];
            while (i < lines.length && /^[☑☐] /.test(lines[i])) {
                const isDone = lines[i][0] === '☑';
                const label  = lines[i].slice(2);
                checkItems.push(
                    <div key={i} className="flex items-center gap-2.5 py-1">
                        <span className={`flex-shrink-0 text-base ${isDone ? 'text-green-600' : 'text-gray-300'}`}>
                            {lines[i][0]}
                        </span>
                        <span className={`text-sm ${isDone ? 'text-gray-800' : 'text-gray-400'}`}>
                            {inlineRender(label)}
                        </span>
                    </div>
                );
                i++;
            }
            result.push(<div key={key++} className="space-y-0.5">{checkItems}</div>);
            continue;
        }

        // **Category: Name** — category header inside appendix
        const catMatch = line.match(/^\*\*Category:\s*(.+?)\*\*$/);
        if (catMatch) {
            result.push(
                <div key={key++} className="bg-violet-50 border border-violet-200 rounded px-3 py-1.5 mt-4 mb-2">
                    <span className="text-xs font-bold text-violet-700 uppercase tracking-wide">
                        {catMatch[1]}
                    </span>
                </div>
            );
            i++; continue;
        }

        // Unordered list (including checklist items: ✓ ✗ ▶ ○)
        if (/^[ \t]*[-*+] /.test(line)) {
            const regularItems   = [];
            const checklistItems = [];
            let isChecklist = false;

            let j = i;
            while (j < lines.length && /^[ \t]*[-*+] /.test(lines[j])) {
                if (/^[ \t]*[-*+] [✓✗▶○?]/.test(lines[j])) { isChecklist = true; break; }
                j++;
            }

            if (isChecklist) {
                const SYMBOL_STYLE = {
                    '✓': 'text-green-600',
                    '✗': 'text-red-600',
                    '▶': 'text-blue-600',
                    '○': 'text-amber-600',
                    '?': 'text-gray-400',
                };
                while (i < lines.length && /^[ \t]*[-*+] /.test(lines[i])) {
                    const text     = lines[i].replace(/^[ \t]*[-*+] /, '');
                    const symMatch = text.match(/^([✓✗▶○?])\s+(.+)$/u);
                    if (symMatch) {
                        const sym  = symMatch[1];
                        const rest = symMatch[2];
                        checklistItems.push(
                            <div key={i} className="flex items-start gap-2 py-0.5">
                                <span className={`flex-shrink-0 font-bold text-sm mt-0.5 ${SYMBOL_STYLE[sym] ?? 'text-gray-400'}`}>{sym}</span>
                                <span className="text-gray-700 text-sm">{inlineRender(rest)}</span>
                            </div>
                        );
                    } else {
                        checklistItems.push(
                            <div key={i} className="flex items-start gap-2 py-0.5 pl-5">
                                <span className="text-gray-700 text-sm">{inlineRender(text)}</span>
                            </div>
                        );
                    }
                    i++;
                }
                result.push(<div key={key++} className="my-2 space-y-0.5 pl-1">{checklistItems}</div>);
            } else {
                while (i < lines.length && /^[ \t]*[-*+] /.test(lines[i])) {
                    const indent = lines[i].match(/^([ \t]*)/)[1].length;
                    const text   = lines[i].replace(/^[ \t]*[-*+] /, '');
                    regularItems.push(
                        <li key={i} style={{ marginLeft: indent * 6 }} className="text-gray-700 text-sm">
                            {inlineRender(text)}
                        </li>
                    );
                    i++;
                }
                result.push(<ul key={key++} className="list-disc list-outside pl-5 my-2 space-y-1">{regularItems}</ul>);
            }
            continue;
        }

        // Ordered list
        if (/^\d+\. /.test(line)) {
            const items = [];
            while (i < lines.length && /^\d+\. /.test(lines[i])) {
                const text = lines[i].replace(/^\d+\. /, '');
                items.push(
                    <li key={i} className="text-gray-700 text-sm">{inlineRender(text)}</li>
                );
                i++;
            }
            result.push(<ol key={key++} className="list-decimal list-outside pl-5 my-2 space-y-1">{items}</ol>);
            continue;
        }

        // Blank line
        if (line.trim() === '') {
            result.push(<div key={key++} className="h-1.5" />);
            i++; continue;
        }

        // Default paragraph
        result.push(
            <p key={key++} className="text-gray-700 text-sm leading-relaxed">
                {inlineRender(line)}
            </p>
        );
        i++;
    }

    return <div className="space-y-0.5">{result}</div>;
}

// ── LogBlock ───────────────────────────────────────────────────────────────────

function LogBlock({ log, index }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="border border-gray-200 rounded-xl overflow-hidden">
            <button
                onClick={() => setOpen(o => !o)}
                className="w-full flex items-center gap-3 px-4 py-3 bg-white hover:bg-gray-50 transition-colors text-left"
            >
                <span className="flex-shrink-0 w-6 h-6 rounded-full bg-violet-50 border border-violet-100 flex items-center justify-center text-xs font-bold text-violet-600">
                    {index + 1}
                </span>
                <div className="flex-shrink-0">{STATUS_ICON[log.status] ?? STATUS_ICON.pending}</div>
                <span className="flex-1 font-medium text-gray-900 text-sm">{log.step_name}</span>
                <div className="flex items-center gap-3 text-xs text-gray-400 flex-shrink-0">
                    {log.model_used && (
                        <span className="flex items-center gap-1"><Cpu size={11} /> {log.model_used}</span>
                    )}
                    {log.ai_provider && (
                        <span className="flex items-center gap-1"><Bot size={11} /> {log.ai_provider}</span>
                    )}
                    {log.tokens_used > 0 && (
                        <span className="flex items-center gap-1"><Hash size={11} /> {log.tokens_used.toLocaleString()} tokens</span>
                    )}
                    {log.duration_ms != null && (
                        <span className="flex items-center gap-1"><Timer size={11} /> {fmt(log.duration_ms)}</span>
                    )}
                    {open ? <ChevronDown size={14} className="text-gray-400" /> : <ChevronRight size={14} className="text-gray-400" />}
                </div>
            </button>

            {open && (
                <div className="divide-y divide-gray-100 bg-gray-50">
                    {log.error_message && (
                        <div className="px-4 py-3">
                            <p className="text-xs font-semibold text-red-600 mb-1">Error</p>
                            <pre className="text-xs text-red-700 font-mono whitespace-pre-wrap">{log.error_message}</pre>
                        </div>
                    )}
                    {log.prompt_rendered && (
                        <div className="px-4 py-3">
                            <p className="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wider">Prompt sent to AI</p>
                            <pre className="text-xs text-gray-600 bg-white rounded-lg p-3 overflow-auto max-h-64 whitespace-pre-wrap border border-gray-200">
                                {log.prompt_rendered}
                            </pre>
                        </div>
                    )}
                    {log.output_data != null && log.output_data !== '' && (
                        <div className="px-4 py-3">
                            <p className="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wider">AI Response</p>
                            <pre className="text-xs text-emerald-700 bg-white rounded-lg p-3 overflow-auto max-h-96 whitespace-pre-wrap border border-gray-200">
                                {typeof log.output_data === 'string' ? log.output_data : JSON.stringify(log.output_data, null, 2)}
                            </pre>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

// ── StepGroup ──────────────────────────────────────────────────────────────────

function StepGroup({ title, icon, status, skill, duration, logs, defaultOpen = false }) {
    const [open, setOpen] = useState(defaultOpen);
    const totalTokens = logs.reduce((s, l) => s + (l.tokens_used ?? 0), 0);

    return (
        <div className="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <button
                onClick={() => setOpen(o => !o)}
                className="w-full flex items-center gap-3 px-4 py-3.5 bg-gray-50 hover:bg-gray-100 transition-colors text-left"
            >
                <div className="flex-shrink-0 p-1.5 bg-violet-50 border border-violet-100 rounded-lg">{icon}</div>
                <div className="flex-1 min-w-0">
                    <p className="font-semibold text-gray-900 text-sm">{title}</p>
                    {skill && <p className="text-xs text-gray-500 mt-0.5">Skill: {skill}</p>}
                </div>
                <div className="flex items-center gap-3 text-xs text-gray-400 flex-shrink-0">
                    {totalTokens > 0 && <span>{totalTokens.toLocaleString()} tokens</span>}
                    {duration != null && <span>{duration}s</span>}
                    <div className="flex-shrink-0">{STATUS_ICON[status] ?? STATUS_ICON.pending}</div>
                    {open ? <ChevronDown size={14} className="text-gray-400" /> : <ChevronRight size={14} className="text-gray-400" />}
                </div>
            </button>

            {open && (
                <div className="p-4 space-y-3 bg-white border-t border-gray-100">
                    {logs.length === 0
                        ? <p className="text-sm text-gray-400 text-center py-4">No step logs</p>
                        : logs.map((log, i) => <LogBlock key={log.id ?? i} log={log} index={i} />)
                    }
                </div>
            )}
        </div>
    );
}

// ── Chart palette ─────────────────────────────────────────────────────────────

const SEV_COLORS = {
    Critical:      '#EF4444',
    High:          '#F97316',
    Medium:        '#EAB308',
    Low:           '#3B82F6',
    Informational: '#6B7280',
};

const CHART_PALETTE = [
    '#7C3AED','#6D28D9','#2563EB','#0891B2','#059669',
    '#D97706','#DC2626','#DB2777','#4F46E5','#0D9488',
];

function CustomTooltip({ active, payload }) {
    if (!active || !payload?.length) return null;
    const { label, count } = payload[0].payload;
    return (
        <div className="bg-white border border-gray-200 rounded-lg px-3 py-2 text-xs shadow-lg">
            <p className="text-gray-800 font-semibold">{label}</p>
            <p className="text-violet-600">{count} finding{count !== 1 ? 's' : ''}</p>
        </div>
    );
}

function renderCustomLabel({ cx, cy, midAngle, innerRadius, outerRadius, percent }) {
    if (percent < 0.06) return null;
    const RADIAN = Math.PI / 180;
    const radius = innerRadius + (outerRadius - innerRadius) * 0.6;
    const x = cx + radius * Math.cos(-midAngle * RADIAN);
    const y = cy + radius * Math.sin(-midAngle * RADIAN);
    return (
        <text x={x} y={y} fill="white" textAnchor="middle" dominantBaseline="central" fontSize={11} fontWeight="bold">
            {`${(percent * 100).toFixed(0)}%`}
        </text>
    );
}

// ── ExecSummaryCharts ─────────────────────────────────────────────────────────

function ExecSummaryCharts({ executionId }) {
    const [data, setData]       = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError]     = useState(false);

    useEffect(() => {
        setLoading(true);
        setError(false);
        executionService.findings.chartData(executionId)
            .then(r => setData(r.data))
            .catch(() => setError(true))
            .finally(() => setLoading(false));
    }, [executionId]);

    if (loading) {
        return (
            <div className="flex justify-center items-center py-8 border-b border-gray-100">
                <Loader2 size={20} className="text-violet-500 animate-spin" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="px-6 py-4 text-sm text-red-500 border-b border-gray-100 flex items-center gap-2">
                <AlertTriangle size={14} /> Could not load vulnerability chart data.
            </div>
        );
    }

    const total        = data?.total ?? 0;
    const severityArr  = data?.severity ?? [];
    const owaspArr     = data?.owasp    ?? [];
    const cweArr       = data?.cwe      ?? [];

    if (!total) {
        return (
            <div className="px-6 py-5 text-sm text-gray-400 italic border-b border-gray-100">
                No vulnerabilities recorded — add findings in the Findings tab to populate this chart.
            </div>
        );
    }

    const severityData = severityArr.map(d => ({ ...d, fill: SEV_COLORS[d.label] ?? '#6B7280' }));
    const owaspData    = owaspArr.map((d, i) => ({ ...d, fill: CHART_PALETTE[i % CHART_PALETTE.length] }));
    const cweData      = cweArr.map((d, i) => ({ ...d, fill: CHART_PALETTE[i % CHART_PALETTE.length] }));

    return (
        <div className="border-b border-gray-100">
            {/* Section header */}
            <div className="px-6 py-3.5 bg-violet-50 border-b border-violet-100 flex items-center gap-2">
                <BarChart2 size={14} className="text-violet-600" />
                <h3 className="text-sm font-semibold text-violet-700">Executive Summary — Vulnerability Distribution</h3>
                <span className="ml-auto text-xs font-medium text-violet-500 bg-violet-100 px-2 py-0.5 rounded-full">
                    {total} finding{total !== 1 ? 's' : ''}
                </span>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-0 divide-y lg:divide-y-0 lg:divide-x divide-gray-100">

                {/* Chart 1: Severity donut */}
                <div className="p-5">
                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4 text-center">
                        By Severity
                    </p>
                    <ResponsiveContainer width="100%" height={220}>
                        <PieChart>
                            <Pie
                                data={severityData}
                                cx="50%" cy="50%"
                                innerRadius={55} outerRadius={90}
                                dataKey="count"
                                nameKey="label"
                                labelLine={false}
                                label={renderCustomLabel}
                            >
                                {severityData.map((d, i) => <Cell key={i} fill={d.fill} />)}
                            </Pie>
                            <ReTooltip content={<CustomTooltip />} />
                            <Legend
                                formatter={(v) => <span style={{ color: '#374151', fontSize: 11 }}>{v}</span>}
                            />
                        </PieChart>
                    </ResponsiveContainer>
                </div>

                {/* Chart 2: OWASP donut */}
                <div className="p-5">
                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4 text-center">
                        By OWASP Category
                    </p>
                    {owaspData.length === 0 ? (
                        <p className="text-xs text-gray-400 text-center py-10">No OWASP data</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={220}>
                            <PieChart>
                                <Pie
                                    data={owaspData}
                                    cx="50%" cy="50%"
                                    innerRadius={55} outerRadius={90}
                                    dataKey="count"
                                    nameKey="label"
                                    labelLine={false}
                                    label={renderCustomLabel}
                                >
                                    {owaspData.map((d, i) => <Cell key={i} fill={d.fill} />)}
                                </Pie>
                                <ReTooltip content={<CustomTooltip />} />
                                <Legend
                                    formatter={(v) => <span style={{ color: '#374151', fontSize: 11 }}>{v}</span>}
                                />
                            </PieChart>
                        </ResponsiveContainer>
                    )}
                </div>

                {/* Chart 3: CWE bar chart */}
                <div className="p-5">
                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4 text-center">
                        By CWE
                    </p>
                    {cweData.length === 0 ? (
                        <p className="text-xs text-gray-400 text-center py-10">No CWE data</p>
                    ) : (
                        <ResponsiveContainer width="100%" height={220}>
                            <BarChart data={cweData} layout="vertical" margin={{ left: 8, right: 24, top: 4, bottom: 4 }}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" horizontal={false} />
                                <XAxis
                                    type="number"
                                    allowDecimals={false}
                                    tick={{ fill: '#6B7280', fontSize: 10 }}
                                    axisLine={false}
                                    tickLine={false}
                                />
                                <YAxis
                                    type="category"
                                    dataKey="label"
                                    width={80}
                                    tick={{ fill: '#374151', fontSize: 10 }}
                                    axisLine={false}
                                    tickLine={false}
                                />
                                <ReTooltip content={<CustomTooltip />} cursor={{ fill: 'rgba(124,58,237,0.06)' }} />
                                <Bar dataKey="count" radius={[0, 4, 4, 0]} maxBarSize={22}>
                                    {cweData.map((d, i) => <Cell key={i} fill={d.fill} />)}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </div>
            </div>
        </div>
    );
}

// ── ReportCard ─────────────────────────────────────────────────────────────────

function ReportCard({ report, reportStatus, reportError, onGenerate, generating, onDownload, downloading, executionId }) {
    const [copied, setCopied] = useState(false);

    const copyMarkdown = () => {
        navigator.clipboard.writeText(report.content).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    // Processing or pending — show spinner
    if (reportStatus === 'processing' || reportStatus === 'pending') {
        return (
            <Card>
                <div className="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                    <FileText size={16} className="text-emerald-600" />
                    <h2 className="font-semibold text-gray-900">Security Assessment Report</h2>
                </div>
                <div className="flex items-center gap-3 px-5 py-8 text-gray-500 text-sm">
                    <Loader2 size={18} className="animate-spin text-violet-500 flex-shrink-0" />
                    {reportStatus === 'pending' ? 'Report queued — waiting for worker…' : 'Generating report with AI — this may take 30–90 seconds…'}
                </div>
            </Card>
        );
    }

    // Failed — show error + retry button
    if (reportStatus === 'failed') {
        return (
            <Card>
                <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 className="font-semibold text-gray-900 flex items-center gap-2">
                        <FileText size={16} className="text-emerald-600" />
                        Security Assessment Report
                    </h2>
                    <button
                        onClick={onGenerate}
                        disabled={generating}
                        className="flex items-center gap-1.5 text-xs text-violet-600 hover:text-violet-700 border border-violet-200 hover:border-violet-300 rounded px-2.5 py-1 transition-colors disabled:opacity-50"
                    >
                        {generating ? <Loader2 size={12} className="animate-spin" /> : <RefreshCw size={12} />}
                        Retry Generation
                    </button>
                </div>
                <div className="px-5 py-4">
                    <div className="flex items-start gap-2 bg-red-50 border border-red-200 rounded-lg p-4">
                        <AlertTriangle size={16} className="text-red-500 flex-shrink-0 mt-0.5" />
                        <div>
                            <p className="text-sm font-semibold text-red-600 mb-1">Report generation failed</p>
                            {reportError && <p className="text-xs text-red-700 font-mono whitespace-pre-wrap">{reportError}</p>}
                        </div>
                    </div>
                </div>
            </Card>
        );
    }

    // No report yet and not generating — show generate button
    if (!report) {
        return (
            <Card>
                <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 className="font-semibold text-gray-900 flex items-center gap-2">
                        <FileText size={16} className="text-emerald-600" />
                        Security Assessment Report
                    </h2>
                    <button
                        onClick={onGenerate}
                        disabled={generating}
                        className="flex items-center gap-1.5 text-xs text-emerald-600 hover:text-emerald-700 border border-emerald-200 hover:border-emerald-300 rounded px-2.5 py-1 transition-colors disabled:opacity-50"
                    >
                        {generating ? <Loader2 size={12} className="animate-spin" /> : <FileText size={12} />}
                        Generate Report
                    </button>
                </div>
                <div className="px-5 py-6 text-center text-gray-400 text-sm">
                    <FileText size={28} className="mx-auto mb-2 opacity-20 text-gray-400" />
                    No report generated yet. Click Generate Report to create an AI-written security assessment.
                </div>
            </Card>
        );
    }

    // Report ready
    return (
        <Card>
            <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 className="font-semibold text-gray-900 flex items-center gap-2">
                    <FileText size={16} className="text-emerald-600" />
                    Security Assessment Report
                </h2>
                <div className="flex items-center gap-3">
                    {report.generated_at && (
                        <span className="text-xs text-gray-400">
                            Generated {new Date(report.generated_at).toLocaleString()}
                        </span>
                    )}
                    {report.tokens_used && (
                        <span className="text-xs text-gray-400 flex items-center gap-1">
                            <Hash size={11} /> {report.tokens_used.toLocaleString()} tokens
                        </span>
                    )}
                    <button
                        onClick={onGenerate}
                        disabled={generating}
                        className="flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-800 border border-gray-200 hover:border-gray-400 rounded px-2.5 py-1 transition-colors disabled:opacity-50"
                    >
                        {generating ? <Loader2 size={12} className="animate-spin" /> : <RefreshCw size={12} />}
                        Regenerate
                    </button>
                    <button
                        onClick={onDownload}
                        disabled={downloading}
                        className="flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-700 border border-blue-200 hover:border-blue-300 rounded px-2.5 py-1 transition-colors disabled:opacity-50"
                    >
                        {downloading ? <Loader2 size={12} className="animate-spin" /> : <Download size={12} />}
                        {downloading ? 'Preparing…' : 'Download .docx'}
                    </button>
                    <button
                        onClick={copyMarkdown}
                        className="flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-800 transition-colors px-2 py-1 rounded border border-gray-200 hover:border-gray-400"
                    >
                        {copied ? <Check size={12} className="text-green-600" /> : <Copy size={12} />}
                        {copied ? 'Copied!' : 'Copy Markdown'}
                    </button>
                </div>
            </div>

            {/* Charts panel — executive summary visual */}
            <ExecSummaryCharts executionId={executionId} />

            {/* Report document */}
            <div className="px-6 pt-4 pb-1 flex items-center gap-2 border-b border-gray-100">
                <FileText size={13} className="text-gray-400" />
                <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Full Report</span>
            </div>
            <div className="p-6">
                <MarkdownReport content={report.content} />
            </div>
        </Card>
    );
}

// ── FindingCard ────────────────────────────────────────────────────────────────

function FindingCard({ finding, expanded, copiedId, onToggle, onEdit, onDelete, onCopy }) {
    const fid = 'F-' + String(finding.finding_order).padStart(3, '0');

    return (
        <div className="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            <button
                onClick={onToggle}
                className="w-full flex items-center gap-3 px-4 py-3 bg-white hover:bg-gray-50 transition-colors text-left"
            >
                <span className="text-xs font-mono text-gray-400 flex-shrink-0 w-12">{fid}</span>
                <span className="flex-1 font-medium text-gray-900 text-sm truncate">{finding.title}</span>
                <div className="flex items-center gap-2 flex-shrink-0">
                    {finding.owasp_category && (
                        <span className="text-xs text-violet-600 hidden md:block max-w-[120px] truncate">
                            {finding.owasp_category.split('–')[0]?.trim()}
                        </span>
                    )}
                    {finding.cwe_id && (
                        <span className="text-xs text-gray-400 font-mono hidden md:block">
                            {finding.cwe_id.split('–')[0]?.trim()}
                        </span>
                    )}
                    <span className={`text-xs px-2 py-0.5 rounded font-bold tracking-wide ${SEVERITY_BADGE[finding.severity] ?? SEVERITY_BADGE.Informational}`}>
                        {finding.severity}
                    </span>
                    {expanded ? <ChevronDown size={14} className="text-gray-400" /> : <ChevronRight size={14} className="text-gray-400" />}
                </div>
            </button>

            {expanded && (
                <div className="bg-gray-50 divide-y divide-gray-200">
                    {/* Actions */}
                    <div className="flex items-center gap-3 px-4 py-2.5">
                        <button onClick={onEdit} className="flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-900 transition-colors">
                            <Edit2 size={11} /> Edit
                        </button>
                        <button onClick={onDelete} className="flex items-center gap-1.5 text-xs text-red-500 hover:text-red-700 transition-colors">
                            <Trash2 size={11} /> Delete
                        </button>
                    </div>

                    {/* Meta */}
                    <div className="px-4 py-3 grid grid-cols-3 gap-3 text-xs">
                        <div>
                            <p className="text-gray-500 mb-1">Severity</p>
                            <span className={`px-2 py-0.5 rounded font-bold ${SEVERITY_BADGE[finding.severity] ?? SEVERITY_BADGE.Informational}`}>
                                {finding.severity}
                            </span>
                        </div>
                        <div>
                            <p className="text-gray-500 mb-1">OWASP</p>
                            <p className="text-violet-600">{finding.owasp_category || '—'}</p>
                        </div>
                        <div>
                            <p className="text-gray-500 mb-1">CWE</p>
                            <p className="text-gray-700 font-mono">{finding.cwe_id || '—'}</p>
                        </div>
                    </div>

                    {/* Description */}
                    {finding.description && (
                        <div className="px-4 py-3">
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Description</p>
                            <p className="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{finding.description}</p>
                        </div>
                    )}

                    {/* Impact */}
                    {finding.impact && (
                        <div className="px-4 py-3">
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Impact</p>
                            <p className="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{finding.impact}</p>
                        </div>
                    )}

                    {/* PoC Steps */}
                    {finding.poc_steps && finding.poc_steps.length > 0 && (
                        <div className="px-4 py-3">
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">PoC Steps</p>
                            <ol className="space-y-1.5 list-decimal list-inside">
                                {finding.poc_steps.map((step, i) => (
                                    <li key={i} className="text-sm text-gray-700 leading-relaxed">{step}</li>
                                ))}
                            </ol>
                        </div>
                    )}

                    {/* Reproduction Steps */}
                    {(finding.request || finding.response) && (
                        <div className="px-4 py-3 space-y-3">
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">Reproduction Steps</p>

                            {finding.request && (
                                <div>
                                    <div className="flex items-center justify-between mb-1.5">
                                        <p className="text-xs font-medium text-gray-400">Request</p>
                                        <button
                                            onClick={() => onCopy(finding.request, `req-${finding.id}`)}
                                            className="flex items-center gap-1 text-xs text-gray-400 hover:text-gray-700 transition-colors"
                                        >
                                            {copiedId === `req-${finding.id}` ? <Check size={11} className="text-green-600" /> : <Copy size={11} />}
                                            {copiedId === `req-${finding.id}` ? 'Copied!' : 'Copy'}
                                        </button>
                                    </div>
                                    <pre className="text-xs text-emerald-700 bg-gray-950 border border-gray-800 rounded-lg p-3 overflow-auto max-h-64 whitespace-pre-wrap font-mono">
                                        {finding.request}
                                    </pre>
                                </div>
                            )}

                            {finding.response && (
                                <div>
                                    <div className="flex items-center justify-between mb-1.5">
                                        <p className="text-xs font-medium text-gray-400">Response</p>
                                        <button
                                            onClick={() => onCopy(finding.response, `res-${finding.id}`)}
                                            className="flex items-center gap-1 text-xs text-gray-400 hover:text-gray-700 transition-colors"
                                        >
                                            {copiedId === `res-${finding.id}` ? <Check size={11} className="text-green-600" /> : <Copy size={11} />}
                                            {copiedId === `res-${finding.id}` ? 'Copied!' : 'Copy'}
                                        </button>
                                    </div>
                                    <pre className="text-xs text-emerald-700 bg-gray-950 border border-gray-800 rounded-lg p-3 overflow-auto max-h-64 whitespace-pre-wrap font-mono">
                                        {finding.response}
                                    </pre>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Remediation */}
                    {finding.recommendation && (
                        <div className="px-4 py-3">
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Remediation</p>
                            <p className="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{finding.recommendation}</p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

// ── FindingModal (add / edit) ──────────────────────────────────────────────────

function FindingModal({ finding, onSave, onClose, saving }) {
    const isEdit  = Boolean(finding.id);
    const formRef = useRef(null);
    const [form, setForm] = useState({
        title:          finding.title          ?? '',
        severity:       finding.severity       ?? 'Informational',
        owasp_category: finding.owasp_category ?? '',
        cwe_id:         finding.cwe_id         ?? '',
        description:    finding.description    ?? '',
        impact:         finding.impact         ?? '',
        recommendation: finding.recommendation ?? '',
        poc_steps:      (finding.poc_steps ?? []).join('\n'),
        request:        finding.request        ?? '',
        response:       finding.response       ?? '',
    });

    const set = (k) => (e) => setForm(f => ({ ...f, [k]: e.target.value }));

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!form.title.trim()) return;
        const pocSteps = form.poc_steps
            .split('\n')
            .map(l => l.trim())
            .filter(Boolean);
        onSave({ ...form, poc_steps: pocSteps.length ? pocSteps : [] });
    };

    return (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div className="bg-white border border-gray-200 rounded-2xl w-full max-w-2xl max-h-[90vh] flex flex-col shadow-xl">
                <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
                    <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                        <Shield size={15} className="text-rose-500" />
                        {isEdit ? 'Edit Finding' : 'Add Finding'}
                    </h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-700 transition-colors">
                        <X size={18} />
                    </button>
                </div>

                <form ref={formRef} onSubmit={handleSubmit} className="flex-1 overflow-y-auto p-6 space-y-4">
                    <div className="grid grid-cols-3 gap-3">
                        <div className="col-span-2">
                            <label className="block text-xs text-gray-500 mb-1">Title <span className="text-rose-500">*</span></label>
                            <input
                                required
                                value={form.title}
                                onChange={set('title')}
                                className="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-100 placeholder-gray-400"
                                placeholder="SQL Injection in Login Form"
                            />
                        </div>
                        <div>
                            <label className="block text-xs text-gray-500 mb-1">Severity <span className="text-rose-500">*</span></label>
                            <select
                                required
                                value={form.severity}
                                onChange={set('severity')}
                                className="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-100"
                            >
                                {['Critical', 'High', 'Medium', 'Low', 'Informational'].map(s => (
                                    <option key={s} value={s}>{s}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs text-gray-500 mb-1">OWASP Category</label>
                            <input
                                value={form.owasp_category}
                                onChange={set('owasp_category')}
                                className="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-100 placeholder-gray-400"
                                placeholder="A03:2021 – Injection"
                            />
                        </div>
                        <div>
                            <label className="block text-xs text-gray-500 mb-1">CWE</label>
                            <input
                                value={form.cwe_id}
                                onChange={set('cwe_id')}
                                className="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-100 placeholder-gray-400"
                                placeholder="CWE-89 – SQL Injection"
                            />
                        </div>
                    </div>

                    {[
                        { key: 'description',    label: 'Description',    rows: 3, placeholder: 'What was found and why it is a security concern.' },
                        { key: 'impact',         label: 'Impact',         rows: 2, placeholder: 'Business or technical impact if exploited.' },
                        { key: 'recommendation', label: 'Recommendation', rows: 3, placeholder: 'Use parameterized queries…' },
                    ].map(({ key, label, rows, placeholder }) => (
                        <div key={key}>
                            <label className="block text-xs text-gray-500 mb-1">{label}</label>
                            <textarea
                                value={form[key]}
                                onChange={set(key)}
                                rows={rows}
                                placeholder={placeholder}
                                className="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-100 placeholder-gray-400 resize-y"
                            />
                        </div>
                    ))}

                    <div>
                        <label className="block text-xs text-gray-500 mb-1">
                            PoC Steps <span className="text-gray-400">— one step per line</span>
                        </label>
                        <textarea
                            value={form.poc_steps}
                            onChange={set('poc_steps')}
                            rows={4}
                            placeholder={"Navigate to the target URL.\nSubmit the payload into the vulnerable parameter.\nObserve the application response.\nConfirm the vulnerability impact."}
                            className="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-violet-400 focus:ring-1 focus:ring-violet-100 placeholder-gray-400 resize-y"
                        />
                    </div>

                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Request (Evidence)</label>
                        <textarea
                            value={form.request}
                            onChange={set('request')}
                            rows={4}
                            placeholder={'POST /login HTTP/1.1\nHost: example.com\n\nusername=admin\' OR \'1\'=\'1'}
                            className="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-xs text-emerald-400 focus:outline-none focus:border-violet-500 font-mono placeholder-gray-600 resize-y"
                        />
                    </div>
                    <div>
                        <label className="block text-xs text-gray-500 mb-1">Response (Evidence) <span className="text-gray-400">— at least one evidence field required</span></label>
                        <textarea
                            value={form.response}
                            onChange={set('response')}
                            rows={4}
                            placeholder={'HTTP/1.1 200 OK\nContent-Type: text/html\n\nWelcome admin dashboard'}
                            className="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-xs text-emerald-400 focus:outline-none focus:border-violet-500 font-mono placeholder-gray-600 resize-y"
                        />
                    </div>

                    <button type="submit" className="hidden" />
                </form>

                <div className="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 flex-shrink-0">
                    <button onClick={onClose} className="px-4 py-2 text-sm text-gray-500 hover:text-gray-900 border border-gray-200 hover:border-gray-400 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button
                        onClick={() => formRef.current?.requestSubmit()}
                        disabled={saving}
                        className="px-4 py-2 text-sm text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition-colors disabled:opacity-50 flex items-center gap-1.5"
                    >
                        {saving && <Loader2 size={13} className="animate-spin" />}
                        {saving ? 'Saving…' : (isEdit ? 'Update Finding' : 'Add Finding')}
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── AiGenerateModal ────────────────────────────────────────────────────────────

function AiGenerateModal({ onGenerate, onClose, generating, error }) {
    const [rawScanData, setRawScanData]           = useState('');
    const [vulnerabilityType, setVulnerabilityType] = useState('');

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!rawScanData.trim()) return;
        onGenerate({ raw_scan_data: rawScanData, vulnerability_type: vulnerabilityType });
    };

    return (
        <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
            <div className="bg-gray-900 border border-gray-700 rounded-2xl w-full max-w-xl shadow-2xl">
                <div className="px-6 py-4 border-b border-gray-800 flex items-center justify-between">
                    <h3 className="font-semibold text-white flex items-center gap-2">
                        <Wand2 size={15} className="text-violet-400" />
                        AI Generate Finding
                    </h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-white transition-colors">
                        <X size={18} />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <div>
                        <label className="block text-xs text-gray-400 mb-1">Vulnerability Type <span className="text-gray-600">(optional hint)</span></label>
                        <input
                            value={vulnerabilityType}
                            onChange={e => setVulnerabilityType(e.target.value)}
                            className="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet-500 placeholder-gray-600"
                            placeholder="e.g. SQL Injection, XSS, SSRF…"
                        />
                    </div>
                    <div>
                        <label className="block text-xs text-gray-400 mb-1">Raw Scan Data <span className="text-rose-400">*</span></label>
                        <textarea
                            required
                            value={rawScanData}
                            onChange={e => setRawScanData(e.target.value)}
                            rows={10}
                            placeholder="Paste tool output, HTTP request/response, or scan results here…"
                            className="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-gray-300 focus:outline-none focus:border-violet-500 font-mono placeholder-gray-700 resize-y"
                        />
                    </div>
                    <p className="text-xs text-gray-600">Claude will analyse the data and generate a structured finding. Review and edit before saving.</p>
                    {error && (
                        <div className="flex items-start gap-2 bg-red-500/10 border border-red-500/20 rounded-lg px-3 py-2">
                            <AlertTriangle size={13} className="text-red-400 flex-shrink-0 mt-0.5" />
                            <p className="text-xs text-red-300">{error}</p>
                        </div>
                    )}

                    <div className="flex justify-end gap-3 pt-1">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm text-gray-400 hover:text-white border border-gray-700 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={generating}
                            className="px-4 py-2 text-sm text-white bg-violet-600 hover:bg-violet-500 rounded-lg transition-colors disabled:opacity-50 flex items-center gap-1.5"
                        >
                            {generating ? <Loader2 size={13} className="animate-spin" /> : <Wand2 size={13} />}
                            {generating ? 'Generating…' : 'Generate Finding'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ── FindingsTab ────────────────────────────────────────────────────────────────

function FindingsTab({ executionId }) {
    const [findings, setFindings]         = useState([]);
    const [loadingFindings, setLoading]   = useState(true);
    const [expandedId, setExpandedId]     = useState(null);
    const [editingFinding, setEditing]    = useState(null);
    const [showAiModal, setShowAiModal]   = useState(false);
    const [saving, setSaving]             = useState(false);
    const [generatingAi, setGeneratingAi] = useState(false);
    const [copiedId, setCopiedId]         = useState(null);
    const [aiError, setAiError]           = useState(null);

    const loadFindings = useCallback(() => {
        setLoading(true);
        executionService.findings.list(executionId)
            .then(r => setFindings(r.data.data ?? []))
            .finally(() => setLoading(false));
    }, [executionId]);

    useEffect(() => { loadFindings(); }, [loadFindings]);

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this finding? This cannot be undone.')) return;
        await executionService.findings.remove(executionId, id);
        setFindings(f => f.filter(x => x.id !== id));
        if (expandedId === id) setExpandedId(null);
    };

    const handleSave = async (data) => {
        setSaving(true);
        try {
            if (editingFinding.id) {
                const r = await executionService.findings.update(executionId, editingFinding.id, data);
                setFindings(f => f.map(x => x.id === editingFinding.id ? r.data.data : x));
            } else {
                const r = await executionService.findings.create(executionId, data);
                setFindings(f => [...f, r.data.data]);
                setExpandedId(r.data.data.id);
            }
            setEditing(null);
        } finally {
            setSaving(false);
        }
    };

    const handleGenerateAi = async (data) => {
        setGeneratingAi(true);
        setAiError(null);
        try {
            const r = await executionService.findings.generateAi(executionId, data);
            setFindings(f => [...f, r.data.data]);
            setExpandedId(r.data.data.id);
            setShowAiModal(false);
        } catch (err) {
            setAiError(err?.response?.data?.message ?? 'Generation failed. Please try again.');
        } finally {
            setGeneratingAi(false);
        }
    };

    const handleCopy = (text, ck) => {
        navigator.clipboard.writeText(text);
        setCopiedId(ck);
        setTimeout(() => setCopiedId(null), 2000);
    };

    // Summary counts
    const counts = findings.reduce((acc, f) => {
        acc[f.severity] = (acc[f.severity] ?? 0) + 1;
        return acc;
    }, {});

    return (
        <>
            <Card>
                <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 className="font-semibold text-gray-900 flex items-center gap-2">
                        <Shield size={16} className="text-rose-500" />
                        Technical Findings
                        {findings.length > 0 && (
                            <span className="text-xs bg-gray-100 text-gray-500 border border-gray-200 rounded px-1.5 py-0.5">
                                {findings.length}
                            </span>
                        )}
                    </h2>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => { setAiError(null); setShowAiModal(true); }}
                            className="flex items-center gap-1.5 text-xs text-violet-600 hover:text-violet-700 border border-violet-200 hover:border-violet-300 rounded px-2.5 py-1 transition-colors"
                        >
                            <Wand2 size={12} /> AI Generate
                        </button>
                        <button
                            onClick={() => setEditing({})}
                            className="flex items-center gap-1.5 text-xs text-emerald-600 hover:text-emerald-700 border border-emerald-200 hover:border-emerald-300 rounded px-2.5 py-1 transition-colors"
                        >
                            <Plus size={12} /> Add Finding
                        </button>
                    </div>
                </div>

                {/* Severity summary pills */}
                {findings.length > 0 && (
                    <div className="px-5 py-3 border-b border-gray-100 flex items-center gap-2 flex-wrap">
                        {['Critical', 'High', 'Medium', 'Low', 'Informational'].map(s => counts[s] ? (
                            <span key={s} className={`text-xs px-2 py-0.5 rounded font-bold ${SEVERITY_BADGE[s]}`}>
                                {counts[s]} {s}
                            </span>
                        ) : null)}
                    </div>
                )}

                <div className="p-5">
                    {loadingFindings ? (
                        <div className="flex justify-center py-10">
                            <Loader2 size={22} className="text-violet-400 animate-spin" />
                        </div>
                    ) : findings.length === 0 ? (
                        <div className="text-center text-gray-500 text-sm py-10">
                            <Shield size={28} className="mx-auto mb-3 opacity-20" />
                            <p>No findings yet.</p>
                            <p className="text-xs mt-1">Use AI Generate to create findings from scan data, or add manually.</p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {findings.map(f => (
                                <FindingCard
                                    key={f.id}
                                    finding={f}
                                    expanded={expandedId === f.id}
                                    copiedId={copiedId}
                                    onToggle={() => setExpandedId(id => id === f.id ? null : f.id)}
                                    onEdit={() => setEditing(f)}
                                    onDelete={() => handleDelete(f.id)}
                                    onCopy={handleCopy}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </Card>

            {editingFinding !== null && (
                <FindingModal
                    finding={editingFinding}
                    onSave={handleSave}
                    onClose={() => setEditing(null)}
                    saving={saving}
                />
            )}

            {showAiModal && (
                <AiGenerateModal
                    onGenerate={handleGenerateAi}
                    onClose={() => setShowAiModal(false)}
                    generating={generatingAi}
                    error={aiError}
                />
            )}
        </>
    );
}

// ── Multi-target progress panel ────────────────────────────────────────────────

const TARGET_STATUS_ICON = {
    pending:    <Clock   size={13} className="text-amber-500 flex-shrink-0" />,
    processing: <Loader2 size={13} className="text-blue-500 animate-spin flex-shrink-0" />,
    completed:  <CheckCircle size={13} className="text-green-500 flex-shrink-0" />,
    failed:     <XCircle size={13} className="text-red-500 flex-shrink-0" />,
};

const TARGET_STATUS_COLOR = {
    pending:    'text-amber-600',
    processing: 'text-blue-600',
    completed:  'text-green-600',
    failed:     'text-red-600',
};

function TargetsPanel({ executionId, isLive }) {
    const navigate = useNavigate();
    const [data, setData] = useState(null);

    const load = useCallback(() => {
        executionService.targets(executionId).then(r => setData(r.data));
    }, [executionId]);

    useEffect(() => {
        load();
        if (!isLive) return;
        const timer = setInterval(load, 3000);
        return () => clearInterval(timer);
    }, [load, isLive]);

    if (!data) return null;

    const { stats, data: targets } = data;
    const pct = stats.total > 0 ? Math.round(((stats.completed + stats.failed) / stats.total) * 100) : 0;

    return (
        <Card>
            <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 className="font-semibold text-gray-900 flex items-center gap-2">
                    <List size={16} className="text-violet-600" /> Target Progress
                </h2>
                <div className="flex items-center gap-3 text-xs">
                    {stats.completed > 0 && <span className="text-green-600">{stats.completed} done</span>}
                    {stats.failed    > 0 && <span className="text-red-600">{stats.failed} failed</span>}
                    {stats.processing > 0 && <span className="text-blue-600">{stats.processing} running</span>}
                    {stats.pending   > 0 && <span className="text-amber-600">{stats.pending} queued</span>}
                    <span className="text-gray-400 font-medium">{pct}%</span>
                </div>
            </div>

            {/* Progress bar */}
            <div className="h-1.5 bg-gray-100">
                <div
                    className="h-full bg-violet-500 transition-all duration-500"
                    style={{ width: `${pct}%` }}
                />
            </div>

            <div className="divide-y divide-gray-100 max-h-80 overflow-y-auto">
                {targets.map(t => (
                    <div
                        key={t.id}
                        className="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors"
                    >
                        {TARGET_STATUS_ICON[t.status] ?? TARGET_STATUS_ICON.pending}
                        <span className="font-mono text-sm text-gray-800 flex-1 truncate" title={t.target_value}>
                            {t.target_value}
                        </span>
                        <span className={`text-xs font-medium capitalize ${TARGET_STATUS_COLOR[t.status] ?? 'text-gray-500'}`}>
                            {t.status}
                        </span>
                        {t.child_execution_id && (
                            <button
                                onClick={() => navigate(`/executions/${t.child_execution_id}`)}
                                className="text-gray-400 hover:text-violet-600 transition-colors ml-1"
                                title="View target execution"
                            >
                                <ExternalLink size={13} />
                            </button>
                        )}
                        {t.error_message && (
                            <span className="text-xs text-red-500 truncate max-w-[200px]" title={t.error_message}>
                                {t.error_message}
                            </span>
                        )}
                    </div>
                ))}
            </div>
        </Card>
    );
}

// ── Main component ─────────────────────────────────────────────────────────────

export default function ExecutionDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [execution, setExecution] = useState(null);
    const [loading, setLoading] = useState(true);
    const [generatingReport, setGeneratingReport] = useState(false);
    const [downloadingReport, setDownloadingReport] = useState(false);
    const [activeTab, setActiveTab] = useState('logs');

    const shouldPoll = (data) => {
        if (['pending', 'running'].includes(data.status)) return true;
        if (data.type === 'workflow' && ['pending', 'processing'].includes(data.report_status)) return true;
        return false;
    };

    const loadExecution = useCallback((showSpinner = false) => {
        if (showSpinner) setLoading(true);
        return executionService.get(id)
            .then(r => { setExecution(r.data); return r.data; })
            .finally(() => setLoading(false));
    }, [id]);

    useEffect(() => {
        let timer;

        const poll = () => {
            loadExecution().then(data => {
                if (data && shouldPoll(data)) {
                    timer = setTimeout(poll, 3000);
                }
            });
        };

        loadExecution(true).then(data => {
            if (data && shouldPoll(data)) {
                timer = setTimeout(poll, 3000);
            }
        });

        return () => clearTimeout(timer);
    }, [id]);

    const handleGenerateReport = async () => {
        if (generatingReport) return;
        setGeneratingReport(true);
        try {
            await executionService.generateReport(id);
            // Generation is synchronous server-side — reload to get completed report
            await loadExecution();
        } catch {
            loadExecution();
        } finally {
            setGeneratingReport(false);
        }
    };

    const handleDownloadReport = async () => {
        if (downloadingReport) return;
        setDownloadingReport(true);
        try {
            const response = await executionService.downloadReport(id);
            const blob = new Blob([response.data], {
                type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            });
            const url  = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href  = url;
            const name = execution?.workflow?.name ?? 'report';
            link.download = name.replace(/[^a-z0-9_\-]/gi, '_') + '_' + new Date().toISOString().slice(0, 10) + '.docx';
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch {
            // silently ignore — the file didn't generate
        } finally {
            setDownloadingReport(false);
        }
    };

    if (loading) return (
        <div className="flex justify-center py-16">
            <Loader2 size={28} className="text-violet-400 animate-spin" />
        </div>
    );
    if (!execution) return (
        <div className="text-center text-gray-400 py-16">Execution not found</div>
    );

    const isWorkflow  = execution.type === 'workflow';
    const directLogs  = execution.logs ?? [];
    const childExecs  = execution.child_executions ?? [];

    const totalTokens = [
        ...directLogs,
        ...childExecs.flatMap(c => c.logs ?? []),
    ].reduce((s, l) => s + (l.tokens_used ?? 0), 0);

    const isLive = ['pending', 'running'].includes(execution.status);

    return (
        <div className="space-y-6">
            {/* ── Header ─────────────────────────────────────────────────── */}
            <div className="flex items-center gap-4">
                <button onClick={() => navigate('/executions')} className="text-gray-400 hover:text-gray-700 transition-colors">
                    <ArrowLeft size={20} />
                </button>
                <div className="flex-1">
                    <h1 className="text-2xl font-bold text-gray-900">
                        {execution.run_name || 'Execution Detail'}
                    </h1>
                    <p className="text-gray-400 text-xs font-mono mt-0.5">{execution.id}</p>
                </div>
                <div className="flex items-center gap-2">
                    {isLive && (
                        <span className="flex items-center gap-1.5 text-xs text-blue-600 bg-blue-50 border border-blue-200 rounded-full px-2.5 py-1">
                            <Loader2 size={11} className="animate-spin" /> Live
                        </span>
                    )}
                    <Badge variant={STATUS_VARIANT[execution.status]}>{execution.status}</Badge>
                </div>
            </div>

            {/* ── Meta cards ─────────────────────────────────────────────── */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                {[
                    {
                        label: 'Type',
                        value: isWorkflow ? 'Workflow' : 'Skill',
                        icon: isWorkflow ? <GitBranch size={14} className="text-green-600" /> : <Zap size={14} className="text-violet-600" />,
                    },
                    {
                        label: isWorkflow ? 'Workflow' : 'Skill',
                        value: execution.workflow?.name ?? execution.skill?.name ?? '—',
                        icon: <Bot size={14} className="text-violet-600" />,
                    },
                    {
                        label: 'Target',
                        value: execution.input_data?.is_multi_target
                            ? `${execution.input_data.targets_total} targets`
                            : (execution.target ? `${execution.target.name} (${execution.target.value})` : (execution.input_data?.target ?? 'None')),
                        icon: execution.input_data?.is_multi_target
                            ? <List size={14} className="text-violet-600" />
                            : <Target size={14} className="text-violet-600" />,
                    },
                    {
                        label: 'Total Tokens',
                        value: totalTokens > 0 ? totalTokens.toLocaleString() : '—',
                        icon: <Hash size={14} className="text-amber-600" />,
                    },
                    {
                        label: 'Started',
                        value: execution.started_at ? new Date(execution.started_at).toLocaleString() : '—',
                        icon: <Clock size={14} className="text-gray-400" />,
                    },
                    {
                        label: 'Completed',
                        value: execution.completed_at ? new Date(execution.completed_at).toLocaleString() : '—',
                        icon: <Clock size={14} className="text-gray-400" />,
                    },
                    {
                        label: 'Duration',
                        value: execution.duration != null ? `${execution.duration}s` : '—',
                        icon: <Timer size={14} className="text-gray-400" />,
                    },
                    {
                        label: 'Triggered by',
                        value: execution.user?.name ?? '—',
                        icon: <Bot size={14} className="text-gray-400" />,
                    },
                ].map(({ label, value, icon }) => (
                    <div key={label} className="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div className="flex items-center gap-1.5 text-xs text-gray-500 mb-1.5">
                            {icon} {label}
                        </div>
                        <p className="text-gray-900 font-medium text-sm truncate">{value}</p>
                    </div>
                ))}
            </div>

            {/* ── Error banner ───────────────────────────────────────────── */}
            {execution.error_message && !execution.input_data?.is_multi_target && (
                <div className="bg-red-50 border border-red-200 rounded-xl p-4">
                    <p className="text-sm font-semibold text-red-600 mb-1">Execution Error</p>
                    <pre className="text-sm text-red-700 font-mono whitespace-pre-wrap">{execution.error_message}</pre>
                </div>
            )}

            {/* ── Multi-target progress ───────────────────────────────────── */}
            {execution.input_data?.is_multi_target && (
                <TargetsPanel executionId={id} isLive={isLive} />
            )}

            {/* ── Tabs (workflow only) ────────────────────────────────────── */}
            {isWorkflow && execution.status === 'completed' && (
                <div className="flex items-center gap-1 border-b border-gray-200 -mb-2">
                    {[
                        { key: 'logs',     label: 'AI Logs',  icon: <Bot     size={13} /> },
                        { key: 'report',   label: 'Report',   icon: <FileText size={13} /> },
                        { key: 'findings', label: 'Findings', icon: <Shield  size={13} /> },
                    ].map(({ key, label, icon }) => (
                        <button
                            key={key}
                            onClick={() => setActiveTab(key)}
                            className={`flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
                                activeTab === key
                                    ? 'border-violet-600 text-violet-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'
                            }`}
                        >
                            {icon} {label}
                        </button>
                    ))}
                </div>
            )}

            {/* ── Report tab ─────────────────────────────────────────────── */}
            {isWorkflow && execution.status === 'completed' && activeTab === 'report' && (
                <ReportCard
                    executionId={id}
                    report={execution.report}
                    reportStatus={execution.report_status}
                    reportError={execution.report_error}
                    onGenerate={handleGenerateReport}
                    generating={generatingReport}
                    onDownload={handleDownloadReport}
                    downloading={downloadingReport}
                />
            )}

            {/* ── Findings tab ───────────────────────────────────────────── */}
            {isWorkflow && execution.status === 'completed' && activeTab === 'findings' && (
                <FindingsTab executionId={id} />
            )}

            {/* ── AI Activity Log ────────────────────────────────────────── */}
            {(!isWorkflow || execution.status !== 'completed' || activeTab === 'logs') && (
            <Card>
                <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 className="font-semibold text-gray-900 flex items-center gap-2">
                        <Bot size={16} className="text-violet-600" /> AI Activity Log
                    </h2>
                    <span className="text-xs text-gray-400">
                        {isWorkflow
                            ? `${childExecs.length} skill node${childExecs.length !== 1 ? 's' : ''} · ${directLogs.length} direct step${directLogs.length !== 1 ? 's' : ''}`
                            : `${directLogs.length} step${directLogs.length !== 1 ? 's' : ''}`
                        }
                    </span>
                </div>

                <div className="p-5 space-y-4">
                    {directLogs.length > 0 && (
                        <StepGroup
                            title={isWorkflow ? 'APT / Direct Steps' : (execution.skill?.name ?? 'Skill Steps')}
                            icon={<Zap size={14} className="text-violet-400" />}
                            status={execution.status}
                            duration={execution.duration}
                            logs={directLogs}
                            defaultOpen={!isWorkflow}
                        />
                    )}

                    {childExecs.map((child, idx) => (
                        <StepGroup
                            key={child.id}
                            title={`Step ${idx + 1}: ${child.skill?.name ?? 'Skill'}`}
                            icon={<Zap size={14} className="text-violet-400" />}
                            status={child.status}
                            skill={child.skill?.name}
                            duration={child.duration}
                            logs={child.logs ?? []}
                            defaultOpen={true}
                        />
                    ))}

                    {directLogs.length === 0 && childExecs.length === 0 && (
                        <div className="text-center text-gray-500 text-sm py-10">
                            <Bot size={28} className="mx-auto mb-3 opacity-30" />
                            No activity logs yet. The execution may still be queued.
                        </div>
                    )}
                </div>
            </Card>
            )}
        </div>
    );
}
