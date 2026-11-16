import api from '../../../shared/services/api.js';

export const getCurrentUser = () => api.get('/user');

export async function login(credentials) {
    await initializeCsrfProtection();
    return api.post('/auth/login', credentials);
}

// The server-rendered secret entry page posts back to its current URL. The path
// is therefore never included in this public JavaScript bundle.
export const loginAsAdministrator = (credentials) => api.post(
    window.location.pathname,
    credentials,
    { baseURL: '/' },
);

export async function register(attributes) {
    await initializeCsrfProtection();
    return api.post('/auth/register', attributes);
}

export async function verifyRegistration(payload) {
    await initializeCsrfProtection();
    return api.post('/auth/register/verify', payload);
}

export async function resendRegistrationCode(payload) {
    await initializeCsrfProtection();
    return api.post('/auth/register/resend', payload);
}

export async function requestPasswordRecovery(payload) {
    await initializeCsrfProtection();
    return api.post('/auth/password/forgot', payload);
}

export const resendPasswordRecovery = (payload) => api.post('/auth/password/resend', payload);
export const verifyPasswordRecovery = (payload) => api.post('/auth/password/verify', payload);
export const resetPassword = (payload) => api.post('/auth/password/reset', payload);

export const logout = () => api.post('/auth/logout');

export const initializeCsrfProtection = () => api.get(
    new URL('/sanctum/csrf-cookie', window.location.origin).toString(),
);
