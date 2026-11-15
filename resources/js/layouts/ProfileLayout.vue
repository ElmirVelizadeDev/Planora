<template>
    <component :is="workspaceLayout" />
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../features/auth/stores/authStore.js';
import AdminLayout from './AdminLayout.vue';
import UserLayout from './UserLayout.vue';

const auth = useAuthStore();
const workspaceLayout = computed(() => auth.hasRole('user') ? UserLayout : AdminLayout);
</script>
