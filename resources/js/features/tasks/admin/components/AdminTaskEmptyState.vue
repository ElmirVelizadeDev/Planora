<template>
    <div class="admin-tasks-empty">
        <span class="admin-tasks-empty-icon" aria-hidden="true">✓</span>
        <h3>{{ deletedView ? 'No deleted tasks' : (hasFilters ? 'No tasks match these filters' : 'No tasks yet') }}</h3>
        <p>{{ deletedView ? 'Deleted tasks that can be recovered will appear here.' : (hasFilters ? 'Try another search or clear the filters.' : 'Create a task to start tracking work across Planora.') }}</p>
        <button v-if="hasFilters || deletedView" class="admin-tasks-secondary-button" type="button" @click="$emit('clear')">{{ deletedView ? 'Show active tasks' : 'Clear filters' }}</button>
        <RouterLink v-else class="admin-tasks-primary-button" :to="{ name: 'admin.tasks.create' }"><span aria-hidden="true">＋</span> Create task</RouterLink>
    </div>
</template>

<script setup>
defineProps({ hasFilters: { type: Boolean, default: false }, deletedView: { type: Boolean, default: false } });
defineEmits(['clear']);
</script>
