import api from './api.js';

export const executionService = {
    list:           (params) => api.get('/executions', { params }),
    get:            (id)     => api.get(`/executions/${id}`),
    run:            (skillId, inputData) => api.post('/executions', { skill_id: skillId, input_data: inputData }),
    delete:         (id)     => api.delete(`/executions/${id}`),
    retry:          (id)     => api.post(`/executions/${id}/retry`),
    generateReport: (id)     => api.post(`/executions/${id}/generate-report`),
    downloadReport: (id)     => api.get(`/executions/${id}/report/download`, { responseType: 'blob' }),

    targets: (execId) => api.get(`/executions/${execId}/targets`),

    findings: {
        list:       (execId)           => api.get(`/executions/${execId}/findings`),
        create:     (execId, data)     => api.post(`/executions/${execId}/findings`, data),
        update:     (execId, id, data) => api.put(`/executions/${execId}/findings/${id}`, data),
        remove:     (execId, id)       => api.delete(`/executions/${execId}/findings/${id}`),
        generateAi: (execId, data)     => api.post(`/executions/${execId}/findings/generate-ai`, data),
        chartData:  (execId)           => api.get(`/executions/${execId}/findings/chart-data`),
    },
};
