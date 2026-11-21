import api from '../../../../shared/services/api.js';

export const fetchBackups = (params = {}) => api.get('/admin/backups', { params });
export const createBackup = (type) => api.post('/admin/backups', { type });
export const fetchBackup = (id) => api.get(`/admin/backups/${encodeURIComponent(id)}`);
export const downloadBackup = (id) => api.get(`/admin/backups/${encodeURIComponent(id)}/download`, { responseType: 'blob' });
export const deleteBackup = (id) => api.delete(`/admin/backups/${encodeURIComponent(id)}`, { data: { confirmation: `DELETE ${id}` } });
export const restoreBackup = (id) => api.post(`/admin/backups/${encodeURIComponent(id)}/restore`, { confirmation: `RESTORE ${id}` });
