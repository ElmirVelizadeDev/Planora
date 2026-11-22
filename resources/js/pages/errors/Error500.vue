<template>
    <AppErrorPage
        :code="500"
        eyebrow="Unexpected server error"
        title="We could not complete that request"
        description="Something unexpected happened on our side. Your account data remains protected. You can safely reload this page or return to your workspace."
        icon="wrench"
        tone="danger"
        primary-label="Reload page"
        :primary-action="reloadPage"
        secondary-label="Go to dashboard"
        :secondary-to="dashboardRouteFor(auth)"
    />
</template>

<script setup>
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import AppErrorPage from './AppErrorPage.vue';
import { dashboardRouteFor } from '../../shared/utils/errorDestinations.js';

const auth = useAuthStore();
function reloadPage() {
    window.location.reload();
}
</script>
