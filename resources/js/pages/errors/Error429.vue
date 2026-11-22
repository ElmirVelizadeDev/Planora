<template>
    <AppErrorPage
        :code="429"
        eyebrow="Request limit reached"
        title="Give Planora a moment"
        description="Too many requests arrived in a short time. If the server supplied a retry window, we will show it here and pause this action until it ends. Otherwise, you can try again when ready."
        icon="gauge"
        tone="warning"
        primary-label="Try again"
        :primary-action="retryPage"
        secondary-label="Go to dashboard"
        :secondary-to="dashboardRouteFor(auth)"
    />
</template>

<script setup>
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import AppErrorPage from './AppErrorPage.vue';
import { dashboardRouteFor } from '../../shared/utils/errorDestinations.js';

const auth = useAuthStore();
function retryPage() {
    window.location.reload();
}
</script>
