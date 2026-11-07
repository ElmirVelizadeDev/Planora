<template>
    <RouterView />
    <LogoutConfirmationDialog />
</template>

<script setup>
import LogoutConfirmationDialog from './shared/components/LogoutConfirmationDialog.vue';
</script>
