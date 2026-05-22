import React, { useEffect, useState } from 'react';
import { Plus, Edit2, Trash2, Bot, CheckCircle, TestTube, Star } from 'lucide-react';
import Card from '../../components/ui/Card.jsx';
import Badge from '../../components/ui/Badge.jsx';
import Button from '../../components/ui/Button.jsx';
import Modal from '../../components/ui/Modal.jsx';
import Input, { Select } from '../../components/ui/Input.jsx';
import { aiSettingService } from '../../services/aiSetting.service.js';

export default function AiSettings() {
    const [settings, setSettings] = useState([]);
    const [loading, setLoading] = useState(true);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState({ provider: 'claude', api_key: '', default_model: 'claude-sonnet-4-6', temperature: 0.7, max_tokens: 2048 });
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(null);
    const [testResult, setTestResult] = useState({});

    const providerModels = {
        claude: ['claude-opus-4-7', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001'],
        openai: ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'],
    };

    const fetchSettings = async () => {
        setLoading(true);
        try {
            const { data } = await aiSettingService.list();
            setSettings(Array.isArray(data) ? data : data.data ?? []);
        } finally { setLoading(false); }
    };

    useEffect(() => { fetchSettings(); }, []);

    const openCreate = () => {
        setForm({ provider: 'claude', api_key: '', default_model: 'claude-sonnet-4-6', temperature: 0.7, max_tokens: 2048 });
        setModal('create');
    };

    const openEdit = (s) => {
        setForm({ provider: s.provider, api_key: '', default_model: s.default_model, temperature: s.temperature, max_tokens: s.max_tokens });
        setModal({ type: 'edit', setting: s });
    };

    const handleSave = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            if (modal === 'create') await aiSettingService.create(form);
            else await aiSettingService.update(modal.setting.id, form);
            setModal(null);
            fetchSettings();
        } finally { setSaving(false); }
    };

    const handleDelete = async (s) => {
        if (!confirm(`Delete ${s.provider} configuration?`)) return;
        await aiSettingService.delete(s.id);
        fetchSettings();
    };

    const handleTest = async (s) => {
        setTesting(s.id);
        try {
            const { data } = await aiSettingService.test(s.id);
            setTestResult(r => ({ ...r, [s.id]: data }));
        } finally { setTesting(null); }
    };

    const handleActivate = async (s) => {
        await aiSettingService.activate(s.id);
        fetchSettings();
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">AI Settings</h1>
                    <p className="text-gray-500 text-sm mt-1">Configure AI providers (Claude, OpenAI)</p>
                </div>
                <Button onClick={openCreate}><Plus size={16} />Add Provider</Button>
            </div>

            {loading ? (
                <div className="flex justify-center py-12"><div className="animate-spin w-8 h-8 border-2 border-violet-500 border-t-transparent rounded-full" /></div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {settings.map(s => (
                        <Card key={s.id} className={`hover:border-gray-700 transition-colors ${s.is_active ? 'border-violet-500/40' : ''}`}>
                            <div className="p-6">
                                <div className="flex items-start justify-between mb-4">
                                    <div className="flex items-center gap-3">
                                        <div className={`p-2.5 rounded-xl ${s.provider === 'claude' ? 'bg-orange-500/10' : 'bg-green-500/10'}`}>
                                            <Bot size={20} className={s.provider === 'claude' ? 'text-orange-400' : 'text-green-400'} />
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-gray-900 capitalize">{s.provider}</h3>
                                            <p className="text-xs text-gray-400 font-mono">{s.api_key_masked}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {s.is_active && <Badge variant="success">Active</Badge>}
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3 mb-4 text-sm">
                                    <div><p className="text-gray-500 text-xs">Model</p><p className="text-gray-900 font-mono text-xs mt-0.5">{s.default_model}</p></div>
                                    <div><p className="text-gray-500 text-xs">Temperature</p><p className="text-gray-900 mt-0.5">{s.temperature}</p></div>
                                    <div><p className="text-gray-500 text-xs">Max Tokens</p><p className="text-gray-900 mt-0.5">{s.max_tokens}</p></div>
                                </div>

                                {testResult[s.id] && (
                                    <div className={`p-2 rounded-lg text-xs mb-3 ${testResult[s.id].success ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400'}`}>
                                        {testResult[s.id].success ? '✓ ' + testResult[s.id].message : '✗ ' + testResult[s.id].message}
                                    </div>
                                )}

                                <div className="flex flex-wrap gap-2">
                                    {!s.is_active && (
                                        <Button size="sm" variant="secondary" onClick={() => handleActivate(s)}>
                                            <Star size={12} />Set Active
                                        </Button>
                                    )}
                                    <Button size="sm" variant="ghost" onClick={() => handleTest(s)} loading={testing === s.id}>
                                        <TestTube size={12} />Test
                                    </Button>
                                    <Button size="sm" variant="ghost" onClick={() => openEdit(s)}>
                                        <Edit2 size={12} />Edit
                                    </Button>
                                    <Button size="sm" variant="ghost" onClick={() => handleDelete(s)} className="hover:text-red-400">
                                        <Trash2 size={12} />
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    ))}

                    {settings.length === 0 && (
                        <div className="col-span-2 bg-white border border-dashed border-gray-300 rounded-xl p-12 text-center">
                            <Bot size={40} className="text-gray-300 mx-auto mb-3" />

                            <p className="text-gray-500">No AI providers configured yet.</p>
                            <p className="text-gray-400 text-sm mt-1">Add Claude or OpenAI to start executing skills.</p>
                        </div>
                    )}
                </div>
            )}

            <Modal open={!!modal} onClose={() => setModal(null)} title={modal === 'create' ? 'Add AI Provider' : 'Edit AI Provider'}>
                <form onSubmit={handleSave} className="space-y-4">
                    {modal === 'create' && (
                        <Select label="Provider" value={form.provider} onChange={e => setForm(f => ({ ...f, provider: e.target.value, default_model: providerModels[e.target.value][0] }))}>
                            <option value="claude">Claude (Anthropic)</option>
                            <option value="openai">OpenAI</option>
                        </Select>
                    )}
                    <Input label={modal === 'create' ? 'API Key' : 'API Key (leave blank to keep current)'} type="password" value={form.api_key} onChange={e => setForm(f => ({ ...f, api_key: e.target.value }))} placeholder="sk-..." required={modal === 'create'} />
                    <Select label="Default Model" value={form.default_model} onChange={e => setForm(f => ({ ...f, default_model: e.target.value }))}>
                        {(providerModels[form.provider] ?? []).map(m => <option key={m} value={m}>{m}</option>)}
                    </Select>
                    <div className="grid grid-cols-2 gap-3">
                        <Input label="Temperature" type="number" min="0" max="2" step="0.1" value={form.temperature} onChange={e => setForm(f => ({ ...f, temperature: parseFloat(e.target.value) }))} />
                        <Input label="Max Tokens" type="number" min="1" value={form.max_tokens} onChange={e => setForm(f => ({ ...f, max_tokens: parseInt(e.target.value) }))} />
                    </div>
                    <div className="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="secondary" onClick={() => setModal(null)}>Cancel</Button>
                        <Button type="submit" loading={saving}>{modal === 'create' ? 'Add Provider' : 'Save'}</Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
