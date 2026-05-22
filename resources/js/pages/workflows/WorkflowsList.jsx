import React, { useEffect, useState, useCallback } from 'react';
import {
    Plus, Search, Trash2, GitBranch, ExternalLink,
    Shield, Zap, Lock, UserX, Globe, Monitor, Package, Cloud,
    Landmark, Loader2, CheckCircle2, AlertTriangle, Play,
    Hash, Layers,
} from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import Card from '../../components/ui/Card.jsx';
import Table, { Pagination } from '../../components/ui/Table.jsx';
import Badge from '../../components/ui/Badge.jsx';
import Button from '../../components/ui/Button.jsx';
import Modal from '../../components/ui/Modal.jsx';
import Input, { Textarea } from '../../components/ui/Input.jsx';
import { workflowService } from '../../services/workflow.service.js';
import { aptService } from '../../services/apt.service.js';
import RunWorkflowModal from '../../components/ui/RunWorkflowModal.jsx';

// ─── APT presets ──────────────────────────────────────────────────────────────

const APT_PRESETS = [
    { value: 'phishing campaign simulation',           label: 'Phishing Campaign',      icon: Shield  },
    { value: 'ransomware infiltration simulation',      label: 'Ransomware Infiltration', icon: Lock    },
    { value: 'insider threat simulation',              label: 'Insider Threat',          icon: UserX   },
    { value: 'nation-state espionage simulation',      label: 'Nation-State Espionage',  icon: Globe   },
    { value: 'compromised endpoint investigation',     label: 'Compromised Endpoint',    icon: Monitor },
    { value: 'supply chain attack simulation',         label: 'Supply Chain Attack',     icon: Package },
    { value: 'cloud infrastructure breach simulation', label: 'Cloud Breach',            icon: Cloud   },
    { value: 'financial sector APT simulation',       label: 'Financial Sector APT',    icon: Landmark},
];

const APT_LOADING_STEPS = [
    'Analyzing threat landscape…',
    'Designing attack stages…',
    'Mapping MITRE ATT&CK techniques…',
    'Building workflow graph…',
    'Generating advisory report…',
];

// ─── Component ────────────────────────────────────────────────────────────────

export default function WorkflowsList() {
    const navigate = useNavigate();

    // List state
    const [workflows, setWorkflows] = useState([]);
    const [meta, setMeta]           = useState(null);
    const [loading, setLoading]     = useState(true);
    const [search, setSearch]       = useState('');
    const [page, setPage]           = useState(1);

    // Modal state — 'create' | 'apt' | null
    const [modal, setModal]   = useState(null);
    const [saving, setSaving] = useState(false);

    // Create-workflow form
    const [form, setForm] = useState({ name: '', description: '', status: 'draft' });

    // APT simulation form
    const [aptScenario, setAptScenario] = useState('');
    const [aptCustom, setAptCustom]     = useState('');
    const [aptLoading, setAptLoading]   = useState(false);
    const [aptLoadStep, setAptLoadStep] = useState(0);
    const [aptError, setAptError]       = useState(null);

    // Run modal
    const [runWorkflow, setRunWorkflow] = useState(null);
    const [executing, setExecuting]     = useState(false);

    // ── Fetch ──────────────────────────────────────────────────────────────────

    const fetchWorkflows = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await workflowService.list({ search, page });
            setWorkflows(data.data);
            setMeta(data.meta);
        } finally { setLoading(false); }
    }, [search, page]);

    useEffect(() => { fetchWorkflows(); }, [fetchWorkflows]);

    // ── Create workflow ────────────────────────────────────────────────────────

    const openCreate = () => {
        setForm({ name: '', description: '', status: 'draft' });
        setModal('create');
    };

    const handleSave = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const { data } = await workflowService.create(form);
            setModal(null);
            navigate(`/workflows/${data.id}/builder`);
        } finally { setSaving(false); }
    };

    // ── APT simulation ─────────────────────────────────────────────────────────

    const openApt = () => {
        setAptScenario('');
        setAptCustom('');
        setAptError(null);
        setAptLoading(false);
        setAptLoadStep(0);
        setModal('apt');
    };

    const handleAptGenerate = async () => {
        const scenarioType = aptCustom.trim() || aptScenario;
        if (!scenarioType) return;

        setAptLoading(true);
        setAptError(null);
        setAptLoadStep(0);

        const interval = setInterval(
            () => setAptLoadStep(s => (s < APT_LOADING_STEPS.length - 1 ? s + 1 : s)),
            4000
        );

        try {
            const { data } = await aptService.generate(scenarioType);
            setModal(null);
            navigate(`/workflows/${data.workflow.id}/builder`);
        } catch (e) {
            setAptError(e.response?.data?.message ?? e.message ?? 'Generation failed.');
        } finally {
            clearInterval(interval);
            setAptLoading(false);
        }
    };

    // ── Run workflow ───────────────────────────────────────────────────────────

    const openRun = (wf) => { setRunWorkflow(wf); setModal('run'); };

    const handleExecute = async (payload) => {
        setExecuting(true);
        try {
            await workflowService.execute(runWorkflow.id, payload);
            setModal(null);
            navigate('/executions');
        } finally { setExecuting(false); }
    };

    // ── Delete ─────────────────────────────────────────────────────────────────

    const handleDelete = async (wf) => {
        if (!confirm(`Delete workflow "${wf.name}"?`)) return;
        await workflowService.delete(wf.id);
        fetchWorkflows();
    };

    // ── Table columns ──────────────────────────────────────────────────────────

    const columns = [
        {
            key: 'name', label: 'Workflow', render: (w) => (
                <div className="flex items-center gap-3">
                    <div className={`p-1.5 rounded-lg ${w.graph_data?.apt_meta ? 'bg-red-500/10' : 'bg-green-500/10'}`}>
                        {w.graph_data?.apt_meta
                            ? <Shield size={14} className="text-red-400" />
                            : <GitBranch size={14} className="text-green-400" />}
                    </div>
                    <div>
                        <p className="font-medium text-gray-900">{w.name}</p>
                        <p className="text-xs text-gray-500">{w.description?.slice(0, 60)}</p>
                    </div>
                </div>
            ),
        },
        {
            key: 'status', label: 'Status',
            render: (w) => <Badge variant={w.status === 'published' ? 'success' : 'warning'}>{w.status}</Badge>,
        },
        {
            key: 'steps', label: 'Steps',
            render: (w) => {
                const s = w.stats;
                if (!s) return <span className="text-gray-600">—</span>;
                return (
                    <div className="flex items-center gap-1.5 text-xs text-gray-400">
                        <Layers size={12} className="text-violet-400" />
                        <span>{s.skills_count} skill{s.skills_count !== 1 ? 's' : ''}</span>
                        <span className="text-gray-700">·</span>
                        <span>{s.steps_count} step{s.steps_count !== 1 ? 's' : ''}</span>
                    </div>
                );
            },
        },
        {
            key: 'tokens', label: 'Est. Tokens',
            render: (w) => {
                const s = w.stats;
                if (!s || s.total_tokens === 0) return <span className="text-gray-600">—</span>;
                const fmt = (n) => n >= 1000 ? `${(n / 1000).toFixed(1)}k` : String(n);
                return (
                    <div className="text-xs">
                        <div className="flex items-center gap-1 text-yellow-400 font-medium">
                            <Hash size={11} />
                            ~{fmt(s.total_tokens)}
                        </div>
                        <div className="text-gray-600 mt-0.5">
                            {fmt(s.input_tokens)}↑ {fmt(s.output_tokens)}↓
                        </div>
                    </div>
                );
            },
        },
        { key: 'creator', label: 'Creator', render: (w) => w.creator?.name ?? '—' },
        { key: 'created_at', label: 'Created', render: (w) => new Date(w.created_at).toLocaleDateString() },
        {
            key: 'actions', label: '', render: (w) => (
                <div className="flex items-center gap-2 justify-end">
                    <button onClick={() => openRun(w)} title="Run workflow"
                        className="text-gray-400 hover:text-green-400">
                        <Play size={15} />
                    </button>
                    <button onClick={() => navigate(`/workflows/${w.id}/builder`)} title="Open builder"
                        className="text-gray-400 hover:text-violet-600 transition-colors">
                        <ExternalLink size={15} />
                    </button>
                    <button onClick={() => handleDelete(w)} className="text-gray-400 hover:text-red-500 transition-colors">
                        <Trash2 size={15} />
                    </button>
                </div>
            ),
        },
    ];

    // ── Render ─────────────────────────────────────────────────────────────────

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Workflows</h1>
                    <p className="text-gray-500 text-sm mt-1">Design and automate AI workflows</p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="secondary" onClick={openApt}>
                        <Shield size={16} className="text-red-400" /> APT Simulation
                    </Button>
                    <Button onClick={openCreate}>
                        <Plus size={16} /> New Workflow
                    </Button>
                </div>
            </div>

            <Card>
                <div className="p-4 border-b border-gray-100">
                    <div className="relative max-w-xs">
                        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input
                            value={search}
                            onChange={e => { setSearch(e.target.value); setPage(1); }}
                            placeholder="Search workflows…"
                            className="w-full bg-white border border-gray-300 rounded-lg pl-9 pr-4 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
                        />
                    </div>
                </div>
                <Table columns={columns} data={workflows} loading={loading} emptyMessage="No workflows yet." />
                <Pagination meta={meta} onPageChange={setPage} />
            </Card>

            {/* ── Create Workflow Modal ─────────────────────────────────────── */}
            <Modal open={modal === 'create'} onClose={() => setModal(null)} title="Create Workflow">
                <form onSubmit={handleSave} className="space-y-4">
                    <Input
                        label="Workflow Name"
                        value={form.name}
                        onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                        placeholder="e.g. Content Pipeline"
                        required
                    />
                    <Textarea
                        label="Description"
                        value={form.description}
                        onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
                        rows={3}
                    />
                    <div className="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="secondary" onClick={() => setModal(null)}>Cancel</Button>
                        <Button type="submit" loading={saving}>Create & Open Builder</Button>
                    </div>
                </form>
            </Modal>

            {/* ── Run Workflow Modal ────────────────────────────────────────── */}
            <RunWorkflowModal
                open={modal === 'run'}
                workflow={runWorkflow}
                onClose={() => setModal(null)}
                onRun={handleExecute}
                loading={executing}
            />

            {/* ── APT Simulation Modal ──────────────────────────────────────── */}
            <Modal
                open={modal === 'apt'}
                onClose={() => !aptLoading && setModal(null)}
                title="Generate APT Simulation Workflow"
            >
                {aptLoading ? (
                    <AptLoadingState step={aptLoadStep} />
                ) : (
                    <div className="space-y-4">
                        <p className="text-sm text-gray-400">
                            Claude will generate a full MITRE ATT&CK workflow with advisory report.
                        </p>

                        {/* Preset grid */}
                        <div className="grid grid-cols-2 gap-2">
                            {APT_PRESETS.map(({ value, label, icon: Icon }) => (
                                <button
                                    key={value}
                                    onClick={() => { setAptScenario(value); setAptCustom(''); }}
                                    className={`flex items-center gap-2 px-3 py-2.5 rounded-lg border text-sm font-medium transition-all text-left
                                        ${aptScenario === value && !aptCustom
                                            ? 'border-red-400 bg-red-50 text-red-700'
                                            : 'border-gray-200 bg-white text-gray-500 hover:border-gray-400 hover:text-gray-700'}`}
                                >
                                    <Icon size={14} className="flex-shrink-0" />
                                    {label}
                                </button>
                            ))}
                        </div>

                        {/* Custom input */}
                        <Input
                            label="Or describe a custom scenario"
                            value={aptCustom}
                            onChange={e => { setAptCustom(e.target.value); setAptScenario(''); }}
                            placeholder="e.g. critical infrastructure sabotage simulation…"
                        />

                        {aptError && (
                            <div className="flex items-start gap-2 text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg p-3">
                                <AlertTriangle size={15} className="flex-shrink-0 mt-0.5" />
                                {aptError}
                            </div>
                        )}

                        <div className="flex justify-end gap-3 pt-2">
                            <Button type="button" variant="secondary" onClick={() => setModal(null)}>Cancel</Button>
                            <Button
                                onClick={handleAptGenerate}
                                disabled={!aptScenario && !aptCustom.trim()}
                            >
                                <Zap size={14} /> Generate Simulation
                            </Button>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}

function AptLoadingState({ step }) {
    return (
        <div className="py-4 space-y-3">
            <div className="flex justify-center mb-4">
                <Loader2 size={28} className="text-red-400 animate-spin" />
            </div>
            {APT_LOADING_STEPS.map((label, i) => (
                <div key={i} className={`flex items-center gap-3 text-sm transition-colors ${
                    i < step  ? 'text-green-400' :
                    i === step ? 'text-white'     :
                                 'text-gray-600'
                }`}>
                    {i < step
                        ? <CheckCircle2 size={14} className="flex-shrink-0" />
                        : i === step
                            ? <Loader2 size={14} className="animate-spin flex-shrink-0" />
                            : <div className="w-3.5 h-3.5 rounded-full border border-gray-700 flex-shrink-0" />
                    }
                    {label}
                </div>
            ))}
            <p className="text-xs text-gray-500 text-center pt-2">This may take 15–30 seconds…</p>
        </div>
    );
}
