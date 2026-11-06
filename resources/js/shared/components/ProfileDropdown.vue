<template>
    <div class="profile-menu" @keydown.esc="open = false">
        <button
            class="profile-trigger"
            type="button"
            :aria-expanded="open"
            aria-haspopup="menu"
            aria-label="Open profile menu"
            @click="open = !open"
        >
            <UserAvatar :name="displayName" />
            <span class="profile-trigger-copy">
                <strong>{{ displayName }}</strong>
                <small>Account</small>
            </span>
            <span class="profile-chevron" aria-hidden="true">⌄</span>
        </button>

        <div v-if="open" class="profile-popover" role="menu">
            <div class="profile-popover-heading">
                <strong>{{ displayName }}</strong>
                <span v-if="email">{{ email }}</span>
            </div>
            <RouterLink v-for="link in links" :key="link.label" :to="link.to" role="menuitem" @click="open = false">
                {{ link.label }}
            </RouterLink>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import UserAvatar from './UserAvatar.vue';

const props = defineProps({
    links: { type: Array, default: () => [] },
    fallbackName: { type: String, default: 'Your account' },
});

const open = ref(false);
const auth = useAuthStore();
const displayName = computed(() => auth.user?.name || props.fallbackName);
const email = computed(() => auth.user?.email || '');
</script>

<style src="../../../css/components/ProfileDropdown.css"></style>
