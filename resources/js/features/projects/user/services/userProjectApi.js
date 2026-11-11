import api from '../../../../shared/services/api.js';

export async function getUserProjects(params = {}) { return (await api.get('/projects', { params })).data; }
export async function getUserProject(projectId) { return (await api.get(`/projects/${projectId}`)).data; }
export async function getUserProjectTasks(projectId, params = {}) { return (await api.get(`/projects/${projectId}/tasks`, { params })).data; }
