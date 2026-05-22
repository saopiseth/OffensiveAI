import React from 'react';

export default function Input({ label, error, className = '', ...props }) {
    return (
        <div className="space-y-1">
            {label && <label className="block text-sm font-medium text-gray-700">{label}</label>}
            <input
                className={`w-full bg-white border ${error ? 'border-red-400' : 'border-gray-300'}
                    rounded-lg px-3 py-2 text-sm text-gray-900 placeholder-gray-400
                    focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500
                    transition-colors ${className}`}
                {...props}
            />
            {error && <p className="text-xs text-red-500">{error}</p>}
        </div>
    );
}

export function Textarea({ label, error, className = '', ...props }) {
    return (
        <div className="space-y-1">
            {label && <label className="block text-sm font-medium text-gray-700">{label}</label>}
            <textarea
                className={`w-full bg-white border ${error ? 'border-red-400' : 'border-gray-300'}
                    rounded-lg px-3 py-2 text-sm text-gray-900 placeholder-gray-400
                    focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500
                    transition-colors resize-none ${className}`}
                {...props}
            />
            {error && <p className="text-xs text-red-500">{error}</p>}
        </div>
    );
}

export function Select({ label, error, children, className = '', ...props }) {
    return (
        <div className="space-y-1">
            {label && <label className="block text-sm font-medium text-gray-700">{label}</label>}
            <select
                className={`w-full bg-white border ${error ? 'border-red-400' : 'border-gray-300'}
                    rounded-lg px-3 py-2 text-sm text-gray-900
                    focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500
                    transition-colors ${className}`}
                {...props}
            >
                {children}
            </select>
            {error && <p className="text-xs text-red-500">{error}</p>}
        </div>
    );
}
