import api from '../../../../shared/services/api.js';

export async function getActivityLogs(params = {}, config = {}) {
    return (await api.get('/admin/activity-logs', { params, ...config })).data;
}

export async function getActivityLog(id, config = {}) {
    return (await api.get(`/admin/activity-logs/${id}`, config)).data;
}

export async function getActivityLogFilters(params = {}, config = {}) {
    return (await api.get('/admin/activity-logs/filters', { params, ...config })).data;
}

export function activityLogExportUrl(params = {}) {
    const url = new URL(`${api.defaults.baseURL}/admin/activity-logs/export`, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) url.searchParams.set(key, value);
    });
    return url.toString();
}
