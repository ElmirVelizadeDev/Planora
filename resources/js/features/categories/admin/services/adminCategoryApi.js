import api from '../../../../shared/services/api.js';

export async function getAdminCategories(params = {}) {
    return (await api.get('/admin/categories', { params })).data;
}

export async function getAdminCategory(categoryId) {
    return (await api.get(`/admin/categories/${categoryId}`)).data;
}

export async function createAdminCategory(payload) {
    return (await api.post('/admin/categories', payload)).data;
}

export async function updateAdminCategory(categoryId, payload) {
    return (await api.patch(`/admin/categories/${categoryId}`, payload)).data;
}

export async function archiveAdminCategory(categoryId) {
    return (await api.delete(`/admin/categories/${categoryId}`)).data;
}

export async function restoreAdminCategory(categoryId) {
    return (await api.post(`/admin/categories/${categoryId}/restore`)).data;
}
