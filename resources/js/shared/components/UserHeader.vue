<template>
    <WorkspaceHeader
        variant="user"
        :context-label="pageContext"
        profile-name="Alex Morgan"
        :profile-links="profileLinks"
        @toggle-sidebar="$emit('toggle-sidebar')"
    />
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import WorkspaceHeader from './WorkspaceHeader.vue';

defineEmits(['toggle-sidebar']);

const route = useRoute();
const pageContext = computed(() => {
    if (route.name === 'dashboard') return 'Dashboard';
    if (route.name?.startsWith('tasks.')) return 'My tasks';
    if (route.name?.startsWith('projects.')) return 'Projects';
    if (route.name === 'calendar') return 'Calendar';
    if (route.name?.startsWith('categories.')) return 'Categories';
    if (route.name?.startsWith('productivity')) return 'Reports';
    if (route.name?.startsWith('settings.')) return 'Settings';
    if (route.name?.startsWith('profile.')) return 'Your account';

    return 'Your workspace';
});

const profileLinks = [
    { label: 'View profile', to: { name: 'profile.show' } },
    { label: 'Account settings', to: { name: 'settings.account' } },
];
</script>

<style src="../../../css/components/UserHeader.css"></style>
