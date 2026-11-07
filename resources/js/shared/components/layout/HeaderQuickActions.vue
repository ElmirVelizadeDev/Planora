<template>
    <div class="header-menu" ref="menu" @keydown.esc="open = false">
        <button
            class="header-action-trigger quick-add-trigger"
            type="button"
            :aria-expanded="open"
            aria-haspopup="dialog"
            aria-label="Quick add"
            @click="open = !open"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
            <span>Quick add</span>
        </button>
        <section v-if="open" class="header-popover quick-add-popover" role="dialog" aria-label="Quick add">
            <strong>Quick task creation</strong>
            <p>Task creation is not connected to a backend yet.</p>
        </section>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const open = ref(false);
const menu = ref(null);
function onOutside(event) {
    if (!menu.value?.contains(event.target)) open.value = false;
}
onMounted(() => document.addEventListener('pointerdown', onOutside));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside));
</script>
