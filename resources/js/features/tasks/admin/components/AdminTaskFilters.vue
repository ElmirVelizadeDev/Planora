<template>
    <div class="admin-tasks-filters">
        <label class="admin-tasks-search"><span class="admin-tasks-search-icon" aria-hidden="true">⌕</span><input :value="searchInput" type="search" aria-label="Search task title or description" placeholder="Search title or description" @input="$emit('update:searchInput', $event.target.value); $emit('search')"></label>
        <label><select :value="filters.status" aria-label="Status" @change="updateFilter('status', $event.target.value)"><option value="">Status</option><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select></label>
        <label><select :value="filters.priority" aria-label="Priority" @change="updateFilter('priority', $event.target.value)"><option value="">Priority</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
        <label><select :value="filters.assigned_user_id" aria-label="Assigned user" @change="updateFilter('assigned_user_id', $event.target.value)"><option value="">Assigned user</option><option v-for="user in assignees" :key="user.id" :value="user.id">{{ user.name }}</option></select></label>
        <label class="admin-tasks-date-filter"><input :value="filters.due_after" type="date" aria-label="Due date" @change="updateFilter('due_after', $event.target.value)"></label>
        <button class="admin-users-secondary-button admin-users-refresh-button" type="button" :disabled="loading" :aria-busy="loading" @click="$emit('refresh')"><svg class="admin-users-refresh-icon" :class="{ 'is-loading': loading }" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/></svg>{{ loading ? 'Refreshing…' : 'Refresh' }}</button>
        <button v-if="hasFilters" class="admin-tasks-clear-button" type="button" @click="$emit('clear')">Clear filters</button>
    </div>
</template>

<script setup>
defineProps({
    searchInput: { type: String, default: '' },
    filters: { type: Object, required: true },
    assignees: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    hasFilters: { type: Boolean, default: false },
});
const emit = defineEmits(['update:searchInput', 'update:filter', 'search', 'apply', 'refresh', 'clear']);

function updateFilter(name, value) {
    emit('update:filter', name, value);
    emit('apply');
}
</script>
