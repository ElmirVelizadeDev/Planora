<template>
    <RouterView />
    <LogoutConfirmationDialog />
</template>

<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import LogoutConfirmationDialog from './shared/components/LogoutConfirmationDialog.vue';
import { useAuthStore } from './features/auth/stores/authStore.js';
import { moderatorCanAccessAdminRoute, moderatorLandingRoute } from './features/auth/managementAccess.js';

const router = useRouter();
const auth = useAuthStore();
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

onMounted(() => window.addEventListener('planora:api-error', refreshModeratorAccess));
onBeforeUnmount(() => window.removeEventListener('planora:api-error', refreshModeratorAccess));
</script>
