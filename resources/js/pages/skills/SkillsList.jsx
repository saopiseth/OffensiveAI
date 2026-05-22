import React, { useEffect, useState, useCallback } from 'react';
import { Plus, Search, Edit2, Trash2, Zap, Copy, Eye, ArrowRightLeft } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import Card from '../../components/ui/Card.jsx';
import Table, { Pagination } from '../../components/ui/Table.jsx';
import Badge from '../../components/ui/Badge.jsx';
import Button from '../../components/ui/Button.jsx';
import Modal from '../../components/ui/Modal.jsx';
import Input, { Textarea } from '../../components/ui/Input.jsx';
import { skillService } from '../../services/skill.service.js';

export default function SkillsList() {
    const [skills, setSkills] = useState([]);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState({ name: '', description: '', version: '1.0.0', category: '', is_active: true });
    const [saving, setSaving] = useState(false);
    const navigate = useNavigate();

    const fetchSkills = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await skillService.list({ search, page });
            setSkills(data.data);
            setMeta(data.meta);
        } finally { setLoading(false); }
    }, [search, page]);

    useEffect(() => { fetchSkills(); }, [fetchSkills]);

    const openCreate = () => { setForm({ name: '', description: '', version: '1.0.0', category: '', is_active: true }); setModal('create'); };
    const openEdit = (skill) => { setForm({ name: skill.name, description: skill.description || '', version: skill.version, category: skill.category || '', is_active: skill.is_active }); setModal({ type: 'edit', skill }); };

    const handleSave = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            if (modal === 'create') await skillService.create(form);
            else await skillService.update(modal.skill.id, form);
            setModal(null);
            fetchSkills();
        } finally { setSaving(false); }
    };

    const handleDelete = async (skill) => {
        if (!confirm(`Delete skill "${skill.name}"?`)) return;
        await skillService.delete(skill.id);
        fetchSkills();
    };

    const handleDuplicate = async (skill) => {
        await skillService.duplicate(skill.id);
        fetchSkills();
    };

    const columns = [
        { key: 'name', label: 'Skill', render: (s) => (
            <div className="flex items-center gap-3">
                <div className="p-1.5 bg-violet-100 rounded-lg"><Zap size={14} className="text-violet-600" /></div>
                <div>
                    <p className="font-medium text-gray-900">{s.name}</p>
                    <p className="text-xs text-gray-500">{s.description?.slice(0, 60)}</p>
                </div>
            </div>
        )},
        { key: 'category', label: 'Category', render: (s) => s.category ? <Badge variant="info">{s.category}</Badge> : <span className="text-gray-500">—</span> },
        { key: 'version', label: 'Version', render: (s) => <span className="text-xs text-gray-400 font-mono">v{s.version}</span> },
        { key: 'steps_count', label: 'Steps', render: (s) => <span className="text-gray-700">{s.steps_count ?? 0}</span> },
        { key: 'created_at', label: 'Created', render: (s) => (
            <span className="text-xs text-gray-400">
                {s.created_at ? new Date(s.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'}
            </span>
        )},
        { key: 'is_active', label: 'Status', render: (s) => <Badge variant={s.is_active ? 'success' : 'danger'}>{s.is_active ? 'Active' : 'Inactive'}</Badge> },
        { key: 'actions', label: '', render: (s) => (
            <div className="flex items-center gap-2 justify-end">
                <button onClick={() => navigate(`/skills/${s.id}`)} className="text-gray-400 hover:text-violet-600 transition-colors"><Eye size={15} /></button>
                <button onClick={() => openEdit(s)} className="text-gray-400 hover:text-violet-600 transition-colors"><Edit2 size={15} /></button>
                <button onClick={() => handleDuplicate(s)} className="text-gray-400 hover:text-blue-600 transition-colors"><Copy size={15} /></button>
                <button onClick={() => handleDelete(s)} className="text-gray-400 hover:text-red-500 transition-colors"><Trash2 size={15} /></button>
            </div>
        )},
    ];

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">AI Skills</h1>
                    <p className="text-gray-500 text-sm mt-1">Create and manage AI skills</p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="secondary" onClick={() => navigate('/skills/convert')}><ArrowRightLeft size={16} />Convert to Claude</Button>
                    <Button onClick={openCreate}><Plus size={16} />New Skill</Button>
                </div>
            </div>

            <Card>
                <div className="p-4 border-b border-gray-100">
                    <div className="relative max-w-xs">
                        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} placeholder="Search skills..."
                            className="w-full bg-white border border-gray-300 rounded-lg pl-9 pr-4 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
                    </div>
                </div>
                <Table columns={columns} data={skills} loading={loading} emptyMessage="No skills yet. Create your first AI skill!" />
                <Pagination meta={meta} onPageChange={setPage} />
            </Card>

            <Modal open={!!modal} onClose={() => setModal(null)} title={modal === 'create' ? 'Create Skill' : 'Edit Skill'}>
                <form onSubmit={handleSave} className="space-y-4">
                    <Input label="Skill Name" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} placeholder="e.g. Text Summarizer" required />
                    <Textarea label="Description" value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} placeholder="What does this skill do?" rows={3} />
                    <div className="grid grid-cols-2 gap-3">
                        <Input label="Version" value={form.version} onChange={e => setForm(f => ({ ...f, version: e.target.value }))} placeholder="1.0.0" />
                        <Input label="Category" value={form.category} onChange={e => setForm(f => ({ ...f, category: e.target.value }))} placeholder="e.g. NLP" />
                    </div>
                    <label className="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" checked={form.is_active} onChange={e => setForm(f => ({ ...f, is_active: e.target.checked }))} className="accent-violet-500" />
                        <span className="text-sm text-gray-700">Active</span>
                    </label>
                    <div className="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="secondary" onClick={() => setModal(null)}>Cancel</Button>
                        <Button type="submit" loading={saving}>{modal === 'create' ? 'Create' : 'Save'}</Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
