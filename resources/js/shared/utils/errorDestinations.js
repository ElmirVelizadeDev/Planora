import { moderatorLandingRoute } from '../../features/auth/managementAccess.js';

export function dashboardRouteFor(auth) {
    if (auth.hasRole('admin')) return { name: 'admin.dashboard' };
    if (auth.hasRole('moderator')) {
        const destination = moderatorLandingRoute(auth);
        return destination === 'error.403' ? { name: 'home' } : { name: destination };
    }
    if (auth.hasRole('user')) return { name: 'dashboard' };
    return { name: 'home' };
}

export function maintenanceRecoveryRouteFor(auth, scopes) {
    if (!scopes || typeof scopes !== 'object') return null;
    const actor = auth.user;
    const active = actor?.status === 'active';

    if (active && auth.hasRole('admin')) return { name: 'admin.dashboard' };
    if (active && auth.hasRole('user') && scopes.dashboard_workspace === false) {
        return { name: 'dashboard' };
    }
    if (active && auth.hasRole('moderator') && scopes.dashboard_workspace === false) {
        const destination = moderatorLandingRoute(auth);
        if (destination !== 'error.403') return { name: destination };
    }
    if (scopes.public_website === false) return { name: 'home' };
    return null;
}

export function blockedMaintenanceScopeFor(router, target, scopes, auth) {
    if (!scopes || typeof scopes !== 'object') return null;
    const destination = router.resolve(target);
    return (destination.meta.maintenanceScopes || []).find((scope) =>
        scopes[scope] === true && !(scope === 'dashboard_workspace' && auth.hasRole('admin')),
    ) || null;
}

export async function goBackOrDashboard(router, auth) {
    let scopes = null;
    try {
        const { getMaintenanceStatus } = await import('../../features/system-maintenance/shared/services/maintenanceStatusApi.js');
        scopes = (await getMaintenanceStatus()).scopes;
    } catch {
        // Continue with history or the ordinary role-aware fallback if status is unavailable.
    }

    const back = window.history.state?.back;
    if (back) {
        const previous = router.resolve(back);
        const isErrorRoute = previous.name === 'not-found' || String(previous.name || '').startsWith('error.');
        if (!isErrorRoute && !blockedMaintenanceScopeFor(router, back, scopes, auth)) {
            router.back();
            return;
        }
    }

    const fallback = maintenanceRecoveryRouteFor(auth, scopes);
    if (fallback) {
        await router.replace(fallback);
        return;
    }

    if (scopes) {
        const target = dashboardRouteFor(auth);
        const scope = blockedMaintenanceScopeFor(router, target, scopes, auth)
            || (scopes.public_website ? 'public_website' : 'dashboard_workspace');
        await router.replace({ name: 'error.503', query: { maintenanceScope: scope } });
        return;
    }

    await router.replace(dashboardRouteFor(auth));
}
