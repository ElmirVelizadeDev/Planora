<template>
    <div class="header-search-area" :class="{ 'is-open': open }" @click.self="close">
        <button
            ref="triggerRef"
            class="header-search-mobile-trigger"
            type="button"
            aria-label="Open search"
            :aria-expanded="open"
            @click="openSearch"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4.5 4.5" /></svg>
        </button>
        <form class="workspace-search" role="search" @submit.prevent="submitSearch">
            <svg class="workspace-search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4.5 4.5" /></svg>
            <input
                ref="inputRef"
                v-model="query"
                type="search"
                placeholder="Search your workspace"
                aria-label="Search your workspace"
                @keydown.esc.prevent="close"
            >
            <button v-if="query" class="workspace-search-clear" type="button" aria-label="Clear search" @click="clearSearch">×</button>
            <kbd aria-hidden="true">{{ isMac ? '⌘ K' : 'Ctrl K' }}</kbd>
            <button class="workspace-search-close" type="button" aria-label="Close search" @click="close">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
        </form>
        <p v-if="message" class="workspace-search-status" role="status">{{ message }}</p>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const emit = defineEmits(['search', 'overlay-change']);
const query = ref('');
const open = ref(false);
const message = ref('');
const inputRef = ref(null);
const triggerRef = ref(null);
const isMac = /Mac|iPhone|iPad/.test(navigator.platform || '');
const mobileMedia = window.matchMedia('(max-width: 680px)');
let closeTimer;
let focusTimer;

function emitOverlayState(value) {
    emit('overlay-change', value);
}

function openSearch() {
    if (mobileMedia.matches) {
        window.clearTimeout(closeTimer);
        open.value = true;
        emitOverlayState(true);
    }

    window.cancelAnimationFrame(focusTimer);
    focusTimer = window.requestAnimationFrame(() => inputRef.value?.focus());
}

function close() {
    const wasOpen = open.value;
    open.value = false;
    inputRef.value?.blur();

    window.cancelAnimationFrame(focusTimer);
    focusTimer = window.requestAnimationFrame(() => triggerRef.value?.focus({ preventScroll: true }));

    if (wasOpen) {
        window.clearTimeout(closeTimer);
        closeTimer = window.setTimeout(() => emitOverlayState(false), 180);
    }
}

function clearSearch() {
    query.value = '';
    message.value = '';
    openSearch();
}

function submitSearch() {
    const value = query.value.trim();
    if (!value) return;

    emit('search', value);
    message.value = 'Global search is not connected yet.';
}

function onShortcut(event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        openSearch();
    }
}

function onOutsidePointer(event) {
    const target = event.target;
    if (open.value && target instanceof Element && !target.closest('.workspace-search') && !triggerRef.value?.contains(target)) {
        close();
    }
}

function onViewportChange(event) {
    if (!event.matches && open.value) close();
}

onMounted(() => window.addEventListener('keydown', onShortcut));
onMounted(() => {
    document.addEventListener('pointerdown', onOutsidePointer);
    mobileMedia.addEventListener('change', onViewportChange);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onShortcut);
    document.removeEventListener('pointerdown', onOutsidePointer);
    mobileMedia.removeEventListener('change', onViewportChange);
    window.clearTimeout(closeTimer);
    window.cancelAnimationFrame(focusTimer);
});
defineExpose({ close });
</script>
