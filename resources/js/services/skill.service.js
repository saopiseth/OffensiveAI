import api from './api.js';

export const skillService = {
    list: (params) => api.get('/skills', { params }),
    get: (id) => api.get(`/skills/${id}`),
    create: (data) => api.post('/skills', data),
    update: (id, data) => api.put(`/skills/${id}`, data),
    delete: (id) => api.delete(`/skills/${id}`),
    duplicate: (id) => api.post(`/skills/${id}/duplicate`),

    // Mirror
    mirrorList:    ()     => api.get('/skills-mirror/list'),
    mirrorPreview: (data) => api.post('/skills-mirror/preview', data),
    mirrorImport:  (data) => api.post('/skills-mirror/import', data),

    // Convert to Claude
    convertList:    ()     => api.get('/skills-convert/list'),
    convertPreview: (data) => api.post('/skills-convert/preview', data),
    convertImport:  (data) => api.post('/skills-convert/import', data),

    // Steps
    listSteps: (skillId) => api.get(`/skills/${skillId}/steps`),
    createStep: (skillId, data) => api.post(`/skills/${skillId}/steps`, data),
    updateStep: (stepId, data) => api.put(`/steps/${stepId}`, data),
    deleteStep: (stepId) => api.delete(`/steps/${stepId}`),
    reorderSteps: (skillId, steps) => api.post(`/skills/${skillId}/steps/reorder`, { steps }),
};
