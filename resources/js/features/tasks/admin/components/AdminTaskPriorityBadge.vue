<template>
    <span :class="[badgeClass, `is-${priority}`]">{{ label(priority) }}{{ suffix }}</span>
</template>

<script setup>
const props = defineProps({
    priority: { type: String, required: true },
    suffix: { type: String, default: '' },
    context: { type: String, default: 'table', validator: value => ['table', 'detail'].includes(value) },
});

const badgeClass = props.context === 'detail' ? 'admin-task-badge' : 'admin-tasks-badge';
function label(value) { return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, character => character.toUpperCase()); }
</script>
