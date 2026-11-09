<template>
    <div class="admin-tasks-empty">
        <span class="admin-tasks-empty-icon" aria-hidden="true">✓</span>
        <h3>{{ hasFilters ? 'No tasks match these filters' : 'No tasks yet' }}</h3>
        <p>{{ hasFilters ? 'Try another search or clear the filters.' : 'Create a task to start tracking work across Planora.' }}</p>
        <button v-if="hasFilters" class="admin-tasks-secondary-button" type="button" @click="$emit('clear')">Clear filters</button>
        <RouterLink v-else class="admin-tasks-primary-button" :to="{ name: 'admin.tasks.create' }"><span aria-hidden="true">＋</span> Create task</RouterLink>
    </div>
</template>

<script setup>
defineProps({ hasFilters: { type: Boolean, default: false } });
defineEmits(['clear']);
</script>
