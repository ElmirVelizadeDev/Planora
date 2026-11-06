<template>
    <WorkspaceSidebar
        variant="user"
        brand-label="Personal workspace"
        home-route="dashboard"
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
    { label: 'Dashboard', icon: '◫', to: { name: 'dashboard' } },
    { label: 'My Tasks', icon: '✓', to: { name: 'tasks.index' } },
    { label: 'Projects', icon: '▧', to: { name: 'projects.index' } },
    { label: 'Calendar', icon: '▦', to: { name: 'calendar' } },
    { label: 'Categories', icon: '◉', to: { name: 'categories.index' } },
    { label: 'Reports', icon: '▥', to: { name: 'productivity.reports' } },
    { label: 'Settings', icon: '⚙', to: { name: 'settings.index' } },
];
</script>

<style src="../../../css/components/UserSidebar.css"></style>
