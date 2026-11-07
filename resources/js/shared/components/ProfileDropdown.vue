<template>
    <div class="header-menu header-profile-menu" ref="menu" @keydown.esc="open = false">
        <button
            class="header-profile-trigger"
            type="button"
            :aria-expanded="open"
            aria-haspopup="menu"
            aria-label="Open profile menu"
            @click="open = !open"
        >
            <UserAvatar :name="displayName" />
            <span class="header-profile-copy">
                <strong>{{ displayName }}</strong>
                <small>{{ roleLabel }}</small>
            </span>
            <svg class="header-profile-chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4" /></svg>
        </button>

        <div v-if="open" class="header-popover profile-popover" role="menu">
            <div class="header-popover-heading">
                <strong>{{ displayName }}</strong>
                <span>{{ email }}</span>
                <span>{{ roleLabel }}</span>
            </div>
            <RouterLink v-if="variant === 'user'" :to="{ name: 'profile.show' }" role="menuitem" @click="open = false">My Profile</RouterLink>
            <span v-else class="header-menu-unavailable" role="menuitem" aria-disabled="true">My Profile · unavailable</span>
            <RouterLink :to="settingsRoute" role="menuitem" @click="open = false">Account Settings</RouterLink>
            <span class="header-menu-unavailable" role="menuitem" aria-disabled="true">Appearance · not available</span>
            <div class="header-menu-divider" role="separator" />
            <button class="header-logout-button" type="button" aria-haspopup="dialog" role="menuitem" @click="requestLogout">
                Logout
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import { useLogoutConfirmation } from '../../features/auth/composables/useLogoutConfirmation.js';
import UserAvatar from './UserAvatar.vue';

const props = defineProps({ variant: { type: String, required: true } });
const auth = useAuthStore();
const open = ref(false);
const menu = ref(null);
const { isOpen: logoutDialogOpen, requestLogout } = useLogoutConfirmation();
const displayName = computed(() => auth.user?.name || 'Your account');
const email = computed(() => auth.user?.email || '');
const roleLabel = computed(() => (auth.user?.role || auth.roles?.[0] || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()));
const settingsRoute = computed(() => ({ name: props.variant === 'admin' ? 'admin.settings.index' : 'settings.account' }));

function onOutside(event) {
    if (logoutDialogOpen.value) return;
    if (!menu.value?.contains(event.target)) open.value = false;
}
onMounted(() => document.addEventListener('pointerdown', onOutside));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside));
</script>
