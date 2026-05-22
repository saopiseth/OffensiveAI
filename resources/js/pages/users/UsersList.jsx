import React, { useEffect, useState, useCallback } from 'react';
import { Plus, Search, Edit2, Trash2, UserCheck, UserX, Shield } from 'lucide-react';
import Card from '../../components/ui/Card.jsx';
import Table, { Pagination } from '../../components/ui/Table.jsx';
import Badge from '../../components/ui/Badge.jsx';
import Button from '../../components/ui/Button.jsx';
import Modal from '../../components/ui/Modal.jsx';
import Input, { Select } from '../../components/ui/Input.jsx';
import { userService, roleService } from '../../services/user.service.js';

export default function UsersList() {
    const [users, setUsers] = useState([]);
    const [meta, setMeta] = useState(null);
    const [roles, setRoles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [modal, setModal] = useState(null);
    const [form, setForm] = useState({ name: '', email: '', password: '', is_active: true, roles: [] });
    const [saving, setSaving] = useState(false);

    const fetchUsers = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await userService.list({ search, page });
            setUsers(data.data);
            setMeta(data.meta);
        } finally {
            setLoading(false);
        }
    }, [search, page]);

    useEffect(() => { fetchUsers(); }, [fetchUsers]);
    useEffect(() => { roleService.list().then(r => setRoles(r.data)); }, []);

    const openCreate = () => {
        setForm({ name: '', email: '', password: '', is_active: true, roles: [] });
        setModal('create');
    };

    const openEdit = (user) => {
        setForm({ name: user.name, email: user.email, password: '', is_active: user.is_active, roles: user.roles?.map(r => r.name) ?? [] });
        setModal({ type: 'edit', user });
    };

    const handleSave = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            if (modal === 'create') {
                await userService.create(form);
            } else {
                await userService.update(modal.user.id, form);
            }
            setModal(null);
            fetchUsers();
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (user) => {
        if (!confirm(`Delete user ${user.name}?`)) return;
        await userService.delete(user.id);
        fetchUsers();
    };

    const handleToggle = async (user) => {
        await userService.toggleStatus(user.id);
        fetchUsers();
    };

    const columns = [
        { key: 'name', label: 'User', render: (u) => (
            <div>
                <p className="font-medium text-gray-900">{u.name}</p>
                <p className="text-xs text-gray-500">{u.email}</p>
            </div>
        )},
        { key: 'roles', label: 'Roles', render: (u) => (
            <div className="flex flex-wrap gap-1">
                {u.roles?.map(r => <Badge key={r.id} variant="violet">{r.name}</Badge>)}
            </div>
        )},
        { key: 'is_active', label: 'Status', render: (u) => (
            <Badge variant={u.is_active ? 'success' : 'danger'}>{u.is_active ? 'Active' : 'Inactive'}</Badge>
        )},
        { key: 'created_at', label: 'Joined', render: (u) => new Date(u.created_at).toLocaleDateString() },
        { key: 'actions', label: '', render: (u) => (
            <div className="flex items-center gap-2 justify-end">
                <button onClick={() => openEdit(u)} className="text-gray-400 hover:text-violet-600 transition-colors"><Edit2 size={15} /></button>
                <button onClick={() => handleToggle(u)} className={u.is_active ? 'text-gray-400 hover:text-amber-500 transition-colors' : 'text-gray-400 hover:text-green-600 transition-colors'}>
                    {u.is_active ? <UserX size={15} /> : <UserCheck size={15} />}
                </button>
                <button onClick={() => handleDelete(u)} className="text-gray-400 hover:text-red-500 transition-colors"><Trash2 size={15} /></button>
            </div>
        )},
    ];

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Users</h1>
                    <p className="text-gray-500 text-sm mt-1">Manage platform users</p>
                </div>
                <Button onClick={openCreate}><Plus size={16} />Add User</Button>
            </div>

            <Card>
                <div className="p-4 border-b border-gray-100">
                    <div className="relative max-w-xs">
                        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input
                            value={search}
                            onChange={e => { setSearch(e.target.value); setPage(1); }}
                            placeholder="Search users..."
                            className="w-full bg-white border border-gray-300 rounded-lg pl-9 pr-4 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
                        />
                    </div>
                </div>
                <Table columns={columns} data={users} loading={loading} emptyMessage="No users found" />
                <Pagination meta={meta} onPageChange={setPage} />
            </Card>

            <Modal open={!!modal} onClose={() => setModal(null)} title={modal === 'create' ? 'Create User' : 'Edit User'}>
                <form onSubmit={handleSave} className="space-y-4">
                    <Input label="Name" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required />
                    <Input label="Email" type="email" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} required />
                    <Input label={modal === 'create' ? 'Password' : 'New Password (leave blank to keep)'} type="password" value={form.password} onChange={e => setForm(f => ({ ...f, password: e.target.value }))} required={modal === 'create'} />
                    <div className="space-y-1">
                        <label className="block text-sm font-medium text-gray-700">Roles</label>
                        <div className="flex flex-wrap gap-2">
                            {roles.map(r => (
                                <label key={r.id} className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={form.roles.includes(r.name)}
                                        onChange={e => setForm(f => ({
                                            ...f,
                                            roles: e.target.checked ? [...f.roles, r.name] : f.roles.filter(x => x !== r.name)
                                        }))}
                                        className="accent-violet-500"
                                    />
                                    <span className="text-sm text-gray-700">{r.name}</span>
                                </label>
                            ))}
                        </div>
                    </div>
                    <div className="flex justify-end gap-3 pt-2">
                        <Button type="button" variant="secondary" onClick={() => setModal(null)}>Cancel</Button>
                        <Button type="submit" loading={saving}>{modal === 'create' ? 'Create' : 'Save'}</Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
