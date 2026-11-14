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
import { moderatorAdminLinks, moderatorLandingRoute } from '../../features/auth/managementAccess.js';

defineProps({
    collapsed: { type: Boolean, default: false },
    mobileOpen: { type: Boolean, default: false },
});

defineEmits(['toggle-collapse', 'close']);

const auth = useAuthStore();
const adminLinks = [
    { label: 'Dashboard', icon: '◫', to: { name: 'admin.dashboard' } },
    { label: 'Users', icon: '♙', to: { name: 'admin.users.index' } },
    { label: 'Categories', icon: '◉', to: { name: 'admin.categories.index' }, activeOn: ['admin.categories.index', 'admin.categories.create', 'admin.categories.show', 'admin.categories.edit'] },
    { label: 'Projects', icon: '▧', to: { name: 'admin.projects.index' }, activeOn: ['admin.projects.index', 'admin.projects.show'] },
    { label: 'Tasks', icon: '✓', to: { name: 'admin.tasks.index' } },
    { label: 'Reports & Analytics', icon: '▥', to: { name: 'admin.reports' } },
    { label: 'Activity Logs', icon: '◷', to: { name: 'admin.activity-logs.index' }, activeOn: ['admin.activity-logs.index', 'admin.activity-logs.show'] },
    { label: 'Roles & Permissions', icon: 'shield-check', to: { name: 'admin.roles-permissions.index' } },
    { label: 'Settings', icon: '⚙', to: { name: 'admin.settings.index' } },
];
const isAdmin = computed(() => auth.hasRole('admin'));
const brandLabel = computed(() => isAdmin.value ? 'Administration' : 'Management');
const homeRoute = computed(() => isAdmin.value ? 'admin.dashboard' : moderatorLandingRoute(auth));
const links = computed(() => {
    if (isAdmin.value) return adminLinks;
    return moderatorAdminLinks(auth, adminLinks);
});
</script>

<style src="../../../css/components/AdminSidebar.css"></style>
