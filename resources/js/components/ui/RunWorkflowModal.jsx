import React, { useState, useEffect, useMemo } from 'react';
import {
    Play, Globe, Server, Shield, Target, ChevronDown,
    CheckCircle2, XCircle, AlertTriangle, Loader2,
    Hash, ChevronRight, Cpu, Plus, Trash2, Eye, EyeOff,
    KeyRound, User, Lock, Crosshair, Radio, ToggleLeft, ToggleRight,
    GitBranch, FolderGit2, BookOpen, List,
} from 'lucide-react';
import Modal from './Modal.jsx';
import Button from './Button.jsx';
import { workflowService } from '../../services/workflow.service.js';

// ── Workflow type detection ───────────────────────────────────────────────────

const WORKFLOW_TYPES = {
    webapp: {
        label:       'Target URL',
        placeholder: 'https://example.com',
        hint:        'Full URL of the web application to assess',
        icon:        Globe,
        iconColor:   'text-violet-400',
        inputType:   'url',
        targetType:  'url',
        badge:       'Web App',
        badgeColor:  'bg-violet-500/10 text-violet-300 border-violet-500/30',
        credentials: true,
        burp:        true,
        repo:        true,
    },
    network: {
        label:       'Target IP / CIDR / Hostname',
        placeholder: '192.168.1.0/24  or  10.0.0.1  or  internal.corp',
        hint:        'IP address, CIDR range, or hostname of the network target',
        icon:        Server,
        iconColor:   'text-blue-400',
        inputType:   'text',
        targetType:  'cidr',
        badge:       'Network',
        badgeColor:  'bg-blue-500/10 text-blue-300 border-blue-500/30',
        credentials: false,
    },
    redteam: {
        label:       'Target Domain / IP Range',
        placeholder: 'corp.example.com  or  10.0.0.0/8',
        hint:        'Primary domain or IP range for the engagement scope',
        icon:        Shield,
        iconColor:   'text-red-400',
        inputType:   'text',
        targetType:  'domain',
        badge:       'Red Team',
        badgeColor:  'bg-red-500/10 text-red-300 border-red-500/30',
        credentials: false,
    },
    generic: {
        label:       'Target',
        placeholder: 'IP address, domain, or URL',
        hint:        'The primary target for this workflow',
        icon:        Target,
        iconColor:   'text-gray-400',
        inputType:   'text',
        targetType:  'host',
        badge:       null,
        badgeColor:  '',
        credentials: false,
    },
};

function detectType(name = '') {
    const n = name.toLowerCase();
    if (n.includes('web application') || n.includes('web app') || n.includes('webapp')) return 'webapp';
    if (n.includes('network') || n.includes('infrastructure')) return 'network';
    if (n.includes('red team') || n.includes('red-team') || n.includes('redteam')) return 'redteam';
    return 'generic';
}

// ── Credential row ────────────────────────────────────────────────────────────

function CredentialRow({ cred, onChange, onRemove }) {
    const [showPw, setShowPw] = useState(false);

    return (
        <div className="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 items-center">
            {/* Role */}
            <div className="relative">
                <User size={12} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                <input
                    type="text"
                    value={cred.role}
                    onChange={e => onChange('role', e.target.value)}
                    placeholder="Role (e.g. Admin)"
                    className="w-full bg-gray-800 border border-gray-700 rounded-lg pl-7 pr-2 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                />
            </div>

            {/* Username */}
            <div className="relative">
                <KeyRound size={12} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                <input
                    type="text"
                    value={cred.username}
                    onChange={e => onChange('username', e.target.value)}
                    placeholder="Username / email"
                    autoComplete="off"
                    className="w-full bg-gray-800 border border-gray-700 rounded-lg pl-7 pr-2 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-violet-500 font-mono"
                />
            </div>

            {/* Password */}
            <div className="relative">
                <Lock size={12} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                <input
                    type={showPw ? 'text' : 'password'}
                    value={cred.password}
                    onChange={e => onChange('password', e.target.value)}
                    placeholder="Password"
                    autoComplete="new-password"
                    className="w-full bg-gray-800 border border-gray-700 rounded-lg pl-7 pr-7 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-violet-500 font-mono"
                />
                <button
                    type="button"
                    onClick={() => setShowPw(s => !s)}
                    className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300"
                    tabIndex={-1}
                >
                    {showPw ? <EyeOff size={11} /> : <Eye size={11} />}
                </button>
            </div>

            {/* Remove */}
            <button
                type="button"
                onClick={onRemove}
                className="text-gray-600 hover:text-red-400 transition-colors p-1"
            >
                <Trash2 size={13} />
            </button>
        </div>
    );
}

// ── Credentials section ───────────────────────────────────────────────────────

function CredentialsSection({ credentials, setCredentials }) {
    const [open, setOpen] = useState(false);

    const addRow = () =>
        setCredentials(cs => [...cs, { id: crypto.randomUUID(), role: '', username: '', password: '' }]);

    const removeRow = (id) =>
        setCredentials(cs => cs.filter(c => c.id !== id));

    const updateRow = (id, field, value) =>
        setCredentials(cs => cs.map(c => c.id === id ? { ...c, [field]: value } : c));

    const filledCount = credentials.filter(c => c.username.trim() || c.password.trim()).length;

    return (
        <div className="border border-gray-800 rounded-lg overflow-hidden">
            <button
                type="button"
                onClick={() => setOpen(s => !s)}
                className="w-full flex items-center justify-between px-3 py-2.5 bg-gray-900 hover:bg-gray-800/80 transition-colors text-sm"
            >
                <span className="flex items-center gap-2 text-gray-300 font-medium">
                    <KeyRound size={13} className="text-amber-400" />
                    Test Credentials
                    {filledCount > 0 && (
                        <span className="text-xs bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-full px-1.5 py-0.5 font-normal">
                            {filledCount} account{filledCount !== 1 ? 's' : ''}
                        </span>
                    )}
                </span>
                <span className="flex items-center gap-1.5 text-xs text-gray-500">
                    <span className="text-gray-600">Role / Username / Password</span>
                    <ChevronDown size={13} className={`transition-transform ${open ? 'rotate-180' : ''}`} />
                </span>
            </button>

            {open && (
                <div className="px-3 pb-3 pt-2 bg-gray-900/50 space-y-2">
                    {/* Column headers */}
                    <div className="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 px-0.5">
                        <span className="text-xs text-gray-500">Role</span>
                        <span className="text-xs text-gray-500">Username / Email</span>
                        <span className="text-xs text-gray-500">Password</span>
                        <span />
                    </div>

                    {credentials.length === 0 ? (
                        <p className="text-xs text-gray-600 py-1 text-center">
                            No credentials added — click Add to include test accounts
                        </p>
                    ) : (
                        credentials.map(cred => (
                            <CredentialRow
                                key={cred.id}
                                cred={cred}
                                onChange={(field, value) => updateRow(cred.id, field, value)}
                                onRemove={() => removeRow(cred.id)}
                            />
                        ))
                    )}

                    <button
                        type="button"
                        onClick={addRow}
                        className="flex items-center gap-1.5 text-xs text-amber-400 hover:text-amber-300 transition-colors mt-1"
                    >
                        <Plus size={12} /> Add credential
                    </button>

                    <p className="text-xs text-gray-600 pt-1">
                        Injected as <code className="text-gray-500">{'{{credentials}}'}</code> into skill prompts — stored in execution context only.
                    </p>
                </div>
            )}
        </div>
    );
}

// ── Code repository section ───────────────────────────────────────────────────

const REPO_DEFAULTS = {
    enabled:  false,
    url:      '',
    branch:   'main',
    token:    '',
    username: '',
};

function detectProvider(url = '') {
    const u = url.toLowerCase();
    if (u.includes('github.com'))   return { name: 'GitHub',    color: 'text-white',        dot: 'bg-gray-300'  };
    if (u.includes('gitlab'))       return { name: 'GitLab',    color: 'text-orange-300',   dot: 'bg-orange-400' };
    if (u.includes('bitbucket'))    return { name: 'Bitbucket', color: 'text-blue-300',     dot: 'bg-blue-400'  };
    if (u.startsWith('http'))       return { name: 'Self-hosted Git', color: 'text-gray-300', dot: 'bg-gray-500' };
    return null;
}

function RepoSection({ repo, setRepo }) {
    const [open,     setOpen]     = useState(false);
    const [showTok,  setShowTok]  = useState(false);
    const provider = detectProvider(repo.url);

    const update = (field, value) => setRepo(r => ({ ...r, [field]: value }));
    const toggle = () => { setRepo(r => ({ ...r, enabled: !r.enabled })); if (!open) setOpen(true); };

    const isBitbucket = repo.url.toLowerCase().includes('bitbucket');

    return (
        <div className={`border rounded-lg overflow-hidden transition-colors ${
            repo.enabled ? 'border-indigo-500/40' : 'border-gray-800'
        }`}>
            {/* Header */}
            <div className="flex items-center justify-between px-3 py-2.5 bg-gray-900">
                <button
                    type="button"
                    onClick={() => setOpen(s => !s)}
                    className="flex items-center gap-2 text-sm flex-1 text-left"
                >
                    <FolderGit2 size={13} className={repo.enabled ? 'text-indigo-400' : 'text-gray-500'} />
                    <span className={`font-medium ${repo.enabled ? 'text-gray-200' : 'text-gray-400'}`}>
                        Code Repository
                    </span>
                    {repo.enabled && provider && (
                        <span className={`text-xs border border-gray-600 bg-gray-800 rounded-full px-1.5 py-0.5 font-normal ${provider.color}`}>
                            <span className={`inline-block w-1.5 h-1.5 rounded-full mr-1 ${provider.dot}`} />
                            {provider.name}
                        </span>
                    )}
                    {repo.enabled && repo.url && !provider && (
                        <span className="text-xs text-indigo-400 border border-indigo-500/20 bg-indigo-500/10 rounded-full px-1.5 py-0.5 font-normal">
                            Active
                        </span>
                    )}
                    <ChevronDown size={13} className={`ml-auto text-gray-500 transition-transform ${open ? 'rotate-180' : ''}`} />
                </button>

                <button
                    type="button"
                    onClick={toggle}
                    className="ml-3 flex-shrink-0"
                    title={repo.enabled ? 'Disable repo integration' : 'Enable repo integration'}
                >
                    {repo.enabled
                        ? <ToggleRight size={22} className="text-indigo-400" />
                        : <ToggleLeft  size={22} className="text-gray-600"  />}
                </button>
            </div>

            {open && (
                <div className="px-3 pb-3 pt-2 bg-gray-900/50 space-y-3">
                    {/* Info */}
                    <div className="flex items-start gap-2 text-xs text-gray-400 bg-gray-800/60 rounded-lg px-3 py-2 border border-gray-700/50">
                        <BookOpen size={12} className="text-indigo-400 flex-shrink-0 mt-0.5" />
                        <span>
                            Security-relevant source files are fetched via the platform API and injected into skill prompts as{' '}
                            <code className="text-gray-400">{'{{repo_code}}'}</code>,{' '}
                            <code className="text-gray-400">{'{{repo_structure}}'}</code>, and{' '}
                            <code className="text-gray-400">{'{{repo_summary}}'}</code>.
                            Supports GitHub, GitLab (cloud &amp; self-hosted), and Bitbucket.
                        </span>
                    </div>

                    {/* URL + provider badge */}
                    <div>
                        <label className="block text-xs text-gray-400 mb-1">Repository URL</label>
                        <div className="relative">
                            <GitBranch size={12} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" />
                            <input
                                type="url"
                                value={repo.url}
                                onChange={e => update('url', e.target.value)}
                                placeholder="https://github.com/org/repo  or  https://gitlab.corp.com/group/project"
                                className="w-full bg-gray-800 border border-gray-700 rounded-lg pl-8 pr-3 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono"
                            />
                        </div>
                        {provider && (
                            <p className={`text-xs mt-1 ${provider.color}`}>
                                Detected: {provider.name}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        {/* Branch */}
                        <div>
                            <label className="block text-xs text-gray-400 mb-1">Branch</label>
                            <input
                                type="text"
                                value={repo.branch}
                                onChange={e => update('branch', e.target.value)}
                                placeholder="main"
                                className="w-full bg-gray-800 border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono"
                            />
                        </div>

                        {/* Username — only relevant for Bitbucket basic auth */}
                        <div>
                            <label className="block text-xs text-gray-400 mb-1">
                                Username
                                {!isBitbucket && <span className="text-gray-600 ml-1">(Bitbucket only)</span>}
                            </label>
                            <input
                                type="text"
                                value={repo.username}
                                onChange={e => update('username', e.target.value)}
                                placeholder={isBitbucket ? 'Bitbucket username' : 'Leave blank'}
                                disabled={!isBitbucket}
                                className={`w-full bg-gray-800 border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono ${
                                    !isBitbucket ? 'opacity-40 cursor-not-allowed' : ''
                                }`}
                            />
                        </div>
                    </div>

                    {/* Access token */}
                    <div>
                        <label className="block text-xs text-gray-400 mb-1">
                            Access Token / App Password
                            <span className="text-gray-600 ml-1">
                                {isBitbucket
                                    ? '(Bitbucket App Password)'
                                    : repo.url.includes('github') ? '(GitHub PAT — ghp_…)' : '(Personal Access Token)'}
                            </span>
                        </label>
                        <div className="relative">
                            <input
                                type={showTok ? 'text' : 'password'}
                                value={repo.token}
                                onChange={e => update('token', e.target.value)}
                                placeholder="Paste token here — only used to read repository files"
                                autoComplete="off"
                                className="w-full bg-gray-800 border border-gray-700 rounded-lg px-2.5 pr-8 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono"
                            />
                            <button
                                type="button"
                                onClick={() => setShowTok(s => !s)}
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300"
                                tabIndex={-1}
                            >
                                {showTok ? <EyeOff size={11} /> : <Eye size={11} />}
                            </button>
                        </div>
                        <p className="text-xs text-gray-600 mt-1">
                            Requires read-only repo scope. Not stored — used only during this execution.
                        </p>
                    </div>
                </div>
            )}
        </div>
    );
}

// ── Burp Suite integration section ───────────────────────────────────────────

const BURP_DEFAULTS = {
    enabled: false,
    proxy:   'http://127.0.0.1:8080',
    api_url: 'http://127.0.0.1:1337',
    api_key: '',
};

function BurpSuiteSection({ burp, setBurp }) {
    const [open,   setOpen]   = useState(false);
    const [showKey, setShowKey] = useState(false);

    const update = (field, value) => setBurp(b => ({ ...b, [field]: value }));
    const toggle = () => {
        setBurp(b => ({ ...b, enabled: !b.enabled }));
        if (!open) setOpen(true);
    };

    return (
        <div className={`border rounded-lg overflow-hidden transition-colors ${
            burp.enabled ? 'border-orange-500/40' : 'border-gray-800'
        }`}>
            {/* Header row */}
            <div className="flex items-center justify-between px-3 py-2.5 bg-gray-900">
                <button
                    type="button"
                    onClick={() => setOpen(s => !s)}
                    className="flex items-center gap-2 text-sm flex-1 text-left"
                >
                    <Crosshair size={13} className={burp.enabled ? 'text-orange-400' : 'text-gray-500'} />
                    <span className={`font-medium ${burp.enabled ? 'text-gray-200' : 'text-gray-400'}`}>
                        Burp Suite Integration
                    </span>
                    {burp.enabled && (
                        <span className="text-xs bg-orange-500/10 text-orange-400 border border-orange-500/20 rounded-full px-1.5 py-0.5 font-normal">
                            Active
                        </span>
                    )}
                    <ChevronDown size={13} className={`ml-auto text-gray-500 transition-transform ${open ? 'rotate-180' : ''}`} />
                </button>

                {/* Toggle switch */}
                <button
                    type="button"
                    onClick={toggle}
                    className="ml-3 flex-shrink-0 flex items-center gap-1.5 text-xs"
                    title={burp.enabled ? 'Disable Burp integration' : 'Enable Burp integration'}
                >
                    {burp.enabled
                        ? <ToggleRight size={22} className="text-orange-400" />
                        : <ToggleLeft  size={22} className="text-gray-600" />
                    }
                </button>
            </div>

            {open && (
                <div className="px-3 pb-3 pt-2 bg-gray-900/50 space-y-3">
                    {/* Info banner */}
                    <div className="flex items-start gap-2 text-xs text-gray-400 bg-gray-800/60 rounded-lg px-3 py-2 border border-gray-700/50">
                        <Radio size={12} className="text-orange-400 flex-shrink-0 mt-0.5" />
                        <span>
                            Burp Suite Pro must be running with{' '}
                            <span className="text-gray-300">User Options → Misc → REST API → Enable service</span>{' '}
                            checked. Live scanner findings and proxy traffic will be injected into skill prompts as{' '}
                            <code className="text-gray-400">{'{{burp_issues}}'}</code>,{' '}
                            <code className="text-gray-400">{'{{burp_endpoints}}'}</code>, and{' '}
                            <code className="text-gray-400">{'{{burp_summary}}'}</code>.
                        </span>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        {/* Proxy address */}
                        <div>
                            <label className="block text-xs text-gray-400 mb-1">
                                Proxy address
                                <span className="text-gray-600 ml-1">(set in browser/tool)</span>
                            </label>
                            <input
                                type="text"
                                value={burp.proxy}
                                onChange={e => update('proxy', e.target.value)}
                                placeholder="http://127.0.0.1:8080"
                                className="w-full bg-gray-800 border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-orange-500 font-mono"
                            />
                        </div>

                        {/* REST API URL */}
                        <div>
                            <label className="block text-xs text-gray-400 mb-1">REST API URL</label>
                            <input
                                type="text"
                                value={burp.api_url}
                                onChange={e => update('api_url', e.target.value)}
                                placeholder="http://127.0.0.1:1337"
                                className="w-full bg-gray-800 border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-orange-500 font-mono"
                            />
                        </div>
                    </div>

                    {/* API Key */}
                    <div>
                        <label className="block text-xs text-gray-400 mb-1">API Key</label>
                        <div className="relative">
                            <input
                                type={showKey ? 'text' : 'password'}
                                value={burp.api_key}
                                onChange={e => update('api_key', e.target.value)}
                                placeholder="Leave blank if no auth is configured"
                                autoComplete="off"
                                className="w-full bg-gray-800 border border-gray-700 rounded-lg px-2.5 pr-8 py-1.5 text-xs text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-orange-500 font-mono"
                            />
                            <button
                                type="button"
                                onClick={() => setShowKey(s => !s)}
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300"
                                tabIndex={-1}
                            >
                                {showKey ? <EyeOff size={11} /> : <Eye size={11} />}
                            </button>
                        </div>
                    </div>

                    {/* OWASP methodology note */}
                    <div className="text-xs text-gray-500 bg-gray-800/40 rounded px-2.5 py-2 border border-gray-700/40">
                        <span className="text-gray-400 font-medium">OWASP WSTG approach:</span>{' '}
                        Skill prompts will reference Burp findings to guide testing across all OWASP Top 10 categories —
                        injection, broken auth, IDOR, XXE, SSRF, misconfigurations, and more.
                    </div>
                </div>
            )}
        </div>
    );
}

// ── Preflight panel ───────────────────────────────────────────────────────────

const API_STATUS = {
    ok:          { icon: CheckCircle2,  color: 'text-green-400',  bg: 'bg-green-500/10  border-green-500/20',  label: 'API operational'          },
    no_credits:  { icon: XCircle,       color: 'text-red-400',    bg: 'bg-red-500/10    border-red-500/20',    label: 'Insufficient API credits'  },
    no_provider: { icon: AlertTriangle, color: 'text-yellow-400', bg: 'bg-yellow-500/10 border-yellow-500/20', label: 'No AI provider configured' },
    error:       { icon: AlertTriangle, color: 'text-yellow-400', bg: 'bg-yellow-500/10 border-yellow-500/20', label: 'API check failed'           },
};

function fmt(n) {
    if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`;
    if (n >= 1_000)     return `${(n / 1_000).toFixed(1)}k`;
    return String(n);
}

function PreflightPanel({ workflowId }) {
    const [state, setState] = useState('loading');
    const [data,  setData]  = useState(null);
    const [showBreakdown, setShowBreakdown] = useState(false);

    useEffect(() => {
        if (!workflowId) return;
        setState('loading');
        workflowService.preflight(workflowId)
            .then(r => { setData(r.data); setState('done'); })
            .catch(() => setState('error'));
    }, [workflowId]);

    if (state === 'loading') {
        return (
            <div className="flex items-center gap-2 text-xs text-gray-400 py-2">
                <Loader2 size={13} className="animate-spin" />
                Checking API status and estimating tokens…
            </div>
        );
    }
    if (state === 'error') {
        return <div className="text-xs text-gray-500 py-1">Preflight check unavailable.</div>;
    }

    const apiMeta    = API_STATUS[data.api?.status] ?? API_STATUS.error;
    const ApiIcon    = apiMeta.icon;
    const noCredits  = data.api?.status === 'no_credits';
    const noProvider = data.api?.status === 'no_provider';

    return (
        <div className="space-y-2">
            <div className={`flex items-center gap-2 text-xs px-3 py-2 rounded-lg border ${apiMeta.bg}`}>
                <ApiIcon size={13} className={`flex-shrink-0 ${apiMeta.color}`} />
                <span className={`font-medium ${apiMeta.color}`}>{apiMeta.label}</span>
                {data.api?.provider && (
                    <span className="text-gray-500 ml-auto flex items-center gap-1">
                        <Cpu size={11} /> {data.api.provider}
                        {data.api.model && ` · ${data.api.model}`}
                    </span>
                )}
            </div>

            <div className="flex items-center gap-3 px-3 py-2.5 bg-gray-900 border border-gray-800 rounded-lg text-xs">
                <Hash size={13} className="text-yellow-400 flex-shrink-0" />
                <div className="flex-1 grid grid-cols-3 gap-2 text-center">
                    <div>
                        <p className="text-gray-500">Input</p>
                        <p className="font-semibold text-white">{fmt(data.input_tokens)}</p>
                    </div>
                    <div>
                        <p className="text-gray-500">Output (est.)</p>
                        <p className="font-semibold text-white">{fmt(data.output_tokens)}</p>
                    </div>
                    <div>
                        <p className="text-gray-500">Total</p>
                        <p className={`font-semibold ${noCredits ? 'text-red-400' : 'text-yellow-300'}`}>
                            ~{fmt(data.total_tokens)}
                        </p>
                    </div>
                </div>
                <div className="text-gray-600 flex-shrink-0 text-right leading-tight">
                    <p>{data.skills_count} skill{data.skills_count !== 1 ? 's' : ''}</p>
                    <p>{data.steps_count} step{data.steps_count !== 1 ? 's' : ''}</p>
                </div>
            </div>

            {data.breakdown?.length > 0 && (
                <div>
                    <button
                        type="button"
                        onClick={() => setShowBreakdown(s => !s)}
                        className="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-300 transition-colors"
                    >
                        {showBreakdown ? <ChevronDown size={11} /> : <ChevronRight size={11} />}
                        Per-skill breakdown
                    </button>
                    {showBreakdown && (
                        <div className="mt-1.5 space-y-1 max-h-36 overflow-y-auto">
                            {data.breakdown.map((b, i) => (
                                <div key={i} className="flex items-center justify-between text-xs px-2 py-1 bg-gray-900 rounded border border-gray-800">
                                    <span className="text-gray-300 truncate flex-1 mr-3">{b.label}</span>
                                    <span className="text-gray-500 flex-shrink-0">
                                        {fmt(b.input)}↑ + {fmt(b.output)}↓
                                        <span className="ml-1 text-gray-600">({b.steps} step{b.steps !== 1 ? 's' : ''})</span>
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {noCredits && (
                <div className="flex items-start gap-2 text-xs text-red-300 bg-red-500/10 border border-red-500/20 rounded-lg px-3 py-2.5">
                    <XCircle size={13} className="flex-shrink-0 mt-0.5 text-red-400" />
                    <span>
                        <strong className="text-red-400">No API credits.</strong> Top up your Anthropic / OpenAI account before running — all steps will fail otherwise.
                    </span>
                </div>
            )}

            {noProvider && (
                <div className="flex items-start gap-2 text-xs text-yellow-300 bg-yellow-500/10 border border-yellow-500/20 rounded-lg px-3 py-2.5">
                    <AlertTriangle size={13} className="flex-shrink-0 mt-0.5 text-yellow-400" />
                    Configure an AI provider in Settings before running this workflow.
                </div>
            )}
        </div>
    );
}

// ── Component ─────────────────────────────────────────────────────────────────

// ── Multi-target helpers ──────────────────────────────────────────────────────

function parseTargets(raw) {
    const lines = raw.split('\n').map(l => l.trim()).filter(Boolean);
    const unique = [...new Set(lines)];
    return { lines, unique };
}

// ── Component ─────────────────────────────────────────────────────────────────

export default function RunWorkflowModal({ open, workflow, onClose, onRun, loading }) {
    const [runName,        setRunName]        = useState('');
    const [targetsRaw,    setTargetsRaw]    = useState('');
    const [multiMode,     setMultiMode]     = useState(false);
    const [context,       setContext]       = useState('');
    const [credentials,   setCredentials]   = useState([]);
    const [burp,          setBurp]          = useState({ ...BURP_DEFAULTS });
    const [repo,          setRepo]          = useState({ ...REPO_DEFAULTS });

    const wfType = detectType(workflow?.name);
    const cfg    = WORKFLOW_TYPES[wfType];
    const Icon   = cfg.icon;

    const { lines: rawLines, unique: parsedTargets } = useMemo(() => parseTargets(targetsRaw), [targetsRaw]);
    const dupCount = rawLines.length - parsedTargets.length;

    useEffect(() => {
        if (!open) return;
        setRunName('');
        setTargetsRaw('');
        setMultiMode(false);
        setContext('');
        setCredentials([]);
        setBurp({ ...BURP_DEFAULTS });
        setRepo({ ...REPO_DEFAULTS });
    }, [open]);

    const handleSubmit = () => {
        const inputData = {};

        if (context.trim()) {
            inputData.context = context.trim();
        }

        // Include non-empty credential rows
        const validCreds = credentials
            .filter(c => c.username.trim() || c.password.trim())
            .map(({ role, username, password }) => ({ role: role.trim(), username: username.trim(), password }));

        if (validCreds.length > 0) {
            inputData.credentials = validCreds;
        }

        // Include code repository config when enabled
        if (cfg.repo && repo.enabled && repo.url.trim()) {
            inputData.repo = {
                enabled:  true,
                url:      repo.url.trim(),
                branch:   repo.branch.trim() || 'main',
                token:    repo.token.trim(),
                username: repo.username.trim(),
            };
        }

        // Include Burp Suite config when enabled
        if (cfg.burp && burp.enabled) {
            inputData.burp = {
                enabled: true,
                proxy:   burp.proxy.trim()   || 'http://127.0.0.1:8080',
                api_url: burp.api_url.trim() || 'http://127.0.0.1:1337',
                api_key: burp.api_key.trim(),
            };
        }

        if (multiMode && parsedTargets.length > 1) {
            // Multi-target: send targets array at top level
            inputData.target_type = cfg.targetType;
            onRun({
                run_name:   runName.trim(),
                targets:    parsedTargets,
                input_data: Object.keys(inputData).length ? inputData : undefined,
            });
        } else {
            // Single-target mode
            const singleTarget = parsedTargets[0] ?? '';
            if (singleTarget) {
                inputData.target      = singleTarget;
                inputData.target_name = singleTarget;
                inputData.target_type = cfg.targetType;
            }
            onRun({
                run_name:   runName.trim(),
                input_data: Object.keys(inputData).length ? inputData : undefined,
            });
        }
    };

    return (
        <Modal
            open={open}
            onClose={() => !loading && onClose()}
            title={
                <span className="flex items-center gap-2">
                    Run Workflow
                    {cfg.badge && (
                        <span className={`text-xs font-normal border rounded-full px-2 py-0.5 ${cfg.badgeColor}`}>
                            {cfg.badge}
                        </span>
                    )}
                </span>
            }
        >
            <div className="space-y-4">
                <p className="text-sm text-gray-400 -mt-1">{workflow?.name}</p>

                {/* ── Preflight check ──────────────────────────────────────── */}
                {workflow?.id && <PreflightPanel workflowId={workflow.id} />}

                <hr className="border-gray-800" />

                {/* ── Run Name ──────────────────────────────────────────── */}
                <div>
                    <label className="block text-sm font-medium text-gray-300 mb-1.5">
                        Run Name
                    </label>
                    <input
                        type="text"
                        value={runName}
                        onChange={e => setRunName(e.target.value)}
                        placeholder='e.g. "Web Pentest - Client ABC - May 2026"'
                        maxLength={255}
                        required
                        className="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-violet-500"
                    />
                </div>

                {/* ── Target input (single / multi) ─────────────────────── */}
                <div>
                    <div className="flex items-center justify-between mb-1.5">
                        <label className="block text-sm font-medium text-gray-300">
                            <Icon size={13} className={`inline mr-1.5 ${cfg.iconColor}`} />
                            {multiMode ? 'Target List' : cfg.label}
                        </label>
                        <button
                            type="button"
                            onClick={() => setMultiMode(m => !m)}
                            className="flex items-center gap-1.5 text-xs text-gray-500 hover:text-violet-400 transition-colors"
                        >
                            <List size={12} />
                            {multiMode ? 'Single target' : 'Multiple targets'}
                        </button>
                    </div>

                    {multiMode ? (
                        <>
                            <textarea
                                value={targetsRaw}
                                onChange={e => setTargetsRaw(e.target.value)}
                                rows={5}
                                placeholder={`One target per line:\n${cfg.placeholder}\n${cfg.placeholder.replace('example', 'example2')}`}
                                className="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-violet-500 font-mono resize-none"
                            />
                            <div className="flex items-center gap-3 mt-1">
                                <p className="text-xs text-gray-500 flex-1">{cfg.hint} — one per line</p>
                                {parsedTargets.length > 0 && (
                                    <span className="text-xs text-violet-400 flex-shrink-0">
                                        {parsedTargets.length} target{parsedTargets.length !== 1 ? 's' : ''}
                                        {dupCount > 0 && <span className="text-gray-500 ml-1">({dupCount} dup removed)</span>}
                                    </span>
                                )}
                            </div>
                        </>
                    ) : (
                        <>
                            <input
                                type={cfg.inputType}
                                value={targetsRaw}
                                onChange={e => setTargetsRaw(e.target.value)}
                                placeholder={cfg.placeholder}
                                className="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-violet-500 font-mono"
                            />
                            <p className="text-xs text-gray-500 mt-1">{cfg.hint}</p>
                        </>
                    )}
                </div>

                {/* ── Multi-credential input (webapp only) ───────────────── */}
                {cfg.credentials && (
                    <CredentialsSection
                        credentials={credentials}
                        setCredentials={setCredentials}
                    />
                )}

                {/* ── Code repository (webapp only) ─────────────────────── */}
                {cfg.repo && (
                    <RepoSection repo={repo} setRepo={setRepo} />
                )}

                {/* ── Burp Suite integration (webapp only) ───────────────── */}
                {cfg.burp && (
                    <BurpSuiteSection burp={burp} setBurp={setBurp} />
                )}

                {/* ── Context ────────────────────────────────────────────── */}
                <div>
                    <label className="block text-sm font-medium text-gray-300 mb-1.5">
                        Additional context <span className="text-gray-500 font-normal">(optional)</span>
                    </label>
                    <textarea
                        value={context}
                        onChange={e => setContext(e.target.value)}
                        rows={3}
                        placeholder="Injected as {{context}} into every prompt — e.g. engagement notes, exclusions, special instructions…"
                        className="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-violet-500 resize-none"
                    />
                </div>

                {/* ── Active target summary ──────────────────────────────── */}
                {parsedTargets.length > 0 && (
                    multiMode && parsedTargets.length > 1 ? (
                        <div className="text-xs bg-violet-500/10 border border-violet-500/20 rounded-lg px-3 py-2 text-violet-300 space-y-1">
                            <div className="flex items-center gap-2 font-medium">
                                <List size={12} />
                                {parsedTargets.length} targets queued
                                {dupCount > 0 && <span className="text-gray-500 font-normal">({dupCount} duplicate{dupCount !== 1 ? 's' : ''} removed)</span>}
                            </div>
                            <div className="space-y-0.5 max-h-24 overflow-y-auto">
                                {parsedTargets.map((t, i) => (
                                    <p key={i} className="font-mono text-violet-400 truncate">{t}</p>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="flex items-center gap-2 text-xs bg-green-500/10 border border-green-500/20 rounded-lg px-3 py-2 text-green-300">
                            <Icon size={12} className={cfg.iconColor} />
                            <><strong>Target:</strong> {parsedTargets[0]}</>
                        </div>
                    )
                )}

                {/* ── Actions ───────────────────────────────────────────── */}
                <div className="flex justify-end gap-3 pt-1">
                    <Button type="button" variant="secondary" onClick={onClose} disabled={loading}>
                        Cancel
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        loading={loading}
                        disabled={!runName.trim() || parsedTargets.length === 0}
                    >
                        <Play size={14} />
                        {multiMode && parsedTargets.length > 1
                            ? `Run on ${parsedTargets.length} Targets`
                            : 'Run Workflow'}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
