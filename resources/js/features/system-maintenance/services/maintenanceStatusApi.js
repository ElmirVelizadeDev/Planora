import api from '../../../shared/services/api.js';

export async function getMaintenanceStatus() {
    return (await api.get('/maintenance/status')).data.data;
}
