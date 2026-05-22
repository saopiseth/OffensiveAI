import React, { useState } from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar.jsx';
import Header from './Header.jsx';

export default function Layout() {
    const [sidebarOpen, setSidebarOpen] = useState(true);

    return (
        <div className="flex h-screen bg-gray-50 text-gray-900 overflow-hidden">
            <Sidebar open={sidebarOpen} />
            <div className="flex flex-col flex-1 overflow-hidden">
                <Header onToggleSidebar={() => setSidebarOpen(o => !o)} />
                <main className="flex-1 overflow-auto p-6">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
