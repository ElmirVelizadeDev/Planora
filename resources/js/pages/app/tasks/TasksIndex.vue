<template>
    <main class="user-tasks" :aria-busy="loading">
        <header class="user-tasks-header">
            <div><p class="user-tasks-eyebrow">Your workspace</p><h1>My Tasks</h1><p class="user-tasks-description">Manage your tasks, organize priorities, and keep track of your progress.</p></div>
            <RouterLink class="user-tasks-primary-button" :to="{ name: 'tasks.create' }"><span aria-hidden="true">＋</span> Create task</RouterLink>
        </header>

        <section class="user-tasks-stat-grid" aria-label="Task statistics">
            <article v-for="stat in stats" :key="stat.key" class="user-tasks-stat-card"><span class="user-tasks-stat-icon" :class="`is-${stat.tone}`" aria-hidden="true"><svg v-if="stat.icon === 'total'" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h5"/></svg><svg v-else-if="stat.icon === 'progress'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><svg v-else-if="stat.icon === 'completed'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg><svg v-else-if="stat.icon === 'pending'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><svg v-else viewBox="0 0 24 24"><path d="M12 8v5m0 3h.01"/><path d="M10.3 4.9 2.8 18a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 4.9a2 2 0 0 0-3.4 0Z"/></svg></span><strong>{{ formatNumber(statistics[stat.key]) }}</strong><span>{{ stat.label }}</span></article>
        </section>

        <section class="user-tasks-panel" aria-label="My task list">
            <div class="user-tasks-toolbar">
                <div class="user-tasks-panel-heading"><h2>All tasks</h2><p>Search and organize your work</p></div>
                <TaskFilters
                    v-model:search-input="searchInput"
                    v-model:status="filters.status"
                    v-model:priority="filters.priority"
                    v-model:category-id="filters.category_id"
                    v-model:due="filters.due"
                    v-model:start-date="filters.start_date"
                    v-model:end-date="filters.end_date"
                    v-model:sort="sort"
                    :loading="loading"
                    :categories="categories"
                    :has-toolbar-changes="hasToolbarChanges"
                    @search="onSearch"
                    @change="applyFilters"
                    @refresh="load"
                    @clear="clearFilters"
                />
            </div>

            <div v-if="notice" class="user-tasks-notice" role="status">{{ notice }}</div>
            <div v-if="error" class="user-tasks-feedback is-error" role="alert"><span>{{ error }}</span><button type="button" @click="load">Try again</button></div>
            <div v-else-if="loading && !tasks.length" class="user-tasks-loading" role="status"><span class="user-tasks-spinner" aria-hidden="true"></span>Loading your tasks…</div>
            <TaskEmptyState v-else-if="!tasks.length" :has-filters="hasFilters" @clear="clearFilters" />
            <template v-else>
                <TaskList :tasks="tasks" :pending-statuses="pendingStatuses" :loading="loading" @status-change="setStatus" @delete="confirmDelete" />
                <footer class="user-tasks-pagination"><p>Showing <strong>{{ rangeStart }}–{{ rangeEnd }}</strong> of <strong>{{ formatNumber(total) }}</strong> tasks</p><div class="user-tasks-page-controls"><label>Rows <select v-model.number="perPage" @change="page = 1; load()"><option v-for="size in [10,25,50,100]" :key="size" :value="size">{{ size }}</option></select></label><button type="button" :disabled="page <= 1 || loading" aria-label="Previous page" @click="goToPage(page - 1)">‹</button><span>Page <strong>{{ page }}</strong> of {{ lastPage }}</span><button type="button" :disabled="page >= lastPage || loading" aria-label="Next page" @click="goToPage(page + 1)">›</button></div></footer>
            </template>
        </section>

        <div v-if="taskToDelete" class="user-task-modal-backdrop" @click.self="taskToDelete = null" @keydown.esc="taskToDelete = null"><section class="user-task-confirm" role="dialog" aria-modal="true" aria-labelledby="delete-task-title"><p class="user-task-eyebrow">Delete task</p><h2 id="delete-task-title">Delete “{{ taskToDelete.title }}”?</h2><p>This task will be moved to deleted records.</p><div class="user-task-confirm-actions"><button class="user-tasks-secondary-button" type="button" @click="taskToDelete = null">Cancel</button><button class="user-tasks-delete-button" type="button" :disabled="deleting" @click="deleteTask">{{ deleting ? 'Deleting…' : 'Delete task' }}</button></div></section></div>
    </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import TaskEmptyState from '../../../features/tasks/user/components/TaskEmptyState.vue';
import TaskFilters from '../../../features/tasks/user/components/TaskFilters.vue';
import TaskList from '../../../features/tasks/user/components/TaskList.vue';
import { useUserTasks } from '../../../features/tasks/user/composables/useUserTasks.js';

const route = useRoute(), router = useRouter();
const {
    tasks, categories, loading, error, notice, page, perPage, total, lastPage, statistics,
    searchInput, sort, filters, pendingStatuses, hasFilters, hasToolbarChanges,
    rangeStart, rangeEnd, load, onSearch, applyFilters, clearFilters, goToPage,
    setStatus, remove, showNotice, loadCategories,
} = useUserTasks();
const taskToDelete = ref(null), deleting = ref(false);
const stats = computed(() => [
    { key: 'total', label: 'Total tasks', icon: 'total', tone: 'primary' },
    { key: 'in_progress', label: 'In progress', icon: 'progress', tone: 'warning' },
    { key: 'completed', label: 'Completed', icon: 'completed', tone: 'success' },
    { key: 'pending', label: 'Pending', icon: 'pending', tone: 'neutral' },
    { key: 'overdue', label: 'Overdue', icon: 'overdue', tone: 'danger' },
]);
function formatNumber(value) { return new Intl.NumberFormat().format(Number(value) || 0); }
function confirmDelete(task) { taskToDelete.value = task; }
async function deleteTask() {
    if (!taskToDelete.value || deleting.value) return;
    deleting.value = true;
    await remove(taskToDelete.value);
    taskToDelete.value = null;
    deleting.value = false;
}
onMounted(async () => {
    if (route.query.notice) {
        const messages = { created: 'Task created.', updated: 'Task updated.', deleted: 'Task deleted.' };
        if (messages[route.query.notice]) showNotice(messages[route.query.notice]);
        await router.replace({ name: 'tasks.index' });
    }
    load(); loadCategories();
});
</script>

<style src="../../../../css/pages/app/tasks/index.css"></style>
