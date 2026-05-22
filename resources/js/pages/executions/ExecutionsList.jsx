import React, { useEffect, useState, useCallback } from 'react';
import { RefreshCw, Eye, Trash2, RotateCcw } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import Card from '../../components/ui/Card.jsx';
import Table, { Pagination } from '../../components/ui/Table.jsx';
import Badge from '../../components/ui/Badge.jsx';
import Button from '../../components/ui/Button.jsx';
import { Select } from '../../components/ui/Input.jsx';
import { executionService } from '../../services/execution.service.js';

const statusVariant = { completed: 'success', failed: 'danger', running: 'info', pending: 'warning' };

export default function ExecutionsList() {
    const [executions, setExecutions] = useState([]);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState('');
    const [page, setPage] = useState(1);
    const navigate = useNavigate();

    const fetchExecutions = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await executionService.list({ type: 'workflow', status: statusFilter || undefined, page });
            setExecutions(data.data);
            setMeta(data.meta);
        } finally { setLoading(false); }
    }, [statusFilter, page]);

    useEffect(() => { fetchExecutions(); }, [fetchExecutions]);

    const handleDelete = async (exec) => {
        if (!confirm('Delete this execution?')) return;
        await executionService.delete(exec.id);
        fetchExecutions();
    };

    const handleRetry = async (exec) => {
        await executionService.retry(exec.id);
        fetchExecutions();
    };

    const columns = [
        { key: 'id', label: 'ID', render: (e) => <span className="font-mono text-xs text-gray-400">{e.id.slice(0, 8)}…</span> },
        { key: 'workflow', label: 'Workflow / Run', render: (e) => (
            <div>
                <span className="text-gray-900 font-medium">{e.workflow?.name ?? '—'}</span>
                {e.run_name && (
                    <p className="text-xs text-violet-600 mt-0.5 truncate max-w-[200px]" title={e.run_name}>{e.run_name}</p>
                )}
            </div>
        )},
        { key: 'user',   label: 'By',      render: (e) => <span className="text-gray-500">{e.user?.name ?? '—'}</span> },
        { key: 'status', label: 'Status',   render: (e) => <Badge variant={statusVariant[e.status] ?? 'default'}>{e.status}</Badge> },
        { key: 'created_at', label: 'Started', render: (e) => <span className="text-gray-500">{new Date(e.created_at).toLocaleString()}</span> },
        { key: 'actions', label: '', render: (e) => (
            <div className="flex items-center gap-2 justify-end" onClick={ev => ev.stopPropagation()}>
                <button onClick={() => navigate(`/executions/${e.id}`)} className="text-gray-400 hover:text-violet-600 transition-colors"><Eye size={15} /></button>
                {(e.status === 'failed' || e.status === 'completed') && (
                    <button onClick={() => handleRetry(e)} className="text-gray-400 hover:text-blue-600 transition-colors"><RotateCcw size={15} /></button>
                )}
                <button onClick={() => handleDelete(e)} className="text-gray-400 hover:text-red-500 transition-colors"><Trash2 size={15} /></button>
            </div>
        )},
    ];

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Execution Logs</h1>
                    <p className="text-gray-500 text-sm mt-1">Monitor workflow runs</p>
                </div>
                <Button variant="secondary" onClick={fetchExecutions}><RefreshCw size={14} />Refresh</Button>
            </div>

            <Card>
                <div className="p-4 border-b border-gray-100 flex items-center gap-4">
                    <Select value={statusFilter} onChange={e => { setStatusFilter(e.target.value); setPage(1); }} className="w-40">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="running">Running</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                    </Select>
                </div>
                <Table columns={columns} data={executions} loading={loading} emptyMessage="No executions found" onRowClick={(e) => navigate(`/executions/${e.id}`)} />
                <Pagination meta={meta} onPageChange={setPage} />
            </Card>
        </div>
    );
}
