<template>
    <header class="admin-header" @keydown.esc="closeMenus">
        <div class="admin-header-start">
            <button
                class="admin-header-menu-button"
                type="button"
                aria-label="Open administration navigation"
                @click="$emit('toggle-sidebar')"
            >
                <span></span><span></span><span></span>
            </button>

            <div class="admin-header-page">
                <div class="admin-header-brandline">
                    <img :src="'/images/branding/planora-master-512.png'" alt="Planora" width="16" height="16">
                    <span>Planora Admin</span>
                </div>
                <h1>{{ pageContext }}</h1>
            </div>
        </div>

        <div class="admin-header-search">
            <SearchBar />
        </div>

        <div class="admin-header-actions">
            <div ref="notificationMenu" class="admin-header-menu admin-notification-menu">
                <button
                    class="admin-header-icon-button"
                    type="button"
                    :aria-expanded="notificationsOpen"
                    aria-haspopup="dialog"
                    aria-label="Open notifications"
                    @click="toggleNotifications"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
                    </svg>
                </button>
                <section v-if="notificationsOpen" class="admin-header-popover admin-notification-popover" role="dialog" aria-label="Admin notifications">
                    <div class="admin-header-popover-heading">
                        <strong>Notifications</strong>
                        <span>Platform updates</span>
                    </div>
                    <div class="admin-notification-empty">
                        <span aria-hidden="true">✦</span>
                        <strong>You’re all caught up</strong>
                        <small>New updates will appear here.</small>
                    </div>
                </section>
            </div>

            <div ref="profileMenu" class="admin-header-menu admin-profile-menu">
                <button
                    class="admin-profile-trigger"
                    type="button"
                    :aria-expanded="profileOpen"
                    aria-haspopup="menu"
                    aria-label="Open admin profile menu"
                    @click="toggleProfile"
                >
                    <UserAvatar :name="adminName" />
                    <span class="admin-profile-copy">
                        <strong>{{ adminName }}</strong>
                        <small v-if="adminRole">{{ adminRole }}</small>
                    </span>
                    <svg class="admin-profile-chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4" /></svg>
                </button>

                <div v-if="profileOpen" class="admin-header-popover admin-profile-popover" role="menu">
                    <div class="admin-header-popover-heading">
                        <strong>{{ adminName }}</strong>
                        <span v-if="adminEmail">{{ adminEmail }}</span>
                        <span v-else-if="adminRole">{{ adminRole }}</span>
                    </div>
                    <RouterLink :to="{ name: 'admin.settings.index' }" role="menuitem" @click="closeMenus">Admin settings</RouterLink>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import SearchBar from './SearchBar.vue';
import UserAvatar from './UserAvatar.vue';

defineEmits(['toggle-sidebar']);

const route = useRoute();
const auth = useAuthStore();
const notificationMenu = ref(null);
const profileMenu = ref(null);
const notificationsOpen = ref(false);
const profileOpen = ref(false);

const pageContext = computed(() => {
    if (route.name === 'admin.dashboard') return 'Dashboard';
    if (route.name?.startsWith('admin.users.')) return 'Users';
    if (route.name?.startsWith('admin.tasks.')) return 'Tasks';
    if (route.name?.startsWith('admin.projects.')) return 'Projects';
    if (route.name?.startsWith('admin.categories.')) return 'Categories';
    if (route.name === 'admin.reports') return 'Reports & Analytics';
    if (route.name === 'admin.analytics') return 'Analytics';
    if (route.name?.startsWith('admin.activity-logs.')) return 'Activity Logs';
    if (route.name?.startsWith('admin.settings.')) return 'Settings';
    if (route.name?.startsWith('admin.roles.') || route.name === 'admin.permissions.index') return 'Access management';
    if (route.name?.startsWith('admin.notifications.')) return 'Notifications';
    if (route.name?.startsWith('admin.tags.')) return 'Tags';

    return 'Administration';
});

const adminName = computed(() => auth.user?.name || 'Admin');
const adminEmail = computed(() => auth.user?.email || '');
const adminRole = computed(() => {
    const role = auth.roles?.[0];
    if (!role) return '';

    return role.split(/[_\s-]+/).map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
});

function toggleNotifications() {
    notificationsOpen.value = !notificationsOpen.value;
    profileOpen.value = false;
}

function toggleProfile() {
    profileOpen.value = !profileOpen.value;
    notificationsOpen.value = false;
}

function closeMenus() {
    notificationsOpen.value = false;
    profileOpen.value = false;
}

function closeOnOutsidePointer(event) {
    if (!notificationMenu.value?.contains(event.target)) notificationsOpen.value = false;
    if (!profileMenu.value?.contains(event.target)) profileOpen.value = false;
}

onMounted(() => document.addEventListener('pointerdown', closeOnOutsidePointer));
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeOnOutsidePointer));
watch(() => route.fullPath, closeMenus);
</script>

<style src="../../../css/components/AdminHeader.css"></style>
