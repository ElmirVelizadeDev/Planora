import api from '../../../../shared/services/api.js';
export async function getAdminProjectTasks(projectId, params = {}) { return (await api.get(`/admin/projects/${projectId}/tasks`, { params })).data; }
export async function createAdminProjectTask(projectId, payload) { return (await api.post(`/admin/projects/${projectId}/tasks`, payload)).data; }

export async function getAdminProjects(params = {}) {
    return (await api.get('/admin/projects', { params })).data;
}

export async function getAdminProjectCategories() {
    return (await api.get('/admin/categories', { params: { status: 'active', per_page: 50, sort: 'name', direction: 'asc' } })).data;
}

export async function getAdminProject(projectId) {
    return (await api.get(`/admin/projects/${projectId}`)).data;
}

export async function getAdminProjectOwners() {
    return (await api.get('/admin/projects/owners')).data;
}

export async function createAdminProject(payload) {
    return (await api.post('/admin/projects', payload)).data;
}

export async function updateAdminProject(projectId, payload) {
    return (await api.patch(`/admin/projects/${projectId}`, payload)).data;
}

export async function deleteAdminProject(projectId) {
    return (await api.delete(`/admin/projects/${projectId}`)).data;
}
