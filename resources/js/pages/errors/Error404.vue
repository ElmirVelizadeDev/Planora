<template>
    <AppErrorPage
        :code="404"
        eyebrow="Page not found"
        title="This page seems to have moved"
        description="The address may be incorrect, or the page may have been moved or removed. You can return to a workspace page or go back to where you were."
        icon="search"
        tone="accent"
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
