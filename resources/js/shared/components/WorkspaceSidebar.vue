<template>
    <button
        v-if="mobileOpen"
        class="workspace-sidebar-backdrop"
        type="button"
        aria-label="Close navigation"
        @click="$emit('close')"
    />

    <aside
        class="workspace-sidebar"
        :class="[`is-${variant}`, { 'is-collapsed': collapsed, 'is-mobile-open': mobileOpen }]"
        :aria-label="`${brandLabel} navigation`"
    >
        <div class="workspace-sidebar-brand">
            <RouterLink class="workspace-brand-link" :to="{ name: homeRoute }" @click="closeOnMobile">
                <img class="workspace-brand-mark" :src="'/images/branding/planora-master-512.png'" alt="Planora" width="38" height="38">
                <span v-if="!collapsed || mobileOpen" class="workspace-brand-copy">
                    <strong>Planora</strong>
                    <small>{{ brandLabel }}</small>
                </span>
            </RouterLink>

            <button
                v-if="mobileOpen"
                class="workspace-sidebar-close"
                type="button"
                aria-label="Close navigation"
                @click="$emit('close')"
            >
                <span aria-hidden="true">×</span>
            </button>
        </div>

        <p v-if="!collapsed || mobileOpen" class="workspace-sidebar-label">Workspace</p>

        <nav class="workspace-sidebar-nav">
            <component
                :is="item.disabled ? 'button' : RouterLink"
                v-for="item in links"
                :key="item.label"
                class="workspace-nav-link"
                :class="[{ 'is-disabled': item.disabled }, { 'is-active': isExplicitlyActive(item) }]"
                :to="item.disabled ? undefined : item.to"
                :disabled="item.disabled || undefined"
                :active-class="item.disabled ? undefined : 'is-active'"
                :aria-current="isExplicitlyActive(item) ? 'page' : undefined"
                :title="collapsed ? item.label : undefined"
                :aria-label="item.label"
                @click="closeOnMobile"
            >
                <span v-if="item.icon === 'shield-check'" class="workspace-nav-icon" aria-hidden="true"><svg class="workspace-nav-icon--svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 20 6v5c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6l8-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg></span>
                <span v-else-if="item.icon === 'user-settings'" class="workspace-nav-icon" aria-hidden="true"><svg class="workspace-nav-icon--svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.4-3.2 2.3-5 5.5-5 1.4 0 2.6.4 3.6 1.1"/><path d="m17.2 13.7.5 1.1 1.2.2.2 1.2-.9.8.1 1.2-1.1.5-1-.6-1 .6-1.1-.5.1-1.2-.9-.8.2-1.2 1.2-.2.5-1.1 1.2.1Z"/><path d="M16.4 17h.1"/></svg></span>
                <span v-else class="workspace-nav-icon" aria-hidden="true">{{ item.icon }}</span>
                <span v-if="!collapsed || mobileOpen" class="workspace-nav-label">{{ item.label }}</span>
                <span v-if="item.badge && (!collapsed || mobileOpen)" class="workspace-nav-badge">{{ item.badge }}</span>
            </component>
        </nav>

        <div class="workspace-sidebar-bottom">
            <div v-if="variant === 'user'" class="workspace-sidebar-account">
                <div class="workspace-sidebar-account-row">
                    <RouterLink class="workspace-account-link" :to="{ name: 'profile.show' }" @click="closeOnMobile">
                        <UserAvatar class="workspace-account-avatar" :name="accountName" :image-src="auth.user?.avatar || ''" />
                        <span v-if="!collapsed || mobileOpen" class="workspace-account-copy"><strong>{{ accountName }}</strong><small>Personal account</small></span>
                    </RouterLink>
                    <WorkspaceSidebarProfileMenu v-if="!collapsed || mobileOpen" @navigate="closeOnMobile" />
                </div>
                <button class="workspace-logout-link" type="button" aria-haspopup="dialog" :title="collapsed ? 'Sign out' : undefined" @click="requestLogout">
                    <span class="workspace-nav-icon" aria-hidden="true">↪</span>
                    <span v-if="!collapsed || mobileOpen" class="workspace-nav-label">Sign out</span>
                </button>
            </div>
            <div v-else-if="variant === 'admin'" class="workspace-sidebar-account admin-sidebar-account">
                <div class="workspace-sidebar-account-row">
                    <RouterLink class="workspace-account-link" :to="{ name: auth.hasRole('admin') ? 'admin.settings.index' : (auth.hasManagementPermission('users.view') ? 'admin.users.index' : 'admin.activity-logs.index') }" @click="closeOnMobile">
                        <UserAvatar class="workspace-account-avatar" :name="accountName" :image-src="auth.user?.avatar || ''" />
                        <span v-if="!collapsed || mobileOpen" class="workspace-account-copy"><strong>{{ accountName }}</strong><small>{{ auth.hasRole('admin') ? 'Administrator' : 'Moderator' }}</small></span>
                    </RouterLink>
                    <WorkspaceSidebarProfileMenu v-if="!collapsed || mobileOpen" @navigate="closeOnMobile" />
                </div>
                <button class="workspace-logout-link" type="button" aria-haspopup="dialog" :title="collapsed ? 'Sign out' : undefined" @click="requestLogout">
                    <span class="workspace-nav-icon" aria-hidden="true">↪</span>
                    <span v-if="!collapsed || mobileOpen" class="workspace-nav-label">Sign out</span>
                </button>
            </div>
            <button
                class="workspace-collapse-button"
                type="button"
                :aria-label="collapsed ? 'Expand navigation' : 'Collapse navigation'"
                @click="$emit('toggle-collapse')"
            >
                <span class="workspace-collapse-icon" aria-hidden="true">{{ collapsed ? '›' : '‹' }}</span>
                <span v-if="!collapsed || mobileOpen">Collapse sidebar</span>
            </button>
            <div v-if="!collapsed || mobileOpen" class="workspace-sidebar-footnote">
                <span class="workspace-status-dot" aria-hidden="true" />
                <span>{{ variant === 'admin' ? 'Admin workspace' : 'Your space, in focus' }}</span>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import { useLogoutConfirmation } from '../../features/auth/composables/useLogoutConfirmation.js';
import WorkspaceSidebarProfileMenu from './WorkspaceSidebarProfileMenu.vue';
import UserAvatar from './UserAvatar.vue';

const props = defineProps({
    variant: { type: String, required: true },
    brandLabel: { type: String, required: true },
    homeRoute: { type: String, required: true },
    links: { type: Array, required: true },
    collapsed: { type: Boolean, default: false },
    mobileOpen: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle-collapse', 'close']);
const auth = useAuthStore();
const route = useRoute();
const { requestLogout } = useLogoutConfirmation();
const accountName = computed(() => auth.user?.name || (props.variant === 'admin' ? 'Administrator' : 'Your account'));
function closeOnMobile() {
    if (props.mobileOpen) emit('close');
}

function isExplicitlyActive(item) {
    return !item.disabled && Array.isArray(item.activeOn) && item.activeOn.includes(route.name);
}

</script>

<style src="../../../css/components/WorkspaceSidebar.css"></style>
