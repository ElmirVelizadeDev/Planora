import { useAuthStore } from '../features/auth/stores/authStore.js';

export function installRouteGuards(router) {
    router.beforeEach(async (to) => {
        if (import.meta.env.DEV && ['dashboard', 'admin.dashboard'].includes(to.name)) {
            return true;
        }

        if (!to.meta.requiresAuth && !to.meta.guestOnly) return true;

        const auth = useAuthStore();
        let user = auth.user;

        if (!auth.initialized || (to.meta.requiresAuth && !user)) {
            user = await auth.fetchCurrentUser();
        }

        if (to.meta.guestOnly && user) return { name: 'dashboard' };
        if (to.meta.requiresAuth && !user) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }
        if (to.meta.requiresRole && !auth.hasRole(to.meta.requiresRole)) {
            return { name: 'error.403' };
        }

        return true;
    });
}
