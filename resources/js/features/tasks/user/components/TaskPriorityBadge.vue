<template>
    <span class="user-task-badge" :class="`is-${priority}`">{{ label(priority) }}{{ suffix }}</span>
</template>

<script setup>
defineProps({
    priority: { type: String, required: true },
    suffix: { type: String, default: ' priority' },
});

function label(value) {
    return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, character => character.toUpperCase());
}
</script>
