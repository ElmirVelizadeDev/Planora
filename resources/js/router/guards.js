import { useAuthStore } from '../features/auth/stores/authStore.js';

export function installRouteGuards(router) {
    router.beforeEach(async (to) => {
        if (!to.meta.requiresAuth && !to.meta.guestOnly) return true;

        const auth = useAuthStore();
        let user = auth.user;

        if (!auth.initialized || (to.meta.requiresAuth && !user)) {
            user = await auth.fetchCurrentUser();
        }

        if (to.meta.guestOnly && user) {
            if (auth.hasRole('admin')) return { name: 'admin.dashboard' };
            if (auth.hasRole('user')) return { name: 'dashboard' };
            return { name: 'error.403' };
        }
        if (to.meta.requiresAuth && !user) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }
        if (to.meta.requiresRole && !auth.hasRole(to.meta.requiresRole)) {
            if (to.meta.requiresRole === 'admin' && auth.hasRole('user')) return { name: 'dashboard' };
            if (to.meta.requiresRole === 'user' && auth.hasRole('admin')) return { name: 'admin.dashboard' };
            return { name: 'error.403' };
        }

        return true;
    });
}
