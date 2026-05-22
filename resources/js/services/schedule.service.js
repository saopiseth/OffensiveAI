import api from './api.js';

export const scheduleService = {
    list:   (params) => api.get('/scheduled-workflows', { params }),
    get:    (id)     => api.get(`/scheduled-workflows/${id}`),
    create: (data)   => api.post('/scheduled-workflows', data),
    update: (id, d)  => api.put(`/scheduled-workflows/${id}`, d),
    delete: (id)     => api.delete(`/scheduled-workflows/${id}`),
    run:    (id)     => api.post(`/scheduled-workflows/${id}/run`),
    toggle: (id)     => api.post(`/scheduled-workflows/${id}/toggle`),
};
