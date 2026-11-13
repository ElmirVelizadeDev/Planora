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
                <span class="workspace-nav-icon" aria-hidden="true">{{ item.icon }}</span>
                <span v-if="!collapsed || mobileOpen" class="workspace-nav-label">{{ item.label }}</span>
                <span v-if="item.badge && (!collapsed || mobileOpen)" class="workspace-nav-badge">{{ item.badge }}</span>
            </component>
        </nav>

        <div class="workspace-sidebar-bottom">
            <div v-if="variant === 'user'" class="workspace-sidebar-account">
                <RouterLink class="workspace-account-link" :to="{ name: 'profile.show' }" @click="closeOnMobile">
                    <span class="workspace-account-avatar" aria-hidden="true">{{ accountInitials }}</span>
                    <span v-if="!collapsed || mobileOpen" class="workspace-account-copy"><strong>{{ accountName }}</strong><small>Personal account</small></span>
                    <span v-if="!collapsed || mobileOpen" class="workspace-account-chevron" aria-hidden="true">···</span>
                </RouterLink>
                <button class="workspace-logout-link" type="button" aria-haspopup="dialog" :title="collapsed ? 'Sign out' : undefined" @click="requestLogout">
                    <span class="workspace-nav-icon" aria-hidden="true">↪</span>
                    <span v-if="!collapsed || mobileOpen" class="workspace-nav-label">Sign out</span>
                </button>
            </div>
            <div v-else-if="variant === 'admin'" class="workspace-sidebar-account admin-sidebar-account">
                <RouterLink class="workspace-account-link" :to="{ name: auth.hasRole('admin') ? 'admin.settings.index' : (auth.hasManagementPermission('users.view') ? 'admin.users.index' : 'admin.activity-logs.index') }" @click="closeOnMobile">
                    <span class="workspace-account-avatar" aria-hidden="true">{{ accountInitials }}</span>
                    <span v-if="!collapsed || mobileOpen" class="workspace-account-copy"><strong>{{ accountName }}</strong><small>{{ auth.hasRole('admin') ? 'Administrator' : 'Moderator' }}</small></span>
                    <span v-if="!collapsed || mobileOpen" class="workspace-account-chevron" aria-hidden="true">···</span>
                </RouterLink>
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
const accountInitials = computed(() => accountName.value.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase());

function closeOnMobile() {
    if (props.mobileOpen) emit('close');
}

function isExplicitlyActive(item) {
    return !item.disabled && Array.isArray(item.activeOn) && item.activeOn.includes(route.name);
}

</script>

<style src="../../../css/components/WorkspaceSidebar.css"></style>
