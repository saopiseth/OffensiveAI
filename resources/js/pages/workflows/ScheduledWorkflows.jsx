import React, { useEffect, useState, useCallback } from 'react';
import { Plus, Search, Trash2, Play, Power, Clock, GitBranch } from 'lucide-react';
import Card from '../../components/ui/Card.jsx';
import Table, { Pagination } from '../../components/ui/Table.jsx';
import Badge from '../../components/ui/Badge.jsx';
import Button from '../../components/ui/Button.jsx';
import Modal from '../../components/ui/Modal.jsx';
import Input, { Textarea } from '../../components/ui/Input.jsx';
import { scheduleService } from '../../services/schedule.service.js';
import { workflowService } from '../../services/workflow.service.js';

const CRON_PRESETS = [
    { label: 'Every hour',    value: '0 * * * *'  },
    { label: 'Every 6 hours', value: '0 */6 * * *' },
    { label: 'Daily midnight', value: '0 0 * * *'  },
    { label: 'Weekly (Mon)',  value: '0 0 * * 1'  },
    { label: 'Custom…',       value: '__custom__'  },
];

const BLANK = { name: '', description: '', workflow_id: '', cron_expression: '0 * * * *', input_data: '', is_active: true };

export default function ScheduledWorkflows() {
    const [schedules, setSchedules] = useState([]);
    const [meta, setMeta]           = useState(null);
    const [loading, setLoading]     = useState(true);
    const [search, setSearch]       = useState('');
    const [page, setPage]           = useState(1);
    const [modal, setModal]         = useState(null);
    const [form, setForm]           = useState(BLANK);
    const [saving, setSaving]       = useState(false);
    const [runningId, setRunningId] = useState(null);
    const [workflows, setWorkflows] = useState([]);
    const [cronMode, setCronMode]   = useState('0 * * * *');

    const fetchAll = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await scheduleService.list({ page });
            setSchedules(data.data);
            setMeta(data.meta);
        } finally { setLoading(false); }
    }, [page]);

    useEffect(() => { fetchAll(); }, [fetchAll]);

    const openCreate = async () => {
        setForm(BLANK);
        setCronMode('0 * * * *');
        setModal('create');
        if (!workflows.length) {
            const wf = await workflowService.list({ per_page: 100 });
            setWorkflows(wf.data.data ?? []);
        }
    };

    const handleSave = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = {
                ...form,
                input_data: form.input_data ? JSON.parse(form.input_data) : null,
            };
            await scheduleService.create(payload);
            setModal(null);
            fetchAll();
        } catch (err) {
            alert(err.response?.data?.message ?? err.message);
        } finally { setSaving(false); }
    };

    const handleDelete = async (s) => {
        if (!confirm(`Delete schedule "${s.name}"?`)) return;
        await scheduleService.delete(s.id);
        fetchAll();
    };

    const handleToggle = async (s) => {
        await scheduleService.toggle(s.id);
        fetchAll();
    };

    const handleRun = async (s) => {
        setRunningId(s.id);
        try {
            await scheduleService.run(s.id);
            fetchAll();
        } finally { setRunningId(null); }
    };

    const handleCronPreset = (value) => {
        if (value === '__custom__') {
            setCronMode('__custom__');
        } else {
            setCronMode(value);
            setForm(f => ({ ...f, cron_expression: value }));
        }
    };

    const statusBadge = (s) => {
        const map = { completed: 'success', failed: 'danger', pending: 'warning', running: 'info' };
        return s.last_status ? <Badge variant={map[s.last_status] ?? 'default'}>{s.last_status}</Badge> : <span className="text-gray-500 text-sm">—</span>;
    };

    const columns = [
        {
            key: 'name', label: 'Schedule', render: (s) => (
                <div className="flex items-center gap-3">
                    <div className="p-1.5 bg-violet-100 rounded-lg"><Clock size={14} className="text-violet-600" /></div>
                    <div>
                        <p className="font-medium text-gray-900">{s.name}</p>
                        <p className="text-xs text-gray-400 font-mono">{s.cron_expression}</p>
                    </div>
                </div>
            ),
        },
        { key: 'workflow', label: 'Workflow', render: (s) => (
            <div className="flex items-center gap-1.5 text-sm text-gray-700">
                <GitBranch size={13} className="text-green-400" /> {s.workflow?.name ?? '—'}
            </div>
        )},
        { key: 'next_run', label: 'Next Run', render: (s) => s.next_run_at ? new Date(s.next_run_at).toLocaleString() : '—' },
        { key: 'runs', label: 'Runs', render: (s) => <span className="text-gray-400">{s.run_count}</span> },
        { key: 'last_status', label: 'Last Status', render: statusBadge },
        { key: 'active', label: 'Active', render: (s) => <Badge variant={s.is_active ? 'success' : 'warning'}>{s.is_active ? 'On' : 'Off'}</Badge> },
        {
            key: 'actions', label: '', render: (s) => (
                <div className="flex items-center gap-2 justify-end">
                    <button onClick={() => handleRun(s)} disabled={runningId === s.id} title="Run now"
                        className="text-gray-400 hover:text-green-400 disabled:opacity-40"><Play size={14} /></button>
                    <button onClick={() => handleToggle(s)} title={s.is_active ? 'Pause' : 'Resume'}
                        className={`${s.is_active ? 'text-green-400 hover:text-yellow-400' : 'text-gray-400 hover:text-green-400'}`}>
                        <Power size={14} /></button>
                    <button onClick={() => handleDelete(s)} className="text-gray-400 hover:text-red-400"><Trash2 size={14} /></button>
                </div>
            ),
        },
    ];

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <Clock size={22} className="text-violet-600" /> Scheduled Runs
                    </h1>
                    <p className="text-gray-500 text-sm mt-1">Automate workflow execution on a cron schedule</p>
                </div>
                <Button onClick={openCreate}><Plus size={16} /> New Schedule</Button>
            </div>

            <Card>
                <Table columns={columns} data={schedules} loading={loading} emptyMessage="No schedules yet." />
                <Pagination meta={meta} onPageChange={setPage} />
            </Card>

            <Modal open={modal === 'create'} onClose={() => setModal(null)} title="New Scheduled Run">
                <form onSubmit={handleSave} className="space-y-4">
                    <Input label="Name" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                        placeholder="e.g. Daily Recon Scan" required />

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Workflow</label>
                        <select value={form.workflow_id} onChange={e => setForm(f => ({ ...f, workflow_id: e.target.value }))}
                            required className="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-gray-900 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
                            <option value="">Select workflow…</option>
                            {workflows.map(w => <option key={w.id} value={w.id}>{w.name}</option>)}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Schedule</label>
                        <div className="flex flex-wrap gap-2 mb-2">
                            {CRON_PRESETS.map(p => (
                                <button key={p.value} type="button" onClick={() => handleCronPreset(p.value)}
                                    className={`px-3 py-1 text-xs rounded-lg border transition-colors ${
                                        (cronMode === p.value || (cronMode === '__custom__' && p.value === '__custom__'))
                                            ? 'border-violet-500 bg-violet-50 text-violet-700'
                                            : 'border-gray-300 text-gray-500 hover:border-gray-400'
                                    }`}>{p.label}</button>
                            ))}
                        </div>
                        <input
                            value={form.cron_expression}
                            onChange={e => { setCronMode('__custom__'); setForm(f => ({ ...f, cron_expression: e.target.value })); }}
                            placeholder="* * * * *"
                            className="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-gray-900 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
                            required
                        />
                        <p className="text-xs text-gray-500 mt-1">min hour day month weekday</p>
                    </div>

                    <Textarea label="Description" value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} rows={2} />

                    <div className="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="secondary" onClick={() => setModal(null)}>Cancel</Button>
                        <Button type="submit" loading={saving}>Create Schedule</Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
