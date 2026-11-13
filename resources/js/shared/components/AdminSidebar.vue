<template>
    <WorkspaceSidebar
        variant="admin"
        :brand-label="brandLabel"
        :home-route="homeRoute"
        :links="links"
        :collapsed="collapsed"
        :mobile-open="mobileOpen"
        @toggle-collapse="$emit('toggle-collapse')"
        @close="$emit('close')"
    />
</template>

<script setup>
import WorkspaceSidebar from './WorkspaceSidebar.vue';
import { computed } from 'vue';
import { useAuthStore } from '../../features/auth/stores/authStore.js';

defineProps({
    collapsed: { type: Boolean, default: false },
    mobileOpen: { type: Boolean, default: false },
});

defineEmits(['toggle-collapse', 'close']);

const auth = useAuthStore();
const adminLinks = [
    { label: 'Dashboard', icon: '◫', to: { name: 'admin.dashboard' } },
    { label: 'Users', icon: '♙', to: { name: 'admin.users.index' } },
    { label: 'Projects', icon: '▧', to: { name: 'admin.projects.index' }, activeOn: ['admin.projects.index', 'admin.projects.show'] },
    { label: 'Tasks', icon: '✓', to: { name: 'admin.tasks.index' } },
    { label: 'Categories', icon: '◉', to: { name: 'admin.categories.index' }, activeOn: ['admin.categories.index', 'admin.categories.create', 'admin.categories.show', 'admin.categories.edit'] },
    { label: 'Reports & Analytics', icon: '▥', to: { name: 'admin.reports' } },
    { label: 'Activity Logs', icon: '◷', to: { name: 'admin.activity-logs.index' }, activeOn: ['admin.activity-logs.index', 'admin.activity-logs.show'] },
    { label: 'Settings', icon: '⚙', to: { name: 'admin.settings.index' } },
];
const isAdmin = computed(() => auth.hasRole('admin'));
const brandLabel = computed(() => isAdmin.value ? 'Administration' : 'Management');
const homeRoute = computed(() => isAdmin.value ? 'admin.dashboard' : (auth.hasManagementPermission('users.view') ? 'admin.users.index' : 'admin.activity-logs.index'));
const links = computed(() => {
    if (isAdmin.value) return adminLinks;
    return [
        ...(auth.hasManagementPermission('users.view') ? [adminLinks[1]] : []),
        ...(auth.hasManagementPermission('activity.view') ? [adminLinks[6]] : []),
    ];
});
</script>

<style src="../../../css/components/AdminSidebar.css"></style>
