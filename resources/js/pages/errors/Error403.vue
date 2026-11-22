<template>
    <AppErrorPage
        :code="403"
        eyebrow="Access restricted"
        title="You do not have access to this area"
        description="Your account is signed in, but this destination is not available to it. Return to a workspace page you are allowed to use."
        icon="shield"
        tone="warning"
        primary-label="Go to dashboard"
        :primary-to="dashboardRouteFor(auth)"
        secondary-label="Go back"
        :secondary-action="goBack"
    />
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import AppErrorPage from './AppErrorPage.vue';
import { dashboardRouteFor, goBackOrDashboard } from '../../shared/utils/errorDestinations.js';

const router = useRouter();
const auth = useAuthStore();
const goBack = () => goBackOrDashboard(router, auth);
</script>
