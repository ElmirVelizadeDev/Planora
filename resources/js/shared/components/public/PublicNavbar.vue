<template>
    <header class="public-header">
        <nav class="public-nav" aria-label="Main navigation">
            <RouterLink class="public-brand" :to="{ name: 'home' }" aria-label="Planora home">
                <img :src="'/images/branding/planora-master-512.png'" width="36" height="36" alt="" />
                <span>Planora</span>
            </RouterLink>
            <button class="public-menu-toggle" type="button" :aria-expanded="mobileOpen" aria-controls="public-navigation" aria-label="Toggle navigation" @click="mobileOpen = !mobileOpen">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
            </button>
            <div id="public-navigation" class="public-nav-links" :class="{ 'is-open': mobileOpen }">
                <RouterLink :to="{ name: 'home' }" @click="mobileOpen = false">Home</RouterLink>
                <template v-if="auth.isAuthenticated">
                    <div class="public-profile-menu" @keydown.esc="profileOpen = false">
                        <button class="public-profile-trigger" type="button" aria-label="Open profile menu" :aria-expanded="profileOpen" aria-haspopup="menu" @click="profileOpen = !profileOpen">
                            <UserAvatar :name="auth.user?.name || 'Your account'" />
                        </button>
                        <div v-if="profileOpen" class="public-profile-popover" role="menu">
                            <RouterLink :to="{ name: 'profile.show' }" role="menuitem" @click="closeMenus">My Profile</RouterLink>
                            <button type="button" role="menuitem" @click="logout">Logout</button>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <RouterLink :to="{ name: 'login' }" @click="mobileOpen = false">Login</RouterLink>
                    <RouterLink class="public-nav-register" :to="{ name: 'register' }" @click="mobileOpen = false">Register</RouterLink>
                </template>
            </div>
        </nav>
    </header>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useAuthStore } from '../../../features/auth/stores/authStore.js';
import { useLogoutConfirmation } from '../../../features/auth/composables/useLogoutConfirmation.js';
import UserAvatar from '../UserAvatar.vue';

const auth = useAuthStore();
const mobileOpen = ref(false);
const profileOpen = ref(false);
const { requestLogout: openLogoutConfirmation } = useLogoutConfirmation();

function closeMenus() {
    profileOpen.value = false;
    mobileOpen.value = false;
}
function onOutside(event) {
    if (!event.target.closest('.public-profile-menu')) profileOpen.value = false;
}
function logout(event) {
    closeMenus();
    openLogoutConfirmation(event);
}
onMounted(() => document.addEventListener('pointerdown', onOutside));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside));
</script>
