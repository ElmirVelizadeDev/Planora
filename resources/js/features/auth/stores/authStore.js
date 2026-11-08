import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { getCurrentUser, login as loginRequest, logout as logoutRequest, register as registerRequest, resendRegistrationCode as resendRegistrationCodeRequest, verifyRegistration as verifyRegistrationRequest } from '../services/authApi.js';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const initialized = ref(false);
    const loading = ref(false);

    const isAuthenticated = computed(() => user.value !== null);
    const roles = computed(() => {
        const assigned = user.value?.roles;
        if (Array.isArray(assigned)) return assigned.map((role) => typeof role === 'string' ? role : role.name);
        return user.value?.role ? [user.value.role] : [];
    });

    async function fetchCurrentUser(force = false) {
        if (initialized.value && !force) return user.value;

        loading.value = true;
        try {
            const response = await getCurrentUser();
            user.value = response.data.data ?? response.data;
            return user.value;
        } catch (error) {
            if (error.response?.status === 401) {
                user.value = null;
                return null;
            }

            throw error;
        } finally {
            initialized.value = true;
            loading.value = false;
        }
    }

    function hasRole(role) {
        return roles.value.includes(role);
    }

    async function login(credentials) {
        const response = await loginRequest(credentials);
        const payload = response.data.data ?? response.data;
        user.value = payload.user ?? payload;
        initialized.value = true;
        return { user: user.value, redirectTo: payload.redirect_to };
    }

    async function register(attributes) {
        const response = await registerRequest(attributes);
        const payload = response.data.data ?? response.data;
        return { email: payload.email, deliveryMode: payload.delivery_mode || 'email' };
    }

    async function verifyRegistration(attributes) {
        const response = await verifyRegistrationRequest(attributes);
        const payload = response.data.data ?? response.data;
        user.value = payload.user;
        initialized.value = true;
        return { user: user.value, redirectTo: payload.redirect_to || '/dashboard' };
    }

    async function resendRegistrationCode(attributes) {
        const response = await resendRegistrationCodeRequest(attributes);
        return response.data.data ?? response.data;
    }

    async function logout() {
        await logoutRequest();
        clearUser();
    }

    function clearUser() {
        user.value = null;
        initialized.value = true;
    }

    return { user, initialized, loading, isAuthenticated, roles, fetchCurrentUser, login, register, verifyRegistration, resendRegistrationCode, logout, hasRole, clearUser };
});
