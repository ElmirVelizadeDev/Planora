<template>
    <div class="admin-tasks-filters">
        <div class="admin-tasks-filter-row">
            <label class="admin-tasks-search"><span class="admin-tasks-search-icon" aria-hidden="true">⌕</span><input :value="searchInput" type="search" aria-label="Search task title or description" placeholder="Search title or description" @input="$emit('update:searchInput', $event.target.value); $emit('search')"></label>
            <label><select :value="filters.status" aria-label="Status" @change="updateFilter('status', $event.target.value)"><option value="">Status</option><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select></label>
            <label><select :value="filters.priority" aria-label="Priority" @change="updateFilter('priority', $event.target.value)"><option value="">Priority</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
            <label v-if="isAdmin"><select :value="filters.assigned_user_id" aria-label="Assigned user" @change="updateFilter('assigned_user_id', $event.target.value)"><option value="">Assigned user</option><option v-for="user in assignees" :key="user.id" :value="user.id">{{ user.name }}</option></select></label>
            <label><select :value="filters.category_id" aria-label="Category" @change="updateFilter('category_id', $event.target.value)"><option value="">All categories</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label>
        </div>
        <div class="admin-tasks-date-row">
            <label v-if="isAdmin" class="admin-tasks-lifecycle-filter"><select :value="filters.deleted" aria-label="Task lifecycle" @change="updateFilter('deleted', $event.target.value)"><option value="">Active tasks</option><option value="1">Deleted tasks</option></select></label>
            <label class="admin-tasks-date-filter"><input :value="filters.start_date" type="date" aria-label="Start Date" @change="updateFilter('start_date', $event.target.value)"></label>
            <label class="admin-tasks-date-filter"><input :value="filters.end_date" type="date" aria-label="End Date" @change="updateFilter('end_date', $event.target.value)"></label>
            <button v-if="hasFilters" class="admin-tasks-clear-button" type="button" @click="$emit('clear')">Clear filters</button>
            <button class="admin-users-secondary-button admin-users-refresh-button" type="button" :disabled="loading" :aria-busy="loading" @click="$emit('refresh')"><svg class="admin-users-refresh-icon" :class="{ 'is-loading': loading }" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/></svg>{{ loading ? 'Refreshing…' : 'Refresh' }}</button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    searchInput: { type: String, default: '' },
    filters: { type: Object, required: true },
    assignees: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    hasFilters: { type: Boolean, default: false },
    isAdmin: { type: Boolean, default: true },
});
const emit = defineEmits(['update:searchInput', 'update:filter', 'search', 'apply', 'refresh', 'clear']);

function updateFilter(name, value) {
    emit('update:filter', name, value);
    emit('apply');
}
</script>
