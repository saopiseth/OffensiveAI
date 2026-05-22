import api from './api.js';

export const aptService = {
    presets:  ()             => api.get('/apt-simulations/presets'),
    list:     (params)       => api.get('/apt-simulations', { params }),
    generate: (scenarioType) => api.post('/apt-simulations', { scenario_type: scenarioType }),
};
