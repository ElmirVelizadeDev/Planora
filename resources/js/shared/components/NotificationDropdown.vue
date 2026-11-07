<template>
    <div class="header-menu" ref="menu" @keydown.esc="open = false">
        <button
            class="header-action-trigger header-icon-trigger"
            type="button"
            :aria-expanded="open"
            aria-haspopup="dialog"
            aria-label="Open notifications"
            @click="open = !open"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
        </button>
        <section v-if="open" class="header-popover notification-popover" role="dialog" aria-label="Notifications">
            <div class="header-popover-heading">
                <strong>Notifications</strong>
                <span>{{ variant === 'admin' ? 'Platform updates' : 'Workspace updates' }}</span>
            </div>
            <div class="header-feature-unavailable" role="status">
                <span aria-hidden="true">✦</span>
                <strong>Notifications are not connected yet.</strong>
            </div>
        </section>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({ variant: { type: String, required: true } });

const open = ref(false);
const menu = ref(null);
function onOutside(event) {
    if (!menu.value?.contains(event.target)) open.value = false;
}
onMounted(() => document.addEventListener('pointerdown', onOutside));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside));
</script>
