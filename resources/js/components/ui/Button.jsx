import React from 'react';
import { Loader2 } from 'lucide-react';

const variants = {
    primary:  'bg-violet-600 hover:bg-violet-700 text-white shadow-sm',
    secondary:'bg-gray-100 hover:bg-gray-200 text-gray-700',
    danger:   'bg-red-600 hover:bg-red-700 text-white shadow-sm',
    ghost:    'bg-transparent hover:bg-gray-100 text-gray-600',
    outline:  'border border-gray-300 hover:border-gray-400 bg-white text-gray-700 hover:text-gray-900',
};

const sizes = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
    lg: 'px-6 py-3 text-base',
};

export default function Button({
    children, variant = 'primary', size = 'md',
    loading = false, disabled = false, className = '', ...props
}) {
    return (
        <button
            disabled={disabled || loading}
            className={`inline-flex items-center gap-2 rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2 focus:ring-offset-white disabled:opacity-50 disabled:cursor-not-allowed
                ${variants[variant]} ${sizes[size]} ${className}`}
            {...props}
        >
            {loading && <Loader2 size={14} className="animate-spin" />}
            {children}
        </button>
    );
}
