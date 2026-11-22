<template>
    <AppErrorPage
        :code="503"
        eyebrow="Service temporarily unavailable"
        title="Planora will be back soon"
        description="A workspace service is temporarily unavailable. We will check its status a few times; you can also request a check whenever you are ready."
        icon="maintenance"
        tone="accent"
        primary-label="Check availability"
        secondary-label="Go to dashboard"
    />
</template>

<script setup>
import AppErrorPage from './AppErrorPage.vue';
</script>
