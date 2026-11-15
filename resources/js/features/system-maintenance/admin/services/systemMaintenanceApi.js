import api from '../../../../shared/services/api.js';

export async function getSystemMaintenanceStatus(config = {}) {
    return (await api.get('/admin/system-maintenance', config)).data.data;
}

export async function enableSystemMaintenance(payload, config = {}) {
    return (await api.post('/admin/system-maintenance/enable', payload, config)).data;
}

export async function disableSystemMaintenance(config = {}) {
    return (await api.post('/admin/system-maintenance/disable', {}, config)).data;
}
