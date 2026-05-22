import React, { useEffect, useState } from 'react';
import { Users, Zap, GitBranch, PlayCircle, CheckCircle, XCircle, Clock } from 'lucide-react';
import { StatCard } from '../components/ui/Card.jsx';
import Badge from '../components/ui/Badge.jsx';
import api from '../services/api.js';

const statusVariant = { completed: 'success', failed: 'danger', running: 'info', pending: 'warning' };

export default function Dashboard() {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get('/dashboard/stats').then(r => setStats(r.data)).finally(() => setLoading(false));
    }, []);

    if (loading) return (
        <div className="flex items-center justify-center h-64">
            <div className="animate-spin w-8 h-8 border-2 border-violet-500 border-t-transparent rounded-full" />
        </div>
    );

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
                <p className="text-gray-500 text-sm mt-1">AI Workflow Platform Overview</p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard title="Total Users"  value={stats?.users?.total ?? 0}      icon={Users}       color="violet" trend={`${stats?.users?.active ?? 0} active`} />
                <StatCard title="AI Skills"    value={stats?.skills?.total ?? 0}     icon={Zap}         color="blue"   trend={`${stats?.skills?.active ?? 0} active`} />
                <StatCard title="Workflows"    value={stats?.workflows?.total ?? 0}  icon={GitBranch}   color="green"  trend={`${stats?.workflows?.published ?? 0} published`} />
                <StatCard title="Executions"   value={stats?.executions?.total ?? 0} icon={PlayCircle}  color="yellow" trend={`${stats?.executions?.completed ?? 0} completed`} />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div className="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <h3 className="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Execution Status</h3>
                    <div className="space-y-3">
                        {[
                            { key: 'completed', label: 'Completed', icon: CheckCircle, color: 'text-green-500' },
                            { key: 'failed',    label: 'Failed',    icon: XCircle,     color: 'text-red-500'   },
                            { key: 'running',   label: 'Running',   icon: PlayCircle,  color: 'text-blue-500'  },
                            { key: 'pending',   label: 'Pending',   icon: Clock,       color: 'text-amber-500' },
                        ].map(({ key, label, icon: Icon, color }) => (
                            <div key={key} className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Icon size={16} className={color} />
                                    <span className="text-sm text-gray-600">{label}</span>
                                </div>
                                <span className="text-sm font-semibold text-gray-900">{stats?.executions?.[key] ?? 0}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="lg:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm">
                    <div className="p-5 border-b border-gray-100">
                        <h3 className="text-base font-semibold text-gray-900">Recent Executions</h3>
                    </div>
                    <div className="divide-y divide-gray-100">
                        {(stats?.recent_executions ?? []).slice(0, 8).map((exec) => (
                            <div key={exec.id} className="flex items-center justify-between px-5 py-3">
                                <div>
                                    <p className="text-sm font-medium text-gray-900">{exec.skill?.name ?? exec.type}</p>
                                    <p className="text-xs text-gray-400">{exec.user?.name} · {new Date(exec.created_at).toLocaleString()}</p>
                                </div>
                                <Badge variant={statusVariant[exec.status] ?? 'default'}>{exec.status}</Badge>
                            </div>
                        ))}
                        {(stats?.recent_executions ?? []).length === 0 && (
                            <p className="text-center text-gray-400 text-sm py-8">No executions yet</p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
