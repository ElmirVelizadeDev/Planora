<template>
    <div ref="triggerWrap" class="workspace-sidebar-menu-trigger-wrap">
        <button
            ref="trigger"
            class="workspace-sidebar-menu-trigger"
            type="button"
            aria-label="Open account menu"
            aria-haspopup="menu"
            :aria-expanded="open"
            :aria-controls="menuId"
            @click="toggle"
            @keydown.down.prevent="openMenu"
        >
            <span aria-hidden="true">⋯</span>
        </button>
    </div>

    <Teleport to="body">
        <Transition name="workspace-sidebar-profile-menu">
            <div
                v-if="open"
                :id="menuId"
                ref="menu"
                class="workspace-sidebar-profile-menu"
                :class="{ 'is-admin': variant === 'admin' }"
                :style="positionStyle"
                role="menu"
                aria-label="Account options"
                @keydown="onMenuKeydown"
            >
                <p class="workspace-sidebar-profile-menu-heading">Account</p>
                <RouterLink class="workspace-sidebar-profile-menu-item" :to="{ name: 'profile.show' }" role="menuitem" tabindex="-1" @click="navigate">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="6.2" r="3.1"/><path d="M3.8 17c.4-3 2.6-4.8 6.2-4.8s5.8 1.8 6.2 4.8"/></svg>
                    <span>My Profile</span>
                </RouterLink>
                <RouterLink class="workspace-sidebar-profile-menu-item" :to="{ name: 'account.settings' }" role="menuitem" tabindex="-1" @click="navigate">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.8v1.4m0 11.6v1.4M2.8 10h1.4m11.6 0h1.4M4.9 4.9l1 1m8.2 8.2 1 1m0-9.2-1 1m-8.2 8.2-1 1"/><circle cx="10" cy="10" r="3.2"/></svg>
                    <span>Account Settings</span>
                </RouterLink>
                <RouterLink class="workspace-sidebar-profile-menu-item" :to="{ name: 'settings.appearance' }" role="menuitem" tabindex="-1" @click="navigate">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.8a7.2 7.2 0 1 0 0 14.4h1.1a1.7 1.7 0 0 0 1.1-3c-.5-.4-.2-1.3.5-1.3h1.1a3.4 3.4 0 0 0 3.4-3.4c0-3.7-3.2-6.7-7.2-6.7Z"/><circle cx="6.5" cy="9" r=".8"/><circle cx="9.3" cy="6.4" r=".8"/><circle cx="13" cy="7" r=".8"/></svg>
                    <span>Appearance</span>
                </RouterLink>
                <template v-if="auth.hasRole('admin')">
                    <div class="workspace-sidebar-profile-menu-divider" role="separator" />
                    <RouterLink class="workspace-sidebar-profile-menu-item" :to="{ name: 'admin.system-maintenance' }" role="menuitem" tabindex="-1" @click="navigate">
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M12.9 3.2a4.1 4.1 0 0 0-5.3 5.3l-4.4 4.4a1.6 1.6 0 0 0 2.3 2.3l4.4-4.4a4.1 4.1 0 0 0 5.3-5.3l-2.3 2.3-2.1-2.1 2.3-2.5Z"/><circle cx="5.1" cy="14.9" r=".5"/></svg>
                        <span>System Maintenance</span>
                    </RouterLink>
                </template>
                <div class="workspace-sidebar-profile-menu-divider" role="separator" />
                <button class="workspace-sidebar-profile-menu-item is-danger" type="button" role="menuitem" tabindex="-1" aria-haspopup="dialog" @click="logout">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M8.2 4H4.8v12h3.4M11 6.5l3.5 3.5-3.5 3.5M7 10h7.2"/></svg>
                    <span>Sign out</span>
                </button>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useLogoutConfirmation } from '../../../features/auth/composables/useLogoutConfirmation.js';
import { useAuthStore } from '../../../features/auth/stores/authStore.js';

const { requestLogout } = useLogoutConfirmation();
const auth = useAuthStore();
const emit = defineEmits(['navigate']);
defineProps({ variant: { type: String, default: 'user' } });
const open = ref(false);
const triggerWrap = ref(null);
const trigger = ref(null);
const menu = ref(null);
const position = ref({ left: 12, top: 12 });
const menuId = 'workspace-sidebar-account-menu';
const positionStyle = computed(() => ({ left: `${position.value.left}px`, top: `${position.value.top}px` }));

function positionMenu() {
    if (!trigger.value || !menu.value) return;
    const anchor = trigger.value.getBoundingClientRect();
    const popup = menu.value.getBoundingClientRect();
    const margin = 12;
    const gap = 8;
    let left = anchor.right - popup.width;
    let top = anchor.top - popup.height - gap;
    if (top < margin) top = anchor.bottom + gap;
    left = Math.max(margin, Math.min(left, window.innerWidth - popup.width - margin));
    top = Math.max(margin, Math.min(top, window.innerHeight - popup.height - margin));
    position.value = { left, top };
}

async function openMenu() {
    if (open.value) return;
    open.value = true;
    await nextTick();
    positionMenu();
    menu.value?.querySelector('[role="menuitem"]')?.focus();
}

function toggle() {
    if (open.value) close();
    else openMenu();
}

function close({ restoreFocus = false } = {}) {
    open.value = false;
    if (restoreFocus) nextTick(() => trigger.value?.focus({ preventScroll: true }));
}

function navigate() {
    close();
    emit('navigate');
}

function onOutside(event) {
    if (!triggerWrap.value?.contains(event.target) && !menu.value?.contains(event.target)) close();
}

function onDocumentKeydown(event) {
    if (open.value && event.key === 'Escape') {
        event.preventDefault();
        close({ restoreFocus: true });
    }
}

function onMenuKeydown(event) {
    const items = [...(menu.value?.querySelectorAll('[role="menuitem"]') || [])];
    const current = items.indexOf(document.activeElement);
    let target = null;
    if (event.key === 'ArrowDown') target = items[(current + 1) % items.length];
    if (event.key === 'ArrowUp') target = items[(current - 1 + items.length) % items.length];
    if (event.key === 'Home') target = items[0];
    if (event.key === 'End') target = items.at(-1);
    if (target) {
        event.preventDefault();
        target.focus();
    }
}

function logout(event) {
    close();
    requestLogout(event);
}

onMounted(() => {
    document.addEventListener('pointerdown', onOutside);
    document.addEventListener('keydown', onDocumentKeydown);
    window.addEventListener('resize', positionMenu);
    window.addEventListener('scroll', positionMenu, true);
});
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onOutside);
    document.removeEventListener('keydown', onDocumentKeydown);
    window.removeEventListener('resize', positionMenu);
    window.removeEventListener('scroll', positionMenu, true);
});
</script>
