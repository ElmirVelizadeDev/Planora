import api from '../../../shared/services/api.js';

export const getCurrentUser = () => api.get('/user');

export async function login(credentials) {
    await initializeCsrfProtection();
    return api.post('/auth/login', credentials);
}

export async function register(attributes) {
    await initializeCsrfProtection();
    return api.post('/auth/register', attributes);
}

export const logout = () => api.post('/auth/logout');

export const initializeCsrfProtection = () => api.get(
    new URL('/sanctum/csrf-cookie', window.location.origin).toString(),
);
