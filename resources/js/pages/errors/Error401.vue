<template>
    <AppErrorPage
        :code="401"
        eyebrow="Authentication required"
        title="Sign in to continue"
        description="Your Planora session is missing or has expired. Sign in to return to your workspace."
        icon="key"
        tone="accent"
        primary-label="Go to sign in"
        :primary-to="loginTarget"
        secondary-label="Return home"
        :secondary-to="{ name: 'home' }"
    />
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppErrorPage from './AppErrorPage.vue';
import { safeInternalRedirect } from '../../shared/utils/safeRedirect.js';

const route = useRoute();
const router = useRouter();
const loginTarget = computed(() => {
    const redirect = safeInternalRedirect(route.query.redirect, router);
    return redirect ? { name: 'login', query: { redirect } } : { name: 'login' };
});
</script>
