import React from 'react';
import { Menu, Bell, LogOut, User } from 'lucide-react';
import useAuthStore from '../../store/authStore.js';
import { useNavigate } from 'react-router-dom';

export default function Header({ onToggleSidebar }) {
    const { user, logout } = useAuthStore();
    const navigate = useNavigate();

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    return (
        <header className="h-16 bg-white border-b border-gray-200 flex items-center px-6 gap-4">
            <button
                onClick={onToggleSidebar}
                className="text-gray-400 hover:text-gray-700 transition-colors"
            >
                <Menu size={20} />
            </button>

            <div className="flex-1" />

            <button className="text-gray-400 hover:text-gray-700 transition-colors relative">
                <Bell size={20} />
            </button>

            <div className="flex items-center gap-3 pl-4 border-l border-gray-200">
                <div className="w-8 h-8 rounded-full bg-violet-600 flex items-center justify-center">
                    <User size={14} className="text-white" />
                </div>
                <div className="hidden md:block">
                    <p className="text-sm font-medium text-gray-900">{user?.name}</p>
                    <p className="text-xs text-gray-500">{user?.roles?.[0]?.name}</p>
                </div>
                <button
                    onClick={handleLogout}
                    className="text-gray-400 hover:text-red-500 transition-colors ml-2"
                    title="Logout"
                >
                    <LogOut size={18} />
                </button>
            </div>
        </header>
    );
}
