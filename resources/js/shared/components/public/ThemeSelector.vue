<template>
    <div class="public-theme-switcher" role="group" aria-label="Choose color theme">
        <button class="public-theme-option" :class="{ 'is-selected': theme === 'light' }" type="button" :aria-pressed="theme === 'light'" aria-label="Light mode" title="Light mode" @click="$emit('select', 'light')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
        </button>
        <button class="public-theme-option" :class="{ 'is-selected': theme === 'dark' }" type="button" :aria-pressed="theme === 'dark'" aria-label="Dark mode" title="Dark mode" @click="$emit('select', 'dark')">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.2 15.4A8.5 8.5 0 0 1 8.6 3.8 8.5 8.5 0 1 0 20.2 15.4Z"/></svg>
        </button>
    </div>
</template>

<script setup>
defineProps({
    theme: { type: String, required: true },
});

defineEmits(['select']);
</script>
