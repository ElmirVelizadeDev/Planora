<template>
    <main class="admin-projects" :aria-busy="loading">
        <header class="admin-projects-header">
            <div>
                <p class="admin-projects-eyebrow">Administration</p>
                <h1>Projects</h1>
                <p class="admin-projects-description">Monitor project ownership, schedules and lifecycle across Planora.</p>
            </div>
            <button class="admin-projects-primary-button" type="button" @click="openCreate">＋ Create project</button>
        </header>

        <AdminProjectStats :statistics="statistics" />

        <section class="admin-projects-panel" aria-label="Project directory">
            <div class="admin-projects-toolbar">
                <div class="admin-projects-panel-heading"><h2>All projects</h2><p>Search and manage projects across Planora</p></div>
                <AdminProjectFilters
                    v-model:search-input="searchInput"
                    v-model:status="status"
                    :loading="loading"
                    :has-filters="hasFilters"
                    @search="onSearch"
                    @apply="applyFilters"
                    @refresh="refreshProjects"
                    @clear="clearFilters"
                />
            </div>

            <div v-if="error && projects.length" class="admin-projects-feedback is-error" role="alert">{{ error }} <button type="button" @click="loadProjects">Try again</button></div>
            <div v-if="error && !projects.length" class="admin-projects-feedback is-error" role="alert">{{ error }} <button type="button" @click="loadProjects">Try again</button></div>
            <div v-else-if="loading && !projects.length" class="admin-projects-feedback" role="status"><span class="admin-projects-spinner" aria-hidden="true"></span> Loading projects…</div>
            <div v-else-if="!projects.length" class="admin-projects-empty"><span class="admin-projects-empty-icon" aria-hidden="true">▧</span><h3>{{ hasFilters ? 'No projects match these filters' : 'No projects yet' }}</h3><p>{{ hasFilters ? 'Try a different search or clear the filters.' : 'Create a project to start managing work across Planora.' }}</p><button v-if="hasFilters" class="admin-projects-secondary-button" type="button" @click="clearFilters">Clear filters</button></div>
            <AdminProjectTable
                v-else
                :projects="projects"
                :loading="loading"
                :page="page"
                :last-page="lastPage"
                :total="total"
                :range-start="rangeStart"
                :range-end="rangeEnd"
                @page-change="goToPage"
                @edit="openEdit"
                @delete="openDelete"
            />
        </section>

        <div v-if="['create', 'edit'].includes(dialog.type)" class="admin-projects-backdrop" @click.self="closeDialog">
            <AdminProjectForm :project="dialog.project" :owners="owners" :owner-error="ownersError" :saving="dialog.saving" :error="dialog.error" :errors="dialog.errors" @cancel="closeDialog" @submit="saveFromDialog" />
        </div>
        <AdminProjectDeleteDialog v-if="dialog.type === 'delete'" :project="dialog.project" :saving="dialog.saving" :error="dialog.error" @close="closeDialog" @confirm="deleteFromDialog" />
        <div v-if="notice" class="admin-projects-notice" role="status">{{ notice }}</div>
    </main>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import AdminProjectDeleteDialog from '../../../features/projects/admin/components/AdminProjectDeleteDialog.vue';
import AdminProjectFilters from '../../../features/projects/admin/components/AdminProjectFilters.vue';
import AdminProjectForm from '../../../features/projects/admin/components/AdminProjectForm.vue';
import AdminProjectStats from '../../../features/projects/admin/components/AdminProjectStats.vue';
import AdminProjectTable from '../../../features/projects/admin/components/AdminProjectTable.vue';
import { useAdminProjects } from '../../../features/projects/admin/composables/useAdminProjects.js';

const {
    projects, owners, ownersError, loading, error, searchInput, status, page, total, lastPage,
    statistics, dialog, hasFilters, rangeStart, rangeEnd, loadProjects, loadOwners, refreshProjects,
    onSearch, applyFilters, clearFilters, goToPage, openCreate, openEdit, openDelete,
    closeDialog, saveProject, confirmDelete,
} = useAdminProjects();
const notice = ref('');
let noticeTimer;

function showNotice(message) {
    notice.value = message;
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => { notice.value = ''; }, 3200);
}

async function saveFromDialog(payload) {
    if (await saveProject(payload)) showNotice('Project saved.');
}

async function deleteFromDialog() {
    if (await confirmDelete()) showNotice('Project deleted.');
}

onMounted(() => { loadProjects(); loadOwners(); });
onBeforeUnmount(() => clearTimeout(noticeTimer));
</script>

<style src="../../../../css/pages/admin/projects/styles/AdminProjectsIndex.css"></style>
