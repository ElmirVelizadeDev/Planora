<template>
    <span class="user-task-badge" :class="`is-${status}`">{{ label(status) }}</span>
</template>

<script setup>
defineProps({ status: { type: String, required: true } });

function label(value) {
    return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, character => character.toUpperCase());
}
</script>
