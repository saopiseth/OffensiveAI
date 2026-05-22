import api from './api.js';

export const userService = {
    list: (params) => api.get('/users', { params }),
    get: (id) => api.get(`/users/${id}`),
    create: (data) => api.post('/users', data),
    update: (id, data) => api.put(`/users/${id}`, data),
    delete: (id) => api.delete(`/users/${id}`),
    assignRoles: (id, roles) => api.post(`/users/${id}/roles`, { roles }),
    toggleStatus: (id) => api.post(`/users/${id}/toggle-status`),
};

export const roleService = {
    list: () => api.get('/roles'),
    get: (id) => api.get(`/roles/${id}`),
    create: (data) => api.post('/roles', data),
    update: (id, data) => api.put(`/roles/${id}`, data),
    delete: (id) => api.delete(`/roles/${id}`),
    permissions: () => api.get('/permissions'),
    createPermission: (name) => api.post('/permissions', { name }),
    deletePermission: (id) => api.delete(`/permissions/${id}`),
};
