<template>
    <span :class="[badgeClass, `is-${status}`]">{{ label(status) }}</span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    context: { type: String, default: 'table', validator: value => ['table', 'detail'].includes(value) },
});

const badgeClass = computed(() => props.context === 'detail' ? 'admin-task-badge' : 'admin-tasks-badge');
function label(value) { return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, character => character.toUpperCase()); }
</script>
