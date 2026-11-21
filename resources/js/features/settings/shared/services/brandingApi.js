import api from '../../../../shared/services/api.js';

export async function getPublicBranding(config = {}) {
    return (await api.get('/settings/branding', config)).data.data;
}
