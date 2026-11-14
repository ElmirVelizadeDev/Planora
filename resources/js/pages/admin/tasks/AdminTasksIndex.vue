<template>
    <main class="admin-tasks" :aria-busy="loading">
        <header class="admin-tasks-header">
            <div><p class="admin-tasks-eyebrow">Administration</p><h1>Tasks</h1><p class="admin-tasks-description">Monitor and manage tasks across the Planora platform.</p></div>
            <RouterLink v-if="isAdmin" class="admin-tasks-primary-button" :to="{ name: 'admin.tasks.create' }" aria-label="Create task">＋ Create task</RouterLink>
        </header>

        <AdminTaskStats :statistics="statistics" />

        <section class="admin-tasks-panel" aria-label="Task directory">
            <div class="admin-tasks-toolbar">
                <div class="admin-tasks-panel-heading"><h2>All tasks</h2><p>Search and manage tasks across Planora</p></div>
                <AdminTaskFilters
                    v-model:search-input="searchInput"
                    :filters="filters"
                    :assignees="assignees"
                    :categories="categories"
                    :loading="loading || statusSaving"
                    :has-filters="hasFilters"
                    :is-admin="isAdmin"
                    @update:filter="updateFilter"
                    @search="onSearch"
                    @apply="applyFilters"
                    @refresh="refreshTasks"
                    @clear="clearFilters"
                />
            </div>

            <div v-if="error" class="admin-tasks-feedback is-error" role="alert"><span>{{ error }}</span><button type="button" @click="loadTasks">Try again</button></div>
            <div v-else-if="loading && !tasks.length" class="admin-tasks-loading" role="status"><span class="admin-tasks-spinner" aria-hidden="true"></span>Loading tasks…</div>
            <AdminTaskEmptyState v-else-if="!tasks.length" :has-filters="hasFilters" :deleted-view="Boolean(filters.deleted)" @clear="clearFilters" />
            <AdminTaskTable
                v-else
                :tasks="tasks"
                :loading="loading || statusSaving"
                :page="page"
                :per-page="perPage"
                :last-page="lastPage"
                :total="total"
                :deleted-view="Boolean(filters.deleted)"
                :range-start="rangeStart"
                :range-end="rangeEnd"
                :is-admin="isAdmin"
                :can-manage-status="canManageStatus"
                @delete="confirmDelete"
                @restore="restoreTask"
                @page-change="goToPage"
                @per-page-change="changePerPage"
                @status-change="changeTaskStatus"
            />
        </section>
        <AdminTaskDeleteDialog
            v-if="deleteDialogTask"
            :task="deleteDialogTask"
            :saving="deleteSaving"
            :error="deleteError"
            @close="closeDeleteDialog"
            @confirm="confirmTaskDeletion"
            @clear-error="deleteError = ''"
        />
    </main>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useAuthStore } from '../../../features/auth/stores/authStore.js';
import AdminTaskDeleteDialog from '../../../features/tasks/admin/components/AdminTaskDeleteDialog.vue';
import AdminTaskEmptyState from '../../../features/tasks/admin/components/AdminTaskEmptyState.vue';
import AdminTaskFilters from '../../../features/tasks/admin/components/AdminTaskFilters.vue';
import AdminTaskStats from '../../../features/tasks/admin/components/AdminTaskStats.vue';
import AdminTaskTable from '../../../features/tasks/admin/components/AdminTaskTable.vue';
import { useAdminTasks } from '../../../features/tasks/admin/composables/useAdminTasks.js';

const {
    tasks, assignees, categories, loading, error, searchInput, page, perPage, total, lastPage,
    filters, statistics, hasFilters, rangeStart, rangeEnd, loadTasks, refreshTasks,
    onSearch, applyFilters, updateFilter, clearFilters, goToPage, changePerPage, removeTask, changeTaskStatus: changeTaskStatusAction, restoreTask: restoreTaskAction,
} = useAdminTasks();

const auth = useAuthStore();
const isAdmin = computed(() => auth.hasRole('admin'));
const canManageStatus = computed(() => isAdmin.value || auth.hasManagementPermission('tasks.status.manage'));
const statusSaving = ref(false);

async function changeTaskStatus({ task, status }) {
    if (statusSaving.value) return;
    statusSaving.value = true;
    try { await changeTaskStatusAction(task, status); }
    finally { statusSaving.value = false; }
}

const deleteDialogTask = ref(null), deleteSaving = ref(false), deleteError = ref('');

async function restoreTask(task) {
    if (!window.confirm(`Restore “${task.title}” to active tasks?`)) return;
    if (await restoreTaskAction(task)) window.dispatchEvent(new CustomEvent('planora:toast', { detail: { type: 'success', message: 'Task restored.' } }));
}

function confirmDelete(task) {
    deleteError.value = '';
    deleteDialogTask.value = task;
}

function closeDeleteDialog() {
    if (deleteSaving.value) return;
    deleteDialogTask.value = null;
    deleteError.value = '';
}

async function confirmTaskDeletion() {
    if (!deleteDialogTask.value || deleteSaving.value) return;
    deleteSaving.value = true;
    deleteError.value = '';
    try {
        const deleted = await removeTask(deleteDialogTask.value);
        if (deleted) deleteDialogTask.value = null;
        else deleteError.value = 'The task could not be deleted. Please try again.';
    } finally {
        deleteSaving.value = false;
    }
}
</script>

<style src="../../../../css/pages/admin/tasks/styles/AdminTasksIndex.css"></style>
