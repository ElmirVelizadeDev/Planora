import api from '../../../shared/services/api.js';

export async function getAdminDashboard() {
    const response = await api.get('/admin/dashboard');
    return response.data.data;
}
