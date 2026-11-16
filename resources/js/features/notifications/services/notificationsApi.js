import api from '../../../shared/services/api.js';

export const fetchNotifications = (params = {}) => api.get('/notifications', { params });
export const markNotificationRead = (id) => api.put(`/notifications/${encodeURIComponent(id)}/read`);
export const markAllNotificationsRead = () => api.post('/notifications/read-all');
