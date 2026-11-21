import api from '../../../../shared/services/api.js';

export const fetchAdminMfaStatus = () => api.get('/admin/mfa');
export const startAdminMfaEnrollment = (password) => api.post('/admin/mfa/enrollment', { password });
export const verifyAdminMfaEnrollment = (code) => api.post('/admin/mfa/enrollment/verify', { code });
export const cancelAdminMfaEnrollment = () => api.delete('/admin/mfa/enrollment');
export const disableAdminMfa = () => api.post('/admin/mfa/disable');
export const regenerateAdminMfaRecoveryCodes = () => api.post('/admin/mfa/recovery-codes');
