import api from '../../../../shared/services/api.js';

export const getAdminReport = (params, signal) => api.get('/admin/reports', { params, signal });
export const getAdminReportFilterOptions = (type, search, signal) => api.get('/admin/reports/filters', { params: { type, search }, signal });
export const getAdminReportTable = (table, params, signal) => api.get(`/admin/reports/tables/${table}`, { params, signal });
export const exportAdminReport = (params, signal) => api.get('/admin/reports/export', { params, signal, responseType: 'blob' });
