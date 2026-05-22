import React from 'react';

const variants = {
    default: 'bg-gray-100 text-gray-600',
    success: 'bg-green-50 text-green-700 ring-1 ring-green-200',
    warning: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
    danger:  'bg-red-50 text-red-700 ring-1 ring-red-200',
    info:    'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
    violet:  'bg-violet-50 text-violet-700 ring-1 ring-violet-200',
};

export default function Badge({ children, variant = 'default', className = '' }) {
    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${variants[variant]} ${className}`}>
            {children}
        </span>
    );
}
