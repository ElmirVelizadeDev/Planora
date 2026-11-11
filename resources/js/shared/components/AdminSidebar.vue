<template>
    <WorkspaceSidebar
        variant="admin"
        brand-label="Administration"
        home-route="admin.dashboard"
        :links="links"
        :collapsed="collapsed"
        :mobile-open="mobileOpen"
        @toggle-collapse="$emit('toggle-collapse')"
        @close="$emit('close')"
    />
</template>

<script setup>
import WorkspaceSidebar from './WorkspaceSidebar.vue';

defineProps({
    collapsed: { type: Boolean, default: false },
    mobileOpen: { type: Boolean, default: false },
});

defineEmits(['toggle-collapse', 'close']);

const links = [
    { label: 'Dashboard', icon: '◫', to: { name: 'admin.dashboard' } },
    { label: 'Users', icon: '♙', to: { name: 'admin.users.index' } },
    { label: 'Projects', icon: '▧', to: { name: 'admin.projects.index' }, activeOn: ['admin.projects.index', 'admin.projects.show'] },
    { label: 'Tasks', icon: '✓', to: { name: 'admin.tasks.index' } },
    { label: 'Categories', icon: '◉', to: { name: 'admin.categories.index' } },
    { label: 'Reports & Analytics', icon: '▥', to: { name: 'admin.reports' } },
    { label: 'Activity Logs', icon: '◷', to: { name: 'admin.activity-logs.index' } },
    { label: 'Settings', icon: '⚙', to: { name: 'admin.settings.index' } },
];
</script>

<style src="../../../css/components/AdminSidebar.css"></style>
