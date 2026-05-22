import React from 'react';
import { NavLink } from 'react-router-dom';
import {
    LayoutDashboard, Users, ShieldCheck, Zap, GitBranch,
    PlayCircle, Bot, ChevronRight, Clock, Copy,
} from 'lucide-react';

const navItems = [
    { to: '/', icon: LayoutDashboard, label: 'Dashboard', end: true },
    { to: '/users', icon: Users, label: 'Users' },
    { to: '/roles', icon: ShieldCheck, label: 'Roles & Permissions' },
    { to: '/skills', icon: Zap, label: 'AI Skills', end: true },
    { to: '/skills/mirror', icon: Copy, label: 'Mirror Skills' },
    { to: '/workflows', icon: GitBranch, label: 'Workflows' },
    { to: '/executions', icon: PlayCircle, label: 'Executions' },
    { to: '/scheduled-workflows', icon: Clock, label: 'Scheduled Runs' },
    { to: '/settings/ai', icon: Bot, label: 'AI Settings' },
];

export default function Sidebar({ open }) {
    return (
        <aside className={`${open ? 'w-64' : 'w-16'} transition-all duration-300 bg-white border-r border-gray-200 flex flex-col`}>
            <div className="h-16 flex items-center px-4 border-b border-gray-200">
                <div className="flex items-center gap-2">
                    <div className="w-8 h-8 bg-violet-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <Bot size={16} className="text-white" />
                    </div>
                    {open && <span className="font-bold text-lg text-gray-900">RedTo</span>}
                </div>
            </div>

            <nav className="flex-1 py-4 overflow-y-auto">
                <div className="space-y-1 mb-4">
                    {navItems.map(({ to, icon: Icon, label, end }) => (
                        <NavLink
                            key={to}
                            to={to}
                            end={end}
                            className={({ isActive }) =>
                                `flex items-center gap-3 px-4 py-2.5 mx-2 rounded-lg transition-colors text-sm font-medium group
                                ${isActive
                                    ? 'bg-violet-50 text-violet-700 border border-violet-200'
                                    : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'}`
                            }
                        >
                            <Icon size={18} className="flex-shrink-0" />
                            {open && <span>{label}</span>}
                            {open && <ChevronRight size={14} className="ml-auto opacity-0 group-hover:opacity-100" />}
                        </NavLink>
                    ))}
                </div>
            </nav>

            <div className="p-4 border-t border-gray-200">
                {open && <p className="text-xs text-gray-400">v1.0.0</p>}
            </div>
        </aside>
    );
}
