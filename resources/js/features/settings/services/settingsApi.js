import api from '../../../shared/services/api.js';

export async function getSettingsOverview(config = {}) {
    return (await api.get('/admin/settings', config)).data.data;
}

export async function getSettingsGroup(group, config = {}) {
    return (await api.get(`/admin/settings/${group}`, config)).data.data;
}

export async function updateSettingsGroup(group, values, config = {}) {
    return (await api.put(`/admin/settings/${group}`, values, config)).data;
}

export async function resetSettingsGroup(group, config = {}) {
    return (await api.post(`/admin/settings/${group}/reset`, {}, config)).data;
}

export async function uploadSettingsLogo(file, config = {}) {
    const body = new FormData();
    body.append('logo', file);
    return (await api.post('/admin/settings/general/logo', body, config)).data;
}

export async function removeSettingsLogo(config = {}) {
    return (await api.delete('/admin/settings/general/logo', config)).data;
}

export async function sendSettingsTestEmail(recipient, config = {}) {
    return (await api.post('/admin/settings/email/test', { recipient }, config)).data;
}

export async function getPublicBranding(config = {}) {
    return (await api.get('/settings/branding', config)).data.data;
}
