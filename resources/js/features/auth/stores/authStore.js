import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { getCurrentUser, login as loginRequest, logout as logoutRequest, register as registerRequest } from '../services/authApi.js';

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
        user.value = response.data.data ?? response.data;
        initialized.value = true;
        return user.value;
    }

    async function register(attributes) {
        const response = await registerRequest(attributes);
        user.value = response.data.data ?? response.data;
        initialized.value = true;
        return user.value;
    }

    async function logout() {
        try {
            await logoutRequest();
        } finally {
            clearUser();
        }
    }

    function clearUser() {
        user.value = null;
        initialized.value = true;
    }

    return { user, initialized, loading, isAuthenticated, roles, fetchCurrentUser, login, register, logout, hasRole, clearUser };
});
