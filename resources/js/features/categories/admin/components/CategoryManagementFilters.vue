<template>
    <label><select :value="status" aria-label="Category status" @change="$emit('update:status', $event.target.value); $emit('change')"><option value="active">Active</option><option value="archived">Archived</option><option value="all">All statuses</option></select></label>
</template>

<script setup>
defineProps({ status: { type: String, default: 'active' } });
defineEmits(['change', 'update:status']);
</script>
