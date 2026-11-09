<template>
    <main class="admin-tasks" :aria-busy="loading">
        <header class="admin-tasks-header">
            <div><p class="admin-tasks-eyebrow">Administration</p><h1>Tasks</h1><p class="admin-tasks-description">Monitor and manage tasks across the Planora platform.</p></div>
            <RouterLink class="admin-tasks-primary-button" :to="{ name: 'admin.tasks.create' }"><span aria-hidden="true">＋</span> Create task</RouterLink>
        </header>

        <AdminTaskStats :statistics="statistics" />

        <section class="admin-tasks-panel" aria-label="Task directory">
            <div class="admin-tasks-toolbar">
                <div class="admin-tasks-panel-heading"><h2>All tasks</h2><p>Search and manage tasks across Planora</p></div>
                <AdminTaskFilters
                    v-model:search-input="searchInput"
                    :filters="filters"
                    :assignees="assignees"
                    :loading="loading"
                    :has-filters="hasFilters"
                    @update:filter="updateFilter"
                    @search="onSearch"
                    @apply="applyFilters"
                    @refresh="refreshTasks"
                    @clear="clearFilters"
                />
            </div>

            <div v-if="error" class="admin-tasks-feedback is-error" role="alert"><span>{{ error }}</span><button type="button" @click="loadTasks">Try again</button></div>
            <div v-else-if="loading && !tasks.length" class="admin-tasks-loading" role="status"><span class="admin-tasks-spinner" aria-hidden="true"></span>Loading tasks…</div>
            <AdminTaskEmptyState v-else-if="!tasks.length" :has-filters="hasFilters" @clear="clearFilters" />
            <AdminTaskTable
                v-else
                :tasks="tasks"
                :loading="loading"
                :page="page"
                :per-page="perPage"
                :last-page="lastPage"
                :total="total"
                :range-start="rangeStart"
                :range-end="rangeEnd"
                @delete="confirmDelete"
                @page-change="goToPage"
                @per-page-change="changePerPage"
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
import { ref } from 'vue';
import AdminTaskDeleteDialog from '../../../features/tasks/admin/components/AdminTaskDeleteDialog.vue';
import AdminTaskEmptyState from '../../../features/tasks/admin/components/AdminTaskEmptyState.vue';
import AdminTaskFilters from '../../../features/tasks/admin/components/AdminTaskFilters.vue';
import AdminTaskStats from '../../../features/tasks/admin/components/AdminTaskStats.vue';
import AdminTaskTable from '../../../features/tasks/admin/components/AdminTaskTable.vue';
import { useAdminTasks } from '../../../features/tasks/admin/composables/useAdminTasks.js';

const {
    tasks, assignees, loading, error, searchInput, page, perPage, total, lastPage,
    filters, statistics, hasFilters, rangeStart, rangeEnd, loadTasks, refreshTasks,
    onSearch, applyFilters, updateFilter, clearFilters, goToPage, changePerPage, removeTask,
} = useAdminTasks();

const deleteDialogTask = ref(null), deleteSaving = ref(false), deleteError = ref('');

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
