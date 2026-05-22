import React, { useEffect, useState } from 'react';
import {
    Plus, Edit2, Trash2, Shield, Eye, Play, Calendar,
    Settings, Puzzle, ChevronDown, ChevronRight, Check, X,
} from 'lucide-react';
import Card from '../../components/ui/Card.jsx';
import Badge from '../../components/ui/Badge.jsx';
import Button from '../../components/ui/Button.jsx';
import Modal from '../../components/ui/Modal.jsx';
import Input from '../../components/ui/Input.jsx';
import { roleService } from '../../services/user.service.js';

// ── Permission metadata ────────────────────────────────────────────────────────

const GROUP_META = {
    manage:   { label: 'Management',  icon: Settings,  color: 'text-violet-600', bg: 'bg-violet-50', badge: 'border-violet-200 text-violet-700' },
    view:     { label: 'View Access', icon: Eye,       color: 'text-blue-600',   bg: 'bg-blue-50',   badge: 'border-blue-200 text-blue-700'     },
    execute:  { label: 'Execution',   icon: Play,      color: 'text-green-600',  bg: 'bg-green-50',  badge: 'border-green-200 text-green-700'   },
    schedule: { label: 'Scheduling',  icon: Calendar,  color: 'text-amber-600',  bg: 'bg-amber-50',  badge: 'border-amber-200 text-amber-700'   },
    custom:   { label: 'Custom',      icon: Puzzle,    color: 'text-gray-500',   bg: 'bg-gray-100',  badge: 'border-gray-200 text-gray-600'     },
};

function getGroup(name) {
    const prefix = name.split('_')[0];
    return GROUP_META[prefix] ?? GROUP_META.custom;
}

function toLabel(name) {
    return name.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function groupPermissions(permissions) {
    const groups = {};
    permissions.forEach(p => {
        const key = Object.keys(GROUP_META).find(k => p.name.startsWith(k + '_')) ?? 'custom';
        if (!groups[key]) groups[key] = [];
        groups[key].push(p);
    });
    return groups;
}

// ── PermissionPill ─────────────────────────────────────────────────────────────

function PermissionPill({ name, onDelete }) {
    const meta = getGroup(name);
    return (
        <span className={`inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full border ${meta.badge} ${meta.bg}`}>
            {toLabel(name)}
            {onDelete && (
                <button onClick={onDelete} className="ml-0.5 opacity-60 hover:opacity-100 transition-opacity">
                    <X size={10} />
                </button>
            )}
        </span>
    );
}

// ── PermissionPicker (inside modal) ──────────────────────────────────────────

function PermissionPicker({ allPermissions, selected, onChange }) {
    const [openGroups, setOpenGroups] = useState({});
    const grouped = groupPermissions(allPermissions);

    const toggle = (name) => {
        onChange(selected.includes(name)
            ? selected.filter(p => p !== name)
            : [...selected, name]
        );
    };

    const toggleGroup = (key, names) => {
        const allSelected = names.every(n => selected.includes(n));
        onChange(allSelected
            ? selected.filter(n => !names.includes(n))
            : [...new Set([...selected, ...names])]
        );
    };

    const toggleSection = (key) => setOpenGroups(s => ({ ...s, [key]: !s[key] }));

    // Default all groups open
    useEffect(() => {
        const init = {};
        Object.keys(grouped).forEach(k => { init[k] = true; });
        setOpenGroups(init);
    }, [allPermissions.length]);

    const total    = allPermissions.length;
    const selCount = selected.length;

    return (
        <div>
            <div className="flex items-center justify-between mb-2">
                <label className="text-sm font-medium text-gray-700">
                    Permissions
                    <span className="ml-2 text-xs text-gray-500">{selCount} / {total} selected</span>
                </label>
                <div className="flex gap-2 text-xs">
                    <button
                        type="button"
                        onClick={() => onChange(allPermissions.map(p => p.name))}
                        className="text-violet-600 hover:text-violet-700"
                    >
                        Select all
                    </button>
                    <span className="text-gray-700">·</span>
                    <button
                        type="button"
                        onClick={() => onChange([])}
                        className="text-gray-400 hover:text-gray-600"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
                {Object.entries(grouped).map(([key, perms]) => {
                    const meta      = GROUP_META[key] ?? GROUP_META.custom;
                    const Icon      = meta.icon;
                    const names     = perms.map(p => p.name);
                    const allSel    = names.every(n => selected.includes(n));
                    const someSel   = names.some(n => selected.includes(n));
                    const isOpen    = openGroups[key] ?? true;

                    return (
                        <div key={key} className="border border-gray-200 rounded-lg overflow-hidden">
                            {/* Group header */}
                            <div className="flex items-center justify-between px-3 py-2 bg-gray-50">
                                <button
                                    type="button"
                                    onClick={() => toggleSection(key)}
                                    className="flex items-center gap-2 flex-1 text-left"
                                >
                                    <Icon size={13} className={meta.color} />
                                    <span className="text-xs font-semibold text-gray-700">{meta.label}</span>
                                    <span className="text-xs text-gray-400">({perms.length})</span>
                                    {isOpen
                                        ? <ChevronDown size={12} className="text-gray-400 ml-auto" />
                                        : <ChevronRight size={12} className="text-gray-400 ml-auto" />
                                    }
                                </button>
                                <button
                                    type="button"
                                    onClick={() => toggleGroup(key, names)}
                                    className={`text-xs ml-3 flex-shrink-0 transition-colors ${
                                        allSel ? 'text-violet-600 hover:text-violet-700' : 'text-gray-500 hover:text-gray-700'
                                    }`}
                                >
                                    {allSel ? 'Deselect all' : 'Select all'}
                                </button>
                            </div>

                            {/* Permission toggles */}
                            {isOpen && (
                                <div className="p-2 grid grid-cols-2 gap-1 bg-white">
                                    {perms.map(p => {
                                        const checked = selected.includes(p.name);
                                        return (
                                            <button
                                                key={p.id}
                                                type="button"
                                                onClick={() => toggle(p.name)}
                                                className={`flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-left transition-all ${
                                                    checked
                                                        ? `${meta.bg} ${meta.color} border border-current/30`
                                                        : 'text-gray-500 border border-gray-200 hover:border-gray-400 hover:text-gray-700'
                                                }`}
                                            >
                                                <span className={`w-4 h-4 rounded flex-shrink-0 flex items-center justify-center border ${
                                                    checked ? 'bg-violet-500 border-violet-500' : 'border-gray-300'
                                                }`}>
                                                    {checked && <Check size={10} className="text-white" />}
                                                </span>
                                                {toLabel(p.name)}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    );
                })}

                {allPermissions.length === 0 && (
                    <p className="text-sm text-gray-500 text-center py-4">No permissions defined yet.</p>
                )}
            </div>
        </div>
    );
}

// ── AddPermissionInline ────────────────────────────────────────────────────────

function AddPermissionInline({ onAdd, onCancel }) {
    const [value, setValue] = useState('');
    const [saving, setSaving] = useState('');
    const [error, setError] = useState('');

    const slug = value.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');

    const handleAdd = async () => {
        if (!slug) return;
        setSaving(true);
        setError('');
        try {
            await onAdd(slug);
        } catch (e) {
            setError(e.response?.data?.message ?? e.response?.data?.errors?.name?.[0] ?? 'Failed');
        } finally { setSaving(false); }
    };

    return (
        <div className="flex items-start gap-2 p-3 bg-violet-50 border border-violet-200 rounded-lg">
            <div className="flex-1">
                <input
                    autoFocus
                    value={value}
                    onChange={e => { setValue(e.target.value); setError(''); }}
                    onKeyDown={e => { if (e.key === 'Enter') handleAdd(); if (e.key === 'Escape') onCancel(); }}
                    placeholder="e.g. export_reports"
                    className="w-full bg-white border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-violet-500 focus:border-violet-500"
                />
                {slug && slug !== value && (
                    <p className="text-xs text-gray-500 mt-1">Will be saved as: <code className="text-violet-600">{slug}</code></p>
                )}
                {error && <p className="text-xs text-red-500 mt-1">{error}</p>}
            </div>
            <button
                onClick={handleAdd}
                disabled={!slug || saving}
                className="px-3 py-1.5 bg-violet-600 hover:bg-violet-500 disabled:opacity-40 text-white text-sm font-medium rounded-lg transition-colors flex-shrink-0"
            >
                {saving ? '…' : 'Add'}
            </button>
            <button onClick={onCancel} className="text-gray-400 hover:text-gray-600 flex-shrink-0 pt-1.5">
                <X size={16} />
            </button>
        </div>
    );
}

// ── Main component ─────────────────────────────────────────────────────────────

export default function RolesList() {
    const [roles, setRoles]               = useState([]);
    const [allPermissions, setAllPerms]   = useState([]);
    const [loading, setLoading]           = useState(true);
    const [modal, setModal]               = useState(null);  // null | 'create' | { type:'edit', role }
    const [form, setForm]                 = useState({ name: '', permissions: [] });
    const [saving, setSaving]             = useState(false);
    const [addingPerm, setAddingPerm]     = useState(false);

    const fetchAll = async () => {
        setLoading(true);
        try {
            const [rolesRes, permsRes] = await Promise.all([roleService.list(), roleService.permissions()]);
            setRoles(rolesRes.data);
            setAllPerms(permsRes.data);
        } finally { setLoading(false); }
    };

    useEffect(() => { fetchAll(); }, []);

    // ── Role CRUD ────────────────────────────────────────────────────────────

    const openCreate = () => { setForm({ name: '', permissions: [] }); setModal('create'); };
    const openEdit   = (role) => {
        setForm({ name: role.name, permissions: role.permissions?.map(p => p.name) ?? [] });
        setModal({ type: 'edit', role });
    };

    const handleSave = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            if (modal === 'create') await roleService.create(form);
            else await roleService.update(modal.role.id, form);
            setModal(null);
            fetchAll();
        } finally { setSaving(false); }
    };

    const handleDeleteRole = async (role) => {
        if (!confirm(`Delete role "${role.name}"?`)) return;
        await roleService.delete(role.id);
        fetchAll();
    };

    // ── Permission CRUD ──────────────────────────────────────────────────────

    const handleAddPermission = async (name) => {
        await roleService.createPermission(name);
        const { data } = await roleService.permissions();
        setAllPerms(data);
        setAddingPerm(false);
    };

    const handleDeletePermission = async (perm) => {
        if (!confirm(`Delete permission "${perm.name}"? It will be removed from all roles.`)) return;
        await roleService.deletePermission(perm.id);
        fetchAll();
    };

    const grouped = groupPermissions(allPermissions);

    return (
        <div className="space-y-6">
            {/* ── Header ─────────────────────────────────────────────── */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Roles & Permissions</h1>
                    <p className="text-gray-500 text-sm mt-1">Manage access control and permission assignments</p>
                </div>
                <Button onClick={openCreate}><Plus size={16} /> Add Role</Button>
            </div>

            {loading ? (
                <div className="flex justify-center py-12">
                    <div className="animate-spin w-8 h-8 border-2 border-violet-500 border-t-transparent rounded-full" />
                </div>
            ) : (
                <>
                    {/* ── Roles grid ─────────────────────────────────────── */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {roles.map(role => {
                            const permGroups = groupPermissions(role.permissions ?? []);
                            return (
                                <Card key={role.id} className="hover:border-gray-700 transition-colors">
                                    <div className="p-5">
                                        <div className="flex items-start justify-between mb-4">
                                            <div className="flex items-center gap-2.5">
                                                <div className="p-2 bg-violet-100 rounded-lg">
                                                    <Shield size={16} className="text-violet-600" />
                                                </div>
                                                <div>
                                                    <h3 className="font-semibold text-gray-900 capitalize">{role.name}</h3>
                                                    <p className="text-xs text-gray-500">{role.users_count ?? 0} user{role.users_count !== 1 ? 's' : ''}</p>
                                                </div>
                                            </div>
                                            <div className="flex gap-1.5">
                                                <button onClick={() => openEdit(role)} className="p-1.5 text-gray-400 hover:text-violet-600 hover:bg-violet-50 rounded-lg transition-colors">
                                                    <Edit2 size={13} />
                                                </button>
                                                <button onClick={() => handleDeleteRole(role)} className="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                                    <Trash2 size={13} />
                                                </button>
                                            </div>
                                        </div>

                                        {/* Permission count summary */}
                                        <div className="mb-3 flex items-center gap-2">
                                            <span className="text-xs text-gray-500">
                                                {(role.permissions?.length ?? 0)} permission{role.permissions?.length !== 1 ? 's' : ''}
                                            </span>
                                            {(role.permissions?.length ?? 0) > 0 && (
                                                <div className="flex -space-x-1">
                                                    {Object.keys(permGroups).map(k => {
                                                        const Icon = GROUP_META[k]?.icon ?? Puzzle;
                                                        return (
                                                            <span key={k} className={`w-5 h-5 rounded-full flex items-center justify-center ${GROUP_META[k]?.bg ?? 'bg-gray-100'} border border-white`} title={GROUP_META[k]?.label}>
                                                                <Icon size={10} className={GROUP_META[k]?.color ?? 'text-gray-400'} />
                                                            </span>
                                                        );
                                                    })}
                                                </div>
                                            )}
                                        </div>

                                        {/* Permission pills */}
                                        <div className="flex flex-wrap gap-1.5">
                                            {role.permissions?.map(p => (
                                                <PermissionPill key={p.id} name={p.name} />
                                            ))}
                                            {(!role.permissions || role.permissions.length === 0) && (
                                                <span className="text-xs text-gray-600 italic">No permissions assigned</span>
                                            )}
                                        </div>
                                    </div>
                                </Card>
                            );
                        })}
                    </div>

                    {/* ── Permissions manager ─────────────────────────────── */}
                    <Card>
                        <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h2 className="font-semibold text-gray-900">Permissions</h2>
                                <p className="text-xs text-gray-500 mt-0.5">{allPermissions.length} permission{allPermissions.length !== 1 ? 's' : ''} defined</p>
                            </div>
                            {!addingPerm && (
                                <Button size="sm" variant="secondary" onClick={() => setAddingPerm(true)}>
                                    <Plus size={13} /> Add Permission
                                </Button>
                            )}
                        </div>

                        <div className="p-5 space-y-5">
                            {addingPerm && (
                                <AddPermissionInline onAdd={handleAddPermission} onCancel={() => setAddingPerm(false)} />
                            )}

                            {Object.entries(grouped).map(([key, perms]) => {
                                const meta = GROUP_META[key] ?? GROUP_META.custom;
                                const Icon = meta.icon;
                                return (
                                    <div key={key}>
                                        <div className="flex items-center gap-2 mb-2">
                                            <Icon size={13} className={meta.color} />
                                            <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">{meta.label}</span>
                                            <span className="text-xs text-gray-600">({perms.length})</span>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {perms.map(p => (
                                                <PermissionPill
                                                    key={p.id}
                                                    name={p.name}
                                                    onDelete={() => handleDeletePermission(p)}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}

                            {allPermissions.length === 0 && !addingPerm && (
                                <p className="text-sm text-gray-500 text-center py-6">No permissions yet. Add one above.</p>
                            )}
                        </div>
                    </Card>
                </>
            )}

            {/* ── Create / Edit Role Modal ────────────────────────────── */}
            <Modal
                open={!!modal}
                onClose={() => setModal(null)}
                title={modal === 'create' ? 'Create Role' : `Edit Role — ${modal?.role?.name}`}
            >
                <form onSubmit={handleSave} className="space-y-5">
                    <Input
                        label="Role Name"
                        value={form.name}
                        onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                        placeholder="e.g. analyst"
                        required
                    />

                    <PermissionPicker
                        allPermissions={allPermissions}
                        selected={form.permissions}
                        onChange={perms => setForm(f => ({ ...f, permissions: perms }))}
                    />

                    <div className="flex justify-end gap-3 pt-1">
                        <Button type="button" variant="secondary" onClick={() => setModal(null)}>Cancel</Button>
                        <Button type="submit" loading={saving}>
                            {modal === 'create' ? 'Create Role' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
