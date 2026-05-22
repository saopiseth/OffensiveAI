import React from 'react';
import { Loader2 } from 'lucide-react';

export default function Table({ columns, data, loading, emptyMessage = 'No data found', onRowClick }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-gray-200 bg-gray-50">
                        {columns.map((col) => (
                            <th key={col.key} className="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                {col.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {loading ? (
                        <tr>
                            <td colSpan={columns.length} className="py-12 text-center">
                                <Loader2 size={24} className="animate-spin text-violet-500 mx-auto" />
                            </td>
                        </tr>
                    ) : data.length === 0 ? (
                        <tr>
                            <td colSpan={columns.length} className="py-12 text-center text-gray-400">
                                {emptyMessage}
                            </td>
                        </tr>
                    ) : (
                        data.map((row, idx) => (
                            <tr
                                key={row.id || idx}
                                onClick={onRowClick ? () => onRowClick(row) : undefined}
                                className={`border-b border-gray-100 hover:bg-gray-50 transition-colors ${onRowClick ? 'cursor-pointer' : ''}`}
                            >
                                {columns.map((col) => (
                                    <td key={col.key} className="py-3 px-4 text-gray-700">
                                        {col.render ? col.render(row) : row[col.key]}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}

export function Pagination({ meta, onPageChange }) {
    if (!meta || meta.last_page <= 1) return null;

    return (
        <div className="flex items-center justify-between px-4 py-3 border-t border-gray-100">
            <p className="text-sm text-gray-500">
                Showing {meta.from}–{meta.to} of {meta.total}
            </p>
            <div className="flex gap-1">
                {Array.from({ length: Math.min(meta.last_page, 7) }, (_, i) => i + 1).map(page => (
                    <button
                        key={page}
                        onClick={() => onPageChange(page)}
                        className={`w-8 h-8 rounded text-xs font-medium transition-colors
                            ${page === meta.current_page
                                ? 'bg-violet-600 text-white'
                                : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100'}`}
                    >
                        {page}
                    </button>
                ))}
            </div>
        </div>
    );
}
