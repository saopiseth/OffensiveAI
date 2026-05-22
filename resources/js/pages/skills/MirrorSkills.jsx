import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
    ArrowLeft, Copy, ChevronDown, ChevronRight, CheckSquare, Square,
    Eye, Download, Loader2, CheckCircle, XCircle, SkipForward, AlertTriangle,
} from 'lucide-react';
import Button from '../../components/ui/Button.jsx';
import Badge from '../../components/ui/Badge.jsx';
import { Select } from '../../components/ui/Input.jsx';
import { skillService } from '../../services/skill.service.js';

// ── Model options ──────────────────────────────────────────────────────────────

const OPENAI_MODELS = [
    { value: 'gpt-4o',        label: 'GPT-4o  — best quality' },
    { value: 'gpt-4o-mini',   label: 'GPT-4o Mini  — fast & cheap' },
    { value: 'gpt-4-turbo',   label: 'GPT-4 Turbo' },
    { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo  — cheapest' },
    { value: 'o1',            label: 'o1  — reasoning' },
    { value: 'o1-mini',       label: 'o1 Mini  — fast reasoning' },
];

// ── Helpers ────────────────────────────────────────────────────────────────────

function categoryLabel(cat) {
    return cat
        .split('-')
        .map(w => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

// ── Skill row ──────────────────────────────────────────────────────────────────

function SkillRow({ skill, selected, onToggle }) {
    return (
        <div
            onClick={() => onToggle(skill.id)}
            className={`flex items-center gap-3 px-4 py-3 rounded-lg cursor-pointer transition-colors
                ${selected
                    ? 'bg-green-500/10 border border-green-500/40'
                    : 'bg-gray-800/50 border border-gray-700/50 hover:border-gray-600'
                }`}
        >
            <div className="flex-shrink-0 text-gray-400">
                {selected
                    ? <CheckSquare size={16} className="text-green-400" />
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
                <span className="text-xs text-gray-500">{skill.steps_count} step{skill.steps_count !== 1 ? 's' : ''}</span>
                {skill.already_mirrored && (
                    <Badge variant="warning">Mirrored</Badge>
                )}
            </div>
        </div>
    );
}

// ── Category section ───────────────────────────────────────────────────────────

function CategorySection({ category, skills, selectedIds, onToggle, onToggleAll }) {
    const [open, setOpen] = useState(true);
    const allSelected    = skills.every(s => selectedIds.has(s.id));
    const someSelected   = skills.some(s => selectedIds.has(s.id));

    return (
        <div className="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div
                className="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-gray-800/50 transition-colors"
                onClick={() => setOpen(o => !o)}
            >
                {open ? <ChevronDown size={16} className="text-gray-400" /> : <ChevronRight size={16} className="text-gray-400" />}
                <h3 className="flex-1 text-sm font-semibold text-white">{categoryLabel(category)}</h3>
                <span className="text-xs text-gray-500">{skills.length} skill{skills.length !== 1 ? 's' : ''}</span>
                <button
                    onClick={e => { e.stopPropagation(); onToggleAll(skills, !allSelected); }}
                    className="text-xs text-violet-400 hover:text-violet-300 ml-2"
                >
                    {allSelected ? 'Deselect all' : 'Select all'}
                </button>
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
    const [open, setOpen] = useState(false);
    return (
        <div className="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div
                className="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-gray-800/50 transition-colors"
                onClick={() => setOpen(o => !o)}
            >
                {open ? <ChevronDown size={16} className="text-gray-400" /> : <ChevronRight size={16} className="text-gray-400" />}
                <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-white">{item.source_name}</p>
                    <p className="text-xs text-green-400">{item.mirror_name}</p>
                </div>
                <span className="text-xs text-gray-500">{item.steps.length} step{item.steps.length !== 1 ? 's' : ''}</span>
            </div>

            {open && (
                <div className="px-4 pb-4 space-y-3">
                    {item.steps.map((step, i) => (
                        <div key={i} className="bg-gray-800 rounded-lg p-3 space-y-1">
                            <p className="text-xs font-semibold text-white">{step.name}</p>
                            <p className="text-xs text-green-300 font-mono">{step.provider} / {step.model}</p>
                            {step.system_prompt && (
                                <p className="text-xs text-gray-400 italic">{step.system_prompt}</p>
                            )}
                        </div>
                    ))}
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
                {item.mirror_name && <p className="text-xs text-green-400">{item.mirror_name}</p>}
                {(item.reason) && <p className="text-xs text-gray-400">{item.reason}</p>}
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

export default function MirrorSkills() {
    const navigate   = useNavigate();
    const [grouped,  setGrouped]  = useState({});
    const [loading,  setLoading]  = useState(true);
    const [selected, setSelected] = useState(new Set());
    const [model,    setModel]    = useState('gpt-4o');
    const [preview,  setPreview]  = useState(null);
    const [previewing, setPreviewing] = useState(false);
    const [importing,  setImporting]  = useState(false);
    const [results,    setResults]    = useState(null);

    useEffect(() => {
        skillService.mirrorList()
            .then(({ data }) => setGrouped(data.data ?? {}))
            .finally(() => setLoading(false));
    }, []);

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

    const totalSkills = Object.values(grouped).reduce((a, arr) => a + arr.length, 0);
    const allIds      = Object.values(grouped).flat().map(s => s.id);

    const handlePreview = async () => {
        setPreviewing(true);
        setPreview(null);
        try {
            const { data } = await skillService.mirrorPreview({
                skill_ids: [...selected],
                target_provider: 'openai',
                target_model: model,
            });
            setPreview(data.data);
        } finally { setPreviewing(false); }
    };

    const handleImport = async () => {
        setImporting(true);
        setResults(null);
        try {
            const { data } = await skillService.mirrorImport({
                skill_ids: [...selected],
                target_provider: 'openai',
                target_model: model,
            });
            setResults(data);
            setPreview(null);
        } finally { setImporting(false); }
    };

    if (loading) {
        return (
            <div className="flex justify-center py-12">
                <div className="animate-spin w-8 h-8 border-2 border-violet-500 border-t-transparent rounded-full" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center gap-4">
                <button onClick={() => navigate('/skills')} className="text-gray-400 hover:text-white">
                    <ArrowLeft size={20} />
                </button>
                <div className="flex-1">
                    <h1 className="text-2xl font-bold text-white">Mirror Skills to OpenAI</h1>
                    <p className="text-gray-400 text-sm">
                        Duplicate Claude skills as OpenAI-powered versions with auto-generated system prompts
                    </p>
                </div>
            </div>

            {/* Config bar */}
            <div className="bg-gray-900 border border-gray-800 rounded-xl p-4 flex flex-wrap items-center gap-4">
                <div className="flex items-center gap-2 text-sm text-gray-400">
                    <span className="font-medium text-white">{selected.size}</span> of {totalSkills} skills selected
                </div>
                <div className="flex gap-2 flex-shrink-0">
                    <button onClick={() => setSelected(new Set(allIds))} className="text-xs text-violet-400 hover:text-violet-300">Select all</button>
                    <span className="text-gray-600">·</span>
                    <button onClick={() => setSelected(new Set())} className="text-xs text-violet-400 hover:text-violet-300">Clear</button>
                </div>
                <div className="flex-1 min-w-48 max-w-64">
                    <Select
                        value={model}
                        onChange={e => setModel(e.target.value)}
                    >
                        {OPENAI_MODELS.map(m => (
                            <option key={m.value} value={m.value}>{m.label}</option>
                        ))}
                    </Select>
                </div>
                <div className="flex gap-3 ml-auto">
                    <Button
                        variant="secondary"
                        onClick={handlePreview}
                        disabled={selected.size === 0}
                        loading={previewing}
                    >
                        <Eye size={14} />Preview
                    </Button>
                    <Button
                        onClick={handleImport}
                        disabled={selected.size === 0}
                        loading={importing}
                    >
                        <Download size={14} />Import {selected.size > 0 ? `(${selected.size})` : ''}
                    </Button>
                </div>
            </div>

            {/* Results */}
            {results && (
                <div className="space-y-3">
                    <div className="flex items-center gap-4">
                        <h2 className="text-sm font-semibold text-gray-400 uppercase tracking-wider">Import Results</h2>
                        <div className="flex gap-3 text-xs">
                            <span className="text-green-400">{results.summary.created} created</span>
                            <span className="text-yellow-400">{results.summary.skipped} skipped</span>
                            <span className="text-red-400">{results.summary.failed} failed</span>
                        </div>
                    </div>
                    {results.results.map((item, i) => <ResultRow key={i} item={item} />)}
                </div>
            )}

            {/* Preview */}
            {preview && (
                <div className="space-y-3">
                    <h2 className="text-sm font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-2">
                        <Eye size={14} />
                        Preview — {preview.length} skill{preview.length !== 1 ? 's' : ''} to mirror
                    </h2>
                    {preview.map((item, i) => <PreviewCard key={i} item={item} />)}
                    <div className="flex justify-end pt-2">
                        <Button onClick={handleImport} loading={importing}>
                            <Download size={14} />Confirm Import ({preview.length})
                        </Button>
                    </div>
                </div>
            )}

            {/* Skill list */}
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
                        <div className="bg-gray-900 border border-dashed border-gray-700 rounded-xl p-8 text-center">
                            <p className="text-gray-500">No Claude skills found. Create some skills first.</p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
