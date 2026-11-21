<template>
    <div class="header-menu header-profile-menu" ref="menu" @keydown.esc="onEscape">
        <button
            ref="trigger"
            class="header-profile-trigger"
            type="button"
            :aria-expanded="open"
            aria-haspopup="menu"
            aria-label="Open profile menu"
            @click="toggle"
        >
            <UserAvatar :name="displayName" :image-src="auth.user?.avatar || ''" />
            <span class="header-profile-copy">
                <strong>{{ displayName }}</strong>
                <small>{{ roleLabel }}</small>
            </span>
            <svg class="header-profile-chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4" /></svg>
        </button>

        <Teleport to="body" :disabled="variant !== 'admin'">
        <div v-if="open" ref="popover" class="header-popover profile-popover" :class="{ 'is-admin': variant === 'admin' }" :style="popoverStyle" role="menu" @keydown.esc="onEscape">
            <div class="header-popover-heading">
                <strong>{{ displayName }}</strong>
                <span>{{ email }}</span>
                <span>{{ roleLabel }}</span>
            </div>
            <RouterLink v-if="variant !== 'limited'" class="profile-menu-item" :to="{ name: 'profile.show' }" role="menuitem" @click="close()">
                <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="6.2" r="3.1"/><path d="M3.8 17c.4-3 2.6-4.8 6.2-4.8s5.8 1.8 6.2 4.8"/></svg>
                <span>My Profile</span>
            </RouterLink>
            <RouterLink class="profile-menu-item" :to="{ name: 'account.settings' }" role="menuitem" @click="close()">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.8v1.4m0 11.6v1.4M2.8 10h1.4m11.6 0h1.4M4.9 4.9l1 1m8.2 8.2 1 1m0-9.2-1 1m-8.2 8.2-1 1"/><circle cx="10" cy="10" r="3.2"/></svg>
                <span>Account Settings</span>
            </RouterLink>
            <RouterLink v-if="variant !== 'limited'" class="profile-menu-item" :to="{ name: 'settings.appearance' }" role="menuitem" @click="close()">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.8a7.2 7.2 0 1 0 0 14.4h1.1a1.7 1.7 0 0 0 1.1-3c-.5-.4-.2-1.3.5-1.3h1.1a3.4 3.4 0 0 0 3.4-3.4c0-3.7-3.2-6.7-7.2-6.7Z"/><circle cx="6.5" cy="9" r=".8"/><circle cx="9.3" cy="6.4" r=".8"/><circle cx="13" cy="7" r=".8"/></svg>
                <span>Appearance</span>
            </RouterLink>
            <RouterLink v-if="variant === 'admin'" class="profile-menu-item" :to="{ name: 'admin.system-maintenance' }" role="menuitem" @click="close()">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M12.9 3.2a4.1 4.1 0 0 0-5.3 5.3l-4.4 4.4a1.6 1.6 0 0 0 2.3 2.3l4.4-4.4a4.1 4.1 0 0 0 5.3-5.3l-2.3 2.3-2.1-2.1 2.3-2.5Z"/><circle cx="5.1" cy="14.9" r=".5"/></svg>
                <span>System Maintenance</span>
            </RouterLink>
            <div class="header-menu-divider" role="separator" />
            <button class="header-logout-button profile-menu-item" type="button" aria-haspopup="dialog" role="menuitem" @click="logout">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M8.2 4H4.8v12h3.4M11 6.5l3.5 3.5-3.5 3.5M7 10h7.2"/></svg>
                <span>Logout</span>
            </button>
        </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import { useLogoutConfirmation } from '../../features/auth/composables/useLogoutConfirmation.js';
import UserAvatar from './UserAvatar.vue';
import { getAnchoredPopoverStyle } from '../composables/getAnchoredPopoverStyle.js';

const props = defineProps({ variant: { type: String, required: true, validator: (value) => ['user', 'admin', 'limited'].includes(value) } });
const auth = useAuthStore();
const open = ref(false);
const menu = ref(null);
const trigger = ref(null);
const popover = ref(null);
const popoverStyle = ref({});
const { isOpen: logoutDialogOpen, requestLogout } = useLogoutConfirmation();
const displayName = computed(() => auth.user?.name || 'Your account');
const email = computed(() => auth.user?.email || '');
const roleLabel = computed(() => (auth.user?.role || auth.roles?.[0] || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()));

function onOutside(event) {
    if (logoutDialogOpen.value) return;
    if (!menu.value?.contains(event.target) && !popover.value?.contains(event.target)) close();
}
function positionAdminPopover() {
    if (props.variant !== 'admin' || !open.value || !trigger.value || !popover.value) return;
    popoverStyle.value = getAnchoredPopoverStyle(trigger.value, { width: 250, maxHeight: 420, viewportAware: true });
}
async function toggle() {
    if (open.value) close();
    else {
        open.value = true;
        if (props.variant === 'admin') {
            await nextTick();
            positionAdminPopover();
        }
    }
}
function close(restoreFocus = false) {
    const focusWasInside = popover.value?.contains(document.activeElement) ?? false;
    open.value = false;
    popoverStyle.value = {};
    if (restoreFocus && focusWasInside) trigger.value?.focus({ preventScroll: true });
}
function onEscape(event) {
    if (!open.value) return;
    if (props.variant === 'admin') {
        event.preventDefault();
        event.stopPropagation();
        close(true);
    } else close();
}
function logout(event) {
    close();
    requestLogout(event);
}
onMounted(() => {
    document.addEventListener('pointerdown', onOutside);
    if (props.variant === 'admin') {
        window.addEventListener('resize', positionAdminPopover, { passive: true });
        window.addEventListener('scroll', positionAdminPopover, { passive: true, capture: true });
        window.visualViewport?.addEventListener('resize', positionAdminPopover, { passive: true });
        window.visualViewport?.addEventListener('scroll', positionAdminPopover, { passive: true });
    }
});
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onOutside);
    if (props.variant === 'admin') {
        window.removeEventListener('resize', positionAdminPopover);
        window.removeEventListener('scroll', positionAdminPopover, true);
        window.visualViewport?.removeEventListener('resize', positionAdminPopover);
        window.visualViewport?.removeEventListener('scroll', positionAdminPopover);
    }
});
</script>
