<template>
    <div class="user-tasks-controls">
        <label class="user-tasks-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.3"/><path d="m15.5 15.5 4.2 4.2"/></svg>
            <input :value="searchInput" type="search" aria-label="Search tasks" placeholder="Search title or description" @input="onSearchInput">
        </label>
        <label><span class="sr-only">Status</span><select :value="status" aria-label="Status" @change="updateFilter('status', $event.target.value)"><option value="">All statuses</option><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select></label>
        <label><span class="sr-only">Priority</span><select :value="priority" aria-label="Priority" @change="updateFilter('priority', $event.target.value)"><option value="">All priorities</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
        <label><span class="sr-only">Due date</span><select :value="due" aria-label="Due date" @change="updateFilter('due', $event.target.value)"><option value="">All dates</option><option value="today">Due today</option><option value="upcoming">Upcoming</option><option value="overdue">Overdue</option></select></label>
        <label><span class="sr-only">Sort tasks</span><select :value="sort" aria-label="Sort tasks" @change="updateFilter('sort', $event.target.value)"><option value="">Newest first</option><option value="oldest">Oldest first</option><option value="due_asc">Due date: earliest</option><option value="due_desc">Due date: latest</option><option value="priority_desc">Priority: highest</option><option value="priority_asc">Priority: lowest</option></select></label>
        <button class="user-tasks-refresh" type="button" :disabled="loading" :aria-busy="loading" @click="$emit('refresh')"><svg :class="{ 'is-loading': loading }" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/></svg><span>{{ loading ? 'Refreshing…' : 'Refresh' }}</span></button>
        <button v-if="hasToolbarChanges" class="user-tasks-clear" type="button" @click="$emit('clear')">Clear filters</button>
    </div>
</template>

<script setup>
defineProps({
    searchInput: { type: String, default: '' },
    status: { type: String, default: '' },
    priority: { type: String, default: '' },
    due: { type: String, default: '' },
    sort: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    hasToolbarChanges: { type: Boolean, default: false },
});
const emit = defineEmits(['update:searchInput', 'update:status', 'update:priority', 'update:due', 'update:sort', 'search', 'change', 'refresh', 'clear']);

function onSearchInput(event) {
    emit('update:searchInput', event.target.value);
    emit('search');
}

function updateFilter(filter, value) {
    emit(`update:${filter}`, value);
    emit('change');
}
</script>
