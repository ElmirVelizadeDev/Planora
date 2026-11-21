import { useAuthStore } from '../features/auth/stores/authStore.js';
import { moderatorCanAccessAdminRoute, moderatorLandingRoute } from '../features/auth/managementAccess.js';
import { initializeAppearancePreferences, resetAppearanceSession } from '../features/appearance/composables/appearancePreferences.js';
import { getMaintenanceStatus } from '../features/system-maintenance/shared/services/maintenanceStatusApi.js';

export function installRouteGuards(router) {
    router.beforeEach(async (to) => {
        if (!to.meta.requiresAuth && !to.meta.guestOnly && !to.meta.initializeAuth
            && !Array.isArray(to.meta.maintenanceScopes)) return true;

        const auth = useAuthStore();
        let user = auth.user;

        if (!auth.initialized || (to.meta.requiresAuth && !user)) {
            try {
                user = await auth.fetchCurrentUser();
            } catch (error) {
                if (error.response?.status === 503) {
                    const scope = error.response.data?.scope;
                    return scope
                        ? { name: 'error.503', query: { maintenanceScope: scope } }
                        : { name: 'error.503' };
                }
                throw error;
            }
        }

        if (user) initializeAppearancePreferences(user.id);
        else if (auth.initialized) resetAppearanceSession();

        if (to.meta.guestOnly && user) {
            if (auth.hasRole('admin')) return { name: 'admin.dashboard' };
            if (auth.hasRole('user')) return { name: 'dashboard' };
            if (auth.hasRole('moderator')) return { name: moderatorLandingRoute(auth) };
            return { name: 'error.403' };
        }
        if (to.meta.requiresAuth && !user) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }
        if (typeof to.name === 'string' && to.name.startsWith('admin.') && !auth.hasRole('admin')) {
            const accessRoute = to.name.startsWith('admin.activity-logs.') ? 'admin.activity-logs.index' : to.name;
            const moderatorCanAccess = moderatorCanAccessAdminRoute(auth, accessRoute);
            if (moderatorCanAccess) return true;
            if (auth.hasRole('moderator')) {
                return { name: moderatorLandingRoute(auth) };
            }
            if (auth.hasRole('user')) return { name: 'dashboard' };
        }

        if (to.meta.requiresRole && !auth.hasRole(to.meta.requiresRole)) {
            if (to.meta.requiresRole === 'user' && auth.hasRole('admin')) return { name: 'admin.dashboard' };
            return { name: 'error.403' };
        }

        if (Array.isArray(to.meta.maintenanceScopes) && to.meta.maintenanceScopes.length > 0) {
            try {
                const maintenance = await getMaintenanceStatus();
                const blockedScope = to.meta.maintenanceScopes.find((scope) =>
                    maintenance.scopes?.[scope]
                    && !(scope === 'dashboard_workspace' && auth.hasRole('admin')),
                );
                if (blockedScope) return { name: 'error.503', query: { maintenanceScope: blockedScope } };
            } catch (error) {
                if (error.response?.status === 503) {
                    const scope = error.response.data?.scope;
                    return scope
                        ? { name: 'error.503', query: { maintenanceScope: scope } }
                        : { name: 'error.503' };
                }
            }
        }

        return true;
    });
}
