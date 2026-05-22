import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
    ArrowLeft, ChevronDown, ChevronRight, CheckSquare, Square,
    Eye, Download, Loader2, CheckCircle, XCircle, SkipForward,
    Zap, RefreshCw,
} from 'lucide-react';
import Button from '../../components/ui/Button.jsx';
import Badge from '../../components/ui/Badge.jsx';
import { skillService } from '../../services/skill.service.js';

// ── Helpers ────────────────────────────────────────────────────────────────────

function categoryLabel(cat) {
    return cat
        .split('-')
        .map(w => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

function providerBadgeColor(provider) {
    if (provider === 'openai')  return 'bg-green-500/10 text-green-300 border border-green-500/30';
    if (provider === 'claude')  return 'bg-violet-500/10 text-violet-300 border border-violet-500/30';
    return 'bg-gray-500/10 text-gray-300 border border-gray-600';
}

// ── Skill row ──────────────────────────────────────────────────────────────────

function SkillRow({ skill, selected, onToggle }) {
    return (
        <div
            onClick={() => !skill.already_converted && onToggle(skill.id)}
            className={`flex items-center gap-3 px-4 py-3 rounded-lg transition-colors
                ${skill.already_converted
                    ? 'opacity-50 cursor-not-allowed bg-gray-800/30 border border-gray-700/30'
                    : selected
                        ? 'bg-violet-500/10 border border-violet-500/40 cursor-pointer'
                        : 'bg-gray-800/50 border border-gray-700/50 hover:border-gray-600 cursor-pointer'
                }`}
        >
            <div className="flex-shrink-0 text-gray-400">
                {skill.already_converted
                    ? <CheckCircle size={16} className="text-violet-400" />
                    : selected
                        ? <CheckSquare size={16} className="text-violet-400" />
                        : <Square size={16} />
                }
            </div>

            <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-white truncate">{skill.name}</p>
                {skill.description && (
                    <p className="text-xs text-gray-500 truncate">{skill.description}</p>
                )}
            </div>

            <div className="flex items-center gap-2 flex-shrink-0">
                {skill.providers.map(p => (
                    <span key={p} className={`text-xs rounded-full px-2 py-0.5 font-mono ${providerBadgeColor(p)}`}>
                        {p}
                    </span>
                ))}
                <span className="text-xs text-gray-500">{skill.steps_count} step{skill.steps_count !== 1 ? 's' : ''}</span>
                {skill.already_converted && (
                    <Badge variant="info">Converted</Badge>
                )}
            </div>
        </div>
    );
}

// ── Category section ───────────────────────────────────────────────────────────

function CategorySection({ category, skills, selectedIds, onToggle, onToggleAll }) {
    const [open, setOpen] = useState(true);
    const eligible     = skills.filter(s => !s.already_converted);
    const allSelected  = eligible.length > 0 && eligible.every(s => selectedIds.has(s.id));

    return (
        <div className="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div
                className="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-gray-800/50 transition-colors"
                onClick={() => setOpen(o => !o)}
            >
                {open ? <ChevronDown size={16} className="text-gray-400" /> : <ChevronRight size={16} className="text-gray-400" />}
                <h3 className="flex-1 text-sm font-semibold text-white">{categoryLabel(category)}</h3>
                <span className="text-xs text-gray-500">{skills.length} skill{skills.length !== 1 ? 's' : ''}</span>
                {eligible.length > 0 && (
                    <button
                        onClick={e => { e.stopPropagation(); onToggleAll(eligible, !allSelected); }}
                        className="text-xs text-violet-400 hover:text-violet-300 ml-2"
                    >
                        {allSelected ? 'Deselect all' : 'Select all'}
                    </button>
                )}
            </div>

            {open && (
                <div className="px-3 pb-3 space-y-2">
                    {skills.map(skill => (
                        <SkillRow
                            key={skill.id}
                            skill={skill}
                            selected={selectedIds.has(skill.id)}
                            onToggle={onToggle}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

// ── Preview card ───────────────────────────────────────────────────────────────

function PreviewCard({ item }) {
    const [open,        setOpen]       = useState(false);
    const [activeStep,  setActiveStep] = useState(0);

    const steps = item.steps ?? [];

    return (
        <div className="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div
                className="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-gray-800/50 transition-colors"
                onClick={() => setOpen(o => !o)}
            >
                {open ? <ChevronDown size={16} className="text-gray-400" /> : <ChevronRight size={16} className="text-gray-400" />}
                <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-white">{item.source_name}</p>
                    <p className="text-xs text-violet-400 flex items-center gap-1 mt-0.5">
                        <Zap size={10} /> {item.converted_name}
                    </p>
                </div>
                <span className="text-xs text-gray-500">{steps.length} step{steps.length !== 1 ? 's' : ''}</span>
            </div>

            {open && steps.length > 0 && (
                <div className="border-t border-gray-800">
                    {/* Step tabs */}
                    {steps.length > 1 && (
                        <div className="flex gap-1 px-4 pt-3 overflow-x-auto">
                            {steps.map((s, i) => (
                                <button
                                    key={i}
                                    onClick={() => setActiveStep(i)}
                                    className={`flex-shrink-0 text-xs px-3 py-1.5 rounded-lg transition-colors ${
                                        activeStep === i
                                            ? 'bg-violet-600 text-white'
                                            : 'bg-gray-800 text-gray-400 hover:text-gray-200'
                                    }`}
                                >
                                    {s.name || `Step ${i + 1}`}
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Merged prompt */}
                    <div className="p-4 space-y-3">
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                {steps[activeStep]?.name || `Step ${activeStep + 1}`}
                            </span>
                            <span className={`text-xs rounded-full px-2 py-0.5 font-mono ${providerBadgeColor(steps[activeStep]?.original_provider)}`}>
                                {steps[activeStep]?.original_provider} → claude
                            </span>
                        </div>
                        <pre className="text-xs text-gray-300 bg-gray-950 border border-gray-700 rounded-lg p-3 overflow-auto max-h-56 whitespace-pre-wrap font-mono leading-relaxed">
                            {steps[activeStep]?.merged_prompt || '(empty)'}
                        </pre>
                        {steps[activeStep]?.output_schema && (
                            <div>
                                <p className="text-xs text-gray-500 mb-1">Output schema</p>
                                <pre className="text-xs text-emerald-400 bg-gray-950 border border-gray-800 rounded-lg p-2 overflow-auto max-h-24 font-mono">
                                    {JSON.stringify(steps[activeStep].output_schema, null, 2)}
                                </pre>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

// ── Result row ─────────────────────────────────────────────────────────────────

function ResultRow({ item }) {
    const icon = {
        created: <CheckCircle size={16} className="text-green-400" />,
        skipped: <SkipForward  size={16} className="text-yellow-400" />,
        failed:  <XCircle      size={16} className="text-red-400" />,
    }[item.status];

    return (
        <div className="flex items-center gap-3 px-4 py-3 bg-gray-900 border border-gray-800 rounded-lg">
            {icon}
            <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-white">{item.source_name}</p>
                {item.converted_name && <p className="text-xs text-violet-400">{item.converted_name}</p>}
                {item.reason && <p className="text-xs text-gray-400">{item.reason}</p>}
            </div>
            {item.steps_copied != null && (
                <span className="text-xs text-gray-500">{item.steps_copied} step{item.steps_copied !== 1 ? 's' : ''}</span>
            )}
            <Badge variant={item.status === 'created' ? 'success' : item.status === 'skipped' ? 'warning' : 'danger'}>
                {item.status}
            </Badge>
        </div>
    );
}

// ── Main page ──────────────────────────────────────────────────────────────────

export default function ConvertSkills() {
    const navigate     = useNavigate();
    const [grouped,    setGrouped]    = useState({});
    const [loading,    setLoading]    = useState(true);
    const [selected,   setSelected]   = useState(new Set());
    const [preview,    setPreview]    = useState(null);
    const [previewing, setPreviewing] = useState(false);
    const [importing,  setImporting]  = useState(false);
    const [results,    setResults]    = useState(null);

    const loadList = useCallback(() => {
        setLoading(true);
        skillService.convertList()
            .then(({ data }) => setGrouped(data.data ?? {}))
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => { loadList(); }, [loadList]);

    const toggleSkill = useCallback((id) => {
        setSelected(s => {
            const next = new Set(s);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    }, []);

    const toggleAll = useCallback((skills, select) => {
        setSelected(s => {
            const next = new Set(s);
            skills.forEach(sk => select ? next.add(sk.id) : next.delete(sk.id));
            return next;
        });
    }, []);

    const allSkills   = Object.values(grouped).flat();
    const eligible    = allSkills.filter(s => !s.already_converted);
    const totalCount  = allSkills.length;
    const eligibleIds = eligible.map(s => s.id);

    const handlePreview = async () => {
        setPreviewing(true);
        setPreview(null);
        try {
            const { data } = await skillService.convertPreview({ skill_ids: [...selected] });
            setPreview(data.data);
        } finally { setPreviewing(false); }
    };

    const handleImport = async () => {
        setImporting(true);
        setResults(null);
        try {
            const { data } = await skillService.convertImport({ skill_ids: [...selected] });
            setResults(data);
            setPreview(null);
            setSelected(new Set());
            loadList();
        } finally { setImporting(false); }
    };

    if (loading) {
        return (
            <div className="flex justify-center py-12">
                <Loader2 size={28} className="animate-spin text-violet-400" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* ── Header ─────────────────────────────────────────────────── */}
            <div className="flex items-center gap-4">
                <button onClick={() => navigate('/skills')} className="text-gray-400 hover:text-white transition-colors">
                    <ArrowLeft size={20} />
                </button>
                <div className="flex-1">
                    <h1 className="text-2xl font-bold text-white flex items-center gap-2">
                        <Zap size={22} className="text-violet-400" />
                        Convert Skills to Claude
                    </h1>
                    <p className="text-gray-400 text-sm mt-0.5">
                        Convert OpenAI and other provider skills into Claude-compatible versions with merged prompts
                    </p>
                </div>
                <button
                    onClick={loadList}
                    className="text-gray-400 hover:text-white transition-colors"
                    title="Refresh"
                >
                    <RefreshCw size={18} />
                </button>
            </div>

            {/* ── Config bar ─────────────────────────────────────────────── */}
            <div className="bg-gray-900 border border-gray-800 rounded-xl p-4 flex flex-wrap items-center gap-4">
                <div className="flex items-center gap-2 text-sm text-gray-400">
                    <span className="font-medium text-white">{selected.size}</span> of {eligible.length} eligible skills selected
                    {totalCount > eligible.length && (
                        <span className="text-xs text-gray-600">
                            ({totalCount - eligible.length} already converted)
                        </span>
                    )}
                </div>
                <div className="flex gap-2 flex-shrink-0">
                    <button onClick={() => setSelected(new Set(eligibleIds))} className="text-xs text-violet-400 hover:text-violet-300 transition-colors">
                        Select all
                    </button>
                    <span className="text-gray-600">·</span>
                    <button onClick={() => setSelected(new Set())} className="text-xs text-violet-400 hover:text-violet-300 transition-colors">
                        Clear
                    </button>
                </div>

                <div className="flex gap-3 ml-auto">
                    <Button
                        variant="secondary"
                        onClick={handlePreview}
                        disabled={selected.size === 0}
                        loading={previewing}
                    >
                        <Eye size={14} /> Preview
                    </Button>
                    <Button
                        onClick={handleImport}
                        disabled={selected.size === 0}
                        loading={importing}
                    >
                        <Download size={14} />
                        Convert {selected.size > 0 ? `(${selected.size})` : ''}
                    </Button>
                </div>
            </div>

            {/* ── Results ────────────────────────────────────────────────── */}
            {results && (
                <div className="space-y-3">
                    <div className="flex items-center gap-4">
                        <h2 className="text-sm font-semibold text-gray-400 uppercase tracking-wider">Conversion Results</h2>
                        <div className="flex gap-3 text-xs">
                            <span className="text-green-400">{results.summary.created} created</span>
                            <span className="text-yellow-400">{results.summary.skipped} skipped</span>
                            {results.summary.failed > 0 && (
                                <span className="text-red-400">{results.summary.failed} failed</span>
                            )}
                        </div>
                    </div>
                    {results.results.map((item, i) => <ResultRow key={i} item={item} />)}
                    <div className="flex justify-end pt-1">
                        <Button variant="secondary" onClick={() => navigate('/skills')}>
                            View Skills
                        </Button>
                    </div>
                </div>
            )}

            {/* ── Preview ────────────────────────────────────────────────── */}
            {preview && (
                <div className="space-y-3">
                    <h2 className="text-sm font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
                        <Eye size={14} />
                        Preview — {preview.length} skill{preview.length !== 1 ? 's' : ''} to convert
                    </h2>
                    {preview.map((item, i) => <PreviewCard key={i} item={item} />)}
                    <div className="flex justify-end pt-2">
                        <Button onClick={handleImport} loading={importing}>
                            <Download size={14} /> Confirm Convert ({preview.length})
                        </Button>
                    </div>
                </div>
            )}

            {/* ── Skill list ─────────────────────────────────────────────── */}
            {!results && (
                <div className="space-y-4">
                    {Object.entries(grouped).map(([cat, skills]) => (
                        <CategorySection
                            key={cat}
                            category={cat}
                            skills={skills}
                            selectedIds={selected}
                            onToggle={toggleSkill}
                            onToggleAll={toggleAll}
                        />
                    ))}

                    {Object.keys(grouped).length === 0 && (
                        <div className="bg-gray-900 border border-dashed border-gray-700 rounded-xl p-10 text-center space-y-2">
                            <Zap size={28} className="text-gray-600 mx-auto" />
                            <p className="text-gray-400 font-medium">No convertible skills found</p>
                            <p className="text-gray-600 text-sm">
                                Create skills with OpenAI or other provider steps first, or mirror Claude skills to OpenAI and convert them back.
                            </p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
