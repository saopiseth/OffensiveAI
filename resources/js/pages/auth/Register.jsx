import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import useAuthStore from '../../store/authStore.js';
import { Bot, Loader2 } from 'lucide-react';

export default function Register() {
    const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
    const { register, loading, error } = useAuthStore();
    const navigate = useNavigate();

    const handleSubmit = async (e) => {
        e.preventDefault();
        const ok = await register(form);
        if (ok) navigate('/');
    };

    const set = (key) => (e) => setForm(f => ({ ...f, [key]: e.target.value }));

    return (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
            <div className="w-full max-w-md">
                <div className="text-center mb-8">
                    <div className="inline-flex items-center justify-center w-14 h-14 bg-violet-600 rounded-2xl mb-4 shadow-lg shadow-violet-200">
                        <Bot size={28} className="text-white" />
                    </div>
                    <h1 className="text-3xl font-bold text-gray-900">RedTo</h1>
                    <p className="text-gray-500 mt-1">Create your account</p>
                </div>

                <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
                    {error && (
                        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm">
                            {error}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-4">
                        {[
                            { key: 'name',                  label: 'Name',             type: 'text',     placeholder: 'John Doe'      },
                            { key: 'email',                 label: 'Email',            type: 'email',    placeholder: 'john@example.com' },
                            { key: 'password',              label: 'Password',         type: 'password', placeholder: '••••••••'       },
                            { key: 'password_confirmation', label: 'Confirm Password', type: 'password', placeholder: '••••••••'       },
                        ].map(({ key, label, type, placeholder }) => (
                            <div key={key}>
                                <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
                                <input
                                    type={type}
                                    value={form[key]}
                                    onChange={set(key)}
                                    placeholder={placeholder}
                                    className="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
                                    required
                                />
                            </div>
                        ))}
                        <button
                            type="submit"
                            disabled={loading}
                            className="w-full bg-violet-600 hover:bg-violet-700 disabled:opacity-50 text-white font-semibold py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2 shadow-sm"
                        >
                            {loading ? <><Loader2 size={16} className="animate-spin" /> Creating...</> : 'Create Account'}
                        </button>
                    </form>

                    <p className="text-center text-sm text-gray-500 mt-6">
                        Already have an account?{' '}
                        <Link to="/login" className="text-violet-600 hover:text-violet-700 font-medium">Sign In</Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
