import api from './api.js';

export const workflowService = {
    list: (params) => api.get('/workflows', { params }),
    get: (id) => api.get(`/workflows/${id}`),
    create: (data) => api.post('/workflows', data),
    update: (id, data) => api.put(`/workflows/${id}`, data),
    delete: (id) => api.delete(`/workflows/${id}`),
    preflight: (id) => api.get(`/workflows/${id}/preflight`),
    execute: (id, payload) => api.post(`/workflows/${id}/execute`, payload),
};
