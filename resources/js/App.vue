<template>
    <Error500 v-if="hasRuntimeError" />
    <AdminLogin v-else-if="adminAuthEntry" />
    <template v-else>
        <RouterView />
        <LogoutConfirmationDialog />
        <AdminStepUpDialog />
    </template>
</template>

<script setup>
import { defineAsyncComponent, onBeforeUnmount, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import LogoutConfirmationDialog from './shared/components/LogoutConfirmationDialog.vue';
import AdminStepUpDialog from './shared/components/AdminStepUpDialog.vue';
const Error500 = defineAsyncComponent(() => import('./pages/errors/Error500.vue'));
import { hasRuntimeError } from './shared/utils/runtimeErrors.js';
const AdminLogin = defineAsyncComponent(() => import('./features/auth/admin/components/AdminLogin.vue'));
import { useAuthStore } from './features/auth/stores/authStore.js';
import { moderatorCanAccessAdminRoute, moderatorLandingRoute } from './features/auth/managementAccess.js';

const router = useRouter();
const auth = useAuthStore();
const adminAuthEntry = document.getElementById('app')?.dataset.planoraView === 'admin-auth';
let syncingAccess = false;

async function refreshModeratorAccess(event) {
    if (event.detail?.status !== 403 || syncingAccess || !auth.hasRole('moderator')) return;
    syncingAccess = true;
    try {
        await auth.fetchCurrentUser(true);
        const routeName = String(router.currentRoute.value.name || '');
        if (auth.hasRole('admin')) await router.replace({ name: 'admin.dashboard' });
        else if (auth.hasRole('user')) await router.replace({ name: 'dashboard' });
        else if (routeName.startsWith('admin.') && !moderatorCanAccessAdminRoute(auth, routeName)
            && !(routeName.startsWith('admin.activity-logs.') && moderatorCanAccessAdminRoute(auth, 'admin.activity-logs.index'))) {
            await router.replace({ name: moderatorLandingRoute(auth) });
        }
    } catch (error) {
        if (error.response?.status === 401) {
            auth.clearUser();
            await router.replace({ name: 'login' });
        }
    } finally {
        syncingAccess = false;
    }
}

async function handleMaintenanceResponse(event) {
    if (event.detail?.status !== 503) return;
    if (adminAuthEntry) return;

    const routeName = String(router.currentRoute.value.name || '');
    if (auth.hasRole('admin') && ['admin.dashboard', 'admin.system-maintenance'].includes(routeName)) return;
    if (routeName === 'error.503') return;

    const scope = event.detail.data?.scope;
    await router.replace(scope
        ? { name: 'error.503', query: { maintenanceScope: scope } }
        : { name: 'error.503' });
}

async function handleExpiredSession(event) {
    if (event.detail?.kind !== 'unauthenticated' || !auth.isAuthenticated) return;
    const routeName = String(router.currentRoute.value.name || '');
    if (routeName === 'login' || routeName.startsWith('admin.secret')) return;

    auth.clearUser();
    await router.replace({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
}

onMounted(() => {
    window.addEventListener('planora:api-error', refreshModeratorAccess);
    window.addEventListener('planora:api-error', handleMaintenanceResponse);
    window.addEventListener('planora:api-error', handleExpiredSession);
});
onBeforeUnmount(() => {
    window.removeEventListener('planora:api-error', refreshModeratorAccess);
    window.removeEventListener('planora:api-error', handleMaintenanceResponse);
    window.removeEventListener('planora:api-error', handleExpiredSession);
});
</script>
