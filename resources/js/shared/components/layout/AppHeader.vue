<template>
    <header class="workspace-header" :class="[`is-${variant}`, { 'search-overlay-open': searchOverlayOpen }]" @keydown.esc="searchRef?.close?.()">
        <div class="workspace-header-start">
            <button
                class="workspace-menu-button"
                type="button"
                aria-label="Toggle sidebar"
                @click="$emit('toggle-sidebar')"
            >
                <span></span><span></span><span></span>
            </button>
            <div class="workspace-header-context">
                <span class="workspace-header-eyebrow">
                    <img :src="branding.application_logo_url || '/images/branding/planora-master-512.png'" alt="" width="16" height="16">
                    {{ variant === 'admin' ? `${branding.application_name || 'Planora'} Admin` : (branding.workspace_name || 'Planora') }}
                </span>
                <span class="workspace-header-label">{{ pageContext }}</span>
            </div>
        </div>

        <SearchBar ref="searchRef" class="workspace-header-search" @overlay-change="searchOverlayOpen = $event" />

        <div class="workspace-header-actions">
            <HeaderQuickActions v-if="variant === 'user'" />
            <NotificationDropdown :variant="variant" />
            <HeaderHelpCenter />
            <ProfileDropdown :variant="variant" />
        </div>
    </header>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import HeaderHelpCenter from './HeaderHelpCenter.vue';
import HeaderQuickActions from './HeaderQuickActions.vue';
import NotificationDropdown from '../NotificationDropdown.vue';
import ProfileDropdown from '../ProfileDropdown.vue';
import SearchBar from '../SearchBar.vue';
import { getPublicBranding } from '../../../features/settings/services/settingsApi.js';

const props = defineProps({
    variant: { type: String, required: true, validator: (value) => ['user', 'admin'].includes(value) },
});

defineEmits(['toggle-sidebar']);

const route = useRoute();
const searchRef = ref(null);
const searchOverlayOpen = ref(false);
const branding = ref({ application_name: 'Planora', workspace_name: 'Planora' });
function updateBranding(event) {
    branding.value = { ...branding.value, ...(event.detail || {}) };
}
onMounted(async () => {
    window.addEventListener('planora:settings-updated', updateBranding);
    try { branding.value = await getPublicBranding(); } catch { /* Keep the built-in Planora identity available if branding is unavailable. */ }
});
onBeforeUnmount(() => window.removeEventListener('planora:settings-updated', updateBranding));
const pageContext = computed(() => {
    const name = String(route.name || '');

    if (props.variant === 'admin') {
        if (name === 'admin.dashboard') return 'Dashboard';
        if (name.startsWith('admin.users.')) return 'Users';
        if (name.startsWith('admin.tasks.')) return 'Tasks';
        if (name.startsWith('admin.projects.')) return 'Projects';
        if (name.startsWith('admin.categories.')) return 'Categories';
        if (name === 'admin.reports') return 'Reports & Analytics';
        if (name === 'admin.analytics') return 'Analytics';
        if (name.startsWith('admin.activity-logs.')) return 'Activity Logs';
        if (name.startsWith('admin.settings.')) return 'Settings';
        if (name.startsWith('admin.roles.') || name === 'admin.permissions.index') return 'Access management';
        if (name.startsWith('admin.notifications.')) return 'Notifications';
        if (name.startsWith('admin.tags.')) return 'Tags';

        return 'Administration';
    }

    if (name === 'dashboard') return 'Dashboard';
    if (name.startsWith('tasks.')) return 'My tasks';
    if (name.startsWith('projects.')) return 'Projects';
    if (name === 'calendar') return 'Calendar';
    if (name.startsWith('categories.')) return 'Categories';
    if (name.startsWith('productivity')) return 'Reports';
    if (name.startsWith('settings.')) return 'Settings';
    if (name.startsWith('profile.')) return 'Your account';

    return 'Your workspace';
});
</script>

<style src="../../../../css/components/WorkspaceHeader.css"></style>
