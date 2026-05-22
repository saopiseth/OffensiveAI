import { create } from 'zustand';
import { authService } from '../services/auth.service.js';

const useAuthStore = create((set, get) => ({
    user: JSON.parse(localStorage.getItem('auth_user') || 'null'),
    token: localStorage.getItem('auth_token') || null,
    loading: false,
    error: null,

    isAuthenticated: () => !!get().token,

    hasRole: (role) => get().user?.roles?.some(r => r.name === role) ?? false,

    hasPermission: (permission) => get().user?.permissions?.includes(permission) ?? false,

    login: async (credentials) => {
        set({ loading: true, error: null });
        try {
            const { data } = await authService.login(credentials);
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('auth_user', JSON.stringify(data.user));
            set({ user: data.user, token: data.token, loading: false });
            return true;
        } catch (err) {
            set({ error: err.response?.data?.message || 'Login failed', loading: false });
            return false;
        }
    },

    register: async (userData) => {
        set({ loading: true, error: null });
        try {
            const { data } = await authService.register(userData);
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('auth_user', JSON.stringify(data.user));
            set({ user: data.user, token: data.token, loading: false });
            return true;
        } catch (err) {
            set({ error: err.response?.data?.message || 'Registration failed', loading: false });
            return false;
        }
    },

    logout: async () => {
        try {
            await authService.logout();
        } finally {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            set({ user: null, token: null });
        }
    },

    fetchMe: async () => {
        try {
            const { data } = await authService.me();
            localStorage.setItem('auth_user', JSON.stringify(data));
            set({ user: data });
        } catch {
            get().logout();
        }
    },
}));

export default useAuthStore;
