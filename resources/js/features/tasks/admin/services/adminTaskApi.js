import api from '../../../../shared/services/api.js';

export async function getAdminTasks(params = {}) {
    return (await api.get('/admin/tasks', { params })).data;
}

export async function getAdminTask(taskId) {
    return (await api.get(`/admin/tasks/${taskId}`)).data;
}

export async function getTaskAssignees() {
    return (await api.get('/admin/tasks/assignees')).data;
}

export async function createAdminTask(payload) {
    return (await api.post('/admin/tasks', payload)).data;
}

export async function updateAdminTask(taskId, payload) {
    return (await api.patch(`/admin/tasks/${taskId}`, payload)).data;
}

export async function deleteAdminTask(taskId) {
    return (await api.delete(`/admin/tasks/${taskId}`)).data;
}
