import api from '../../../../shared/services/api.js';

export async function getUserTasks(params = {}) {
    return (await api.get('/tasks', { params })).data;
}

export async function getUserTask(taskId) {
    return (await api.get(`/tasks/${taskId}`)).data;
}

export async function createUserTask(payload) {
    return (await api.post('/tasks', payload)).data;
}

export async function updateUserTask(taskId, payload) {
    return (await api.patch(`/tasks/${taskId}`, payload)).data;
}

export async function updateUserTaskStatus(taskId, status) {
    return (await api.patch(`/tasks/${taskId}/status`, { status })).data;
}

export async function deleteUserTask(taskId) {
    return (await api.delete(`/tasks/${taskId}`)).data;
}
