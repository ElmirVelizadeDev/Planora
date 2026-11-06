<template>
    <div class="notification-menu" @keydown.esc="open = false">
        <button
            class="notification-trigger"
            type="button"
            :aria-expanded="open"
            aria-haspopup="dialog"
            aria-label="Open notifications"
            @click="open = !open"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
            </svg>
        </button>

        <section v-if="open" class="notification-popover" role="dialog" aria-label="Notifications">
            <div class="notification-heading">
                <strong>Notifications</strong>
                <span>Updates for your workspace</span>
            </div>
            <div class="notification-empty">
                <span class="notification-empty-icon" aria-hidden="true">✦</span>
                <strong>You're all caught up</strong>
                <span>New updates will appear here.</span>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const open = ref(false);
</script>

<style src="../../../css/components/NotificationDropdown.css"></style>
