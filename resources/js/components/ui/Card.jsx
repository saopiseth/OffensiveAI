import React from 'react';

export default function Card({ children, className = '', title, action }) {
    return (
        <div className={`bg-white border border-gray-200 rounded-xl shadow-sm ${className}`}>
            {(title || action) && (
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    {title && <h3 className="text-base font-semibold text-gray-900">{title}</h3>}
                    {action}
                </div>
            )}
            {children}
        </div>
    );
}

export function StatCard({ title, value, icon: Icon, color = 'violet', trend }) {
    const colors = {
        violet: 'bg-violet-100 text-violet-600',
        green:  'bg-green-100 text-green-600',
        blue:   'bg-blue-100 text-blue-600',
        red:    'bg-red-100 text-red-600',
        yellow: 'bg-amber-100 text-amber-600',
    };

    return (
        <div className="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-sm text-gray-500 font-medium">{title}</p>
                    <p className="text-3xl font-bold text-gray-900 mt-1">{value}</p>
                    {trend && <p className="text-xs text-gray-400 mt-1">{trend}</p>}
                </div>
                <div className={`p-3 rounded-lg ${colors[color]}`}>
                    <Icon size={20} />
                </div>
            </div>
        </div>
    );
}
