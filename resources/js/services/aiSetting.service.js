import api from './api.js';

export const aiSettingService = {
    list: () => api.get('/ai-settings'),
    get: (id) => api.get(`/ai-settings/${id}`),
    create: (data) => api.post('/ai-settings', data),
    update: (id, data) => api.put(`/ai-settings/${id}`, data),
    delete: (id) => api.delete(`/ai-settings/${id}`),
    test: (id) => api.post(`/ai-settings/${id}/test`),
    activate: (id) => api.post(`/ai-settings/${id}/activate`),
};
