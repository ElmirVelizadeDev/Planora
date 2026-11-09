<template>
    <div class="user-tasks-empty">
        <span class="user-tasks-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="m8 12 2 2 5-5M17 8h.01"/></svg></span>
        <h3>{{ hasFilters ? 'No tasks match these filters' : 'Your task list is clear' }}</h3>
        <p>{{ hasFilters ? 'Try changing your search or filters.' : 'Create a task to start organizing your work.' }}</p>
        <button v-if="hasFilters" class="user-tasks-secondary-button" type="button" @click="$emit('clear')">Clear filters</button>
        <RouterLink v-else class="user-tasks-primary-button" :to="{ name: 'tasks.create' }"><span aria-hidden="true">＋</span> Create task</RouterLink>
    </div>
</template>

<script setup>
defineProps({ hasFilters: { type: Boolean, default: false } });
defineEmits(['clear']);
</script>
