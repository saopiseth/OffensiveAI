import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Plus, Edit2, Trash2, GripVertical, Play, Loader2 } from 'lucide-react';
import Button from '../../components/ui/Button.jsx';
import Modal from '../../components/ui/Modal.jsx';
import Input, { Textarea, Select } from '../../components/ui/Input.jsx';
import Badge from '../../components/ui/Badge.jsx';
import { skillService } from '../../services/skill.service.js';
import { executionService } from '../../services/execution.service.js';

// ── Provider / model config ────────────────────────────────────────────────────

const PROVIDER_MODELS = {
    claude: [
        { value: 'claude-opus-4-7',          label: 'Claude Opus 4.7  — most capable' },
        { value: 'claude-sonnet-4-6',         label: 'Claude Sonnet 4.6  — balanced' },
        { value: 'claude-haiku-4-5-20251001', label: 'Claude Haiku 4.5  — fastest' },
    ],
    openai: [
        { value: 'gpt-4o',        label: 'GPT-4o  — best quality' },
        { value: 'gpt-4o-mini',   label: 'GPT-4o Mini  — fast & cheap' },
        { value: 'gpt-4-turbo',   label: 'GPT-4 Turbo' },
        { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo  — cheapest' },
        { value: 'o1',            label: 'o1  — reasoning' },
        { value: 'o1-mini',       label: 'o1 Mini  — fast reasoning' },
    ],
};

const DEFAULT_MODEL = { claude: 'claude-sonnet-4-6', openai: 'gpt-4o' };

const PROVIDER_COLOR = {
    claude: 'bg-violet-500/10 text-violet-300 border-violet-500/30',
    openai: 'bg-green-500/10  text-green-300  border-green-500/30',
};

export default function SkillDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [skill, setSkill] = useState(null);
    const [loading, setLoading] = useState(true);
    const [modal, setModal] = useState(null);
    const [stepForm, setStepForm] = useState({ name: '', prompt_template: '', system_prompt: '', description: '', ai_provider: 'claude', model: DEFAULT_MODEL.claude, temperature: 0.7, max_tokens: 2048 });
    const [saving, setSaving] = useState(false);
    const [executing, setExecuting] = useState(false);
    const [execModal, setExecModal] = useState(false);
    const [execInput, setExecInput] = useState('');
    const [execResult, setExecResult] = useState(null);

    const fetchSkill = async () => {
        setLoading(true);
        try {
            const { data } = await skillService.get(id);
            setSkill(data);
        } finally { setLoading(false); }
    };

    useEffect(() => { fetchSkill(); }, [id]);

    const openAddStep = () => {
        setStepForm({ name: '', prompt_template: '', system_prompt: '', description: '', ai_provider: 'claude', model: DEFAULT_MODEL.claude, temperature: 0.7, max_tokens: 2048 });
        setModal('create');
    };

    // When the provider dropdown changes, auto-switch the model to that provider's default
    const handleProviderChange = (provider) => {
        setStepForm(f => ({
            ...f,
            ai_provider: provider,
            model: DEFAULT_MODEL[provider] ?? f.model,
        }));
    };

    const openEditStep = (step) => {
        setStepForm({ name: step.name, prompt_template: step.prompt_template, system_prompt: step.system_prompt || '', description: step.description || '', ai_provider: step.ai_provider, model: step.model || '', temperature: step.temperature, max_tokens: step.max_tokens });
        setModal({ type: 'edit', step });
    };

    const handleSaveStep = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            if (modal === 'create') await skillService.createStep(id, stepForm);
            else await skillService.updateStep(modal.step.id, stepForm);
            setModal(null);
            fetchSkill();
        } finally { setSaving(false); }
    };

    const handleDeleteStep = async (step) => {
        if (!confirm(`Delete step "${step.name}"?`)) return;
        await skillService.deleteStep(step.id);
        fetchSkill();
    };

    const handleExecute = async () => {
        setExecuting(true);
        setExecResult(null);
        try {
            let inputData = {};
            try { inputData = JSON.parse(execInput || '{}'); } catch { inputData = { text: execInput }; }
            const { data } = await executionService.run(id, inputData);
            setExecResult(data);
        } finally { setExecuting(false); }
    };

    if (loading) return <div className="flex justify-center py-12"><div className="animate-spin w-8 h-8 border-2 border-violet-500 border-t-transparent rounded-full" /></div>;
    if (!skill) return <div className="text-center text-gray-400 py-12">Skill not found</div>;

    return (
        <div className="space-y-6">
            <div className="flex items-center gap-4">
                <button onClick={() => navigate('/skills')} className="text-gray-400 hover:text-gray-700 transition-colors"><ArrowLeft size={20} /></button>
                <div className="flex-1">
                    <h1 className="text-2xl font-bold text-gray-900">{skill.name}</h1>
                    <p className="text-gray-500 text-sm">{skill.description}</p>
                </div>
                <div className="flex items-center gap-3">
                    <Badge variant="info">v{skill.version}</Badge>
                    <Badge variant={skill.is_active ? 'success' : 'danger'}>{skill.is_active ? 'Active' : 'Inactive'}</Badge>
                    <Button onClick={() => setExecModal(true)} variant="secondary"><Play size={14} />Execute</Button>
                    <Button onClick={openAddStep}><Plus size={14} />Add Step</Button>
                </div>
            </div>

            <div className="space-y-3">
                <h2 className="text-sm font-semibold text-gray-400 uppercase tracking-wider">Skill Steps ({skill.steps?.length ?? 0})</h2>
                {(!skill.steps || skill.steps.length === 0) && (
                    <div className="bg-white border border-dashed border-gray-300 rounded-xl p-8 text-center">
                        <p className="text-gray-500">No steps yet. Add your first step to define the AI workflow.</p>
                    </div>
                )}
                {skill.steps?.map((step, idx) => (
                    <div key={step.id} className="bg-white border border-gray-200 rounded-xl p-5 hover:border-violet-200 hover:shadow-sm transition-all">
                        <div className="flex items-start gap-3">
                            <div className="flex-shrink-0 w-7 h-7 bg-violet-100 rounded-full flex items-center justify-center">
                                <span className="text-xs font-bold text-violet-600">{idx + 1}</span>
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1">
                                    <h3 className="font-semibold text-gray-900">{step.name}</h3>
                                    <span className={`text-xs border rounded-full px-2 py-0.5 font-medium ${PROVIDER_COLOR[step.ai_provider] ?? 'bg-gray-100 text-gray-600 border-gray-200'}`}>
                                        {step.ai_provider === 'openai' ? 'OpenAI' : step.ai_provider === 'claude' ? 'Claude' : step.ai_provider}
                                    </span>
                                    {step.model && <span className="text-xs text-gray-500 font-mono">{step.model}</span>}
                                </div>
                                {step.description && <p className="text-sm text-gray-400 mb-2">{step.description}</p>}
                                {step.system_prompt && (
                                    <p className="text-xs text-green-400/80 bg-green-500/5 border border-green-500/20 rounded-lg px-3 py-1.5 mb-2 italic truncate">
                                        System: {step.system_prompt}
                                    </p>
                                )}
                                <pre className="text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded-lg p-3 overflow-auto max-h-24 whitespace-pre-wrap">{step.prompt_template}</pre>
                            </div>
                            <div className="flex items-center gap-2">
                                <button onClick={() => openEditStep(step)} className="text-gray-400 hover:text-violet-600 transition-colors"><Edit2 size={14} /></button>
                                <button onClick={() => handleDeleteStep(step)} className="text-gray-400 hover:text-red-500 transition-colors"><Trash2 size={14} /></button>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <Modal open={!!modal} onClose={() => setModal(null)} title={modal === 'create' ? 'Add Step' : 'Edit Step'} size="lg">
                <form onSubmit={handleSaveStep} className="space-y-4">
                    <Input label="Step Name" value={stepForm.name} onChange={e => setStepForm(f => ({ ...f, name: e.target.value }))} required />
                    <Input label="Description" value={stepForm.description} onChange={e => setStepForm(f => ({ ...f, description: e.target.value }))} />
                    <Textarea label="Prompt Template (use {{variable}} for dynamic values)" value={stepForm.prompt_template} onChange={e => setStepForm(f => ({ ...f, prompt_template: e.target.value }))} rows={6} required />
                    <div className="grid grid-cols-2 gap-3">
                        <Select
                            label="AI Provider"
                            value={stepForm.ai_provider}
                            onChange={e => handleProviderChange(e.target.value)}
                        >
                            <option value="claude">Claude (Anthropic)</option>
                            <option value="openai">OpenAI</option>
                        </Select>
                        <Select
                            label="Model"
                            value={stepForm.model}
                            onChange={e => setStepForm(f => ({ ...f, model: e.target.value }))}
                        >
                            {(PROVIDER_MODELS[stepForm.ai_provider] ?? []).map(m => (
                                <option key={m.value} value={m.value}>{m.label}</option>
                            ))}
                        </Select>
                    </div>
                    {stepForm.ai_provider === 'openai' && (
                        <Textarea
                            label="System Prompt (OpenAI only — sets the assistant's role and behavior)"
                            value={stepForm.system_prompt}
                            onChange={e => setStepForm(f => ({ ...f, system_prompt: e.target.value }))}
                            rows={3}
                            placeholder="You are an expert cybersecurity analyst..."
                        />
                    )}
                    <div className="grid grid-cols-2 gap-3">
                        <Input label="Temperature" type="number" min="0" max="2" step="0.1" value={stepForm.temperature} onChange={e => setStepForm(f => ({ ...f, temperature: parseFloat(e.target.value) }))} />
                        <Input label="Max Tokens" type="number" min="1" value={stepForm.max_tokens} onChange={e => setStepForm(f => ({ ...f, max_tokens: parseInt(e.target.value) }))} />
                    </div>
                    <div className="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="secondary" onClick={() => setModal(null)}>Cancel</Button>
                        <Button type="submit" loading={saving}>{modal === 'create' ? 'Add Step' : 'Save'}</Button>
                    </div>
                </form>
            </Modal>

            <Modal open={execModal} onClose={() => { setExecModal(false); setExecResult(null); }} title="Execute Skill" size="lg">
                <div className="space-y-4">
                    <Textarea label="Input Data (JSON or plain text)" value={execInput} onChange={e => setExecInput(e.target.value)} placeholder={'{"text": "Hello world"}'} rows={4} />
                    {execResult && (
                        <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
                            <p className="text-xs font-semibold text-gray-500 mb-1">Result</p>
                            <pre className="text-xs text-green-400 whitespace-pre-wrap">{JSON.stringify(execResult, null, 2)}</pre>
                        </div>
                    )}
                    <div className="flex justify-end gap-3">
                        <Button variant="secondary" onClick={() => setExecModal(false)}>Close</Button>
                        <Button onClick={handleExecute} loading={executing}><Play size={14} />Run Skill</Button>
                    </div>
                </div>
            </Modal>
        </div>
    );
}
