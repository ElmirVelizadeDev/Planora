<template>
    <main class="admin-projects" :aria-busy="loading">
        <header class="admin-projects-header">
            <div>
                <p class="admin-projects-eyebrow">Administration</p>
                <h1>Projects</h1>
                <p class="admin-projects-description">Monitor project ownership, schedules and lifecycle across Planora.</p>
            </div>
            <button v-if="isAdmin" class="admin-projects-primary-button" type="button" @click="openCreate">＋ Create project</button>
        </header>

        <AdminProjectStats :statistics="statistics" />

        <section class="admin-projects-panel" aria-label="Project directory">
            <div class="admin-projects-toolbar">
                <div class="admin-projects-panel-heading"><h2>All projects</h2><p>Search and manage projects across Planora</p></div>
                <AdminProjectFilters
                    v-model:search-input="searchInput"
                    v-model:status="status"
                    v-model:category-id="categoryId"
                    :categories="categories"
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
                :loading="loading || statusSaving"
                :page="page"
                :last-page="lastPage"
                :total="total"
                :range-start="rangeStart"
                :range-end="rangeEnd"
                :is-admin="isAdmin"
                :can-manage-status="canManageStatus"
                @page-change="goToPage"
                @edit="openEdit"
                @delete="openDelete"
                @status-change="changeProjectStatus"
            />
        </section>

        <div v-if="['create', 'edit'].includes(dialog.type)" class="admin-projects-backdrop" @click.self="closeDialog">
            <AdminProjectForm :project="dialog.project" :owners="owners" :categories="categories" :owner-error="ownersError" :saving="dialog.saving" :error="dialog.error" :errors="dialog.errors" @cancel="closeDialog" @submit="saveFromDialog" />
        </div>
        <AdminProjectDeleteDialog v-if="dialog.type === 'delete'" :project="dialog.project" :saving="dialog.saving" :error="dialog.error" @close="closeDialog" @confirm="deleteFromDialog" />
        <div v-if="notice" class="admin-projects-notice" role="status">{{ notice }}</div>
    </main>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { useAuthStore } from '../../../features/auth/stores/authStore.js';
import AdminProjectDeleteDialog from '../../../features/projects/admin/components/AdminProjectDeleteDialog.vue';
import AdminProjectFilters from '../../../features/projects/admin/components/AdminProjectFilters.vue';
import AdminProjectForm from '../../../features/projects/admin/components/AdminProjectForm.vue';
import AdminProjectStats from '../../../features/projects/admin/components/AdminProjectStats.vue';
import AdminProjectTable from '../../../features/projects/admin/components/AdminProjectTable.vue';
import { useAdminProjects } from '../../../features/projects/admin/composables/useAdminProjects.js';
import { getAdminProjectCategories, updateAdminProjectStatus } from '../../../features/projects/admin/services/adminProjectApi.js';

const {
    projects, owners, ownersError, loading, error, searchInput, status, categoryId, page, total, lastPage,
    statistics, dialog, hasFilters, rangeStart, rangeEnd, loadProjects, loadOwners, refreshProjects,
    onSearch, applyFilters, clearFilters, goToPage, openCreate, openEdit, openDelete,
    closeDialog, saveProject, confirmDelete,
} = useAdminProjects();
const notice = ref('');
const categories = ref([]);
const statusSaving = ref(false);
const auth = useAuthStore();
const isAdmin = computed(() => auth.hasRole('admin'));
const canManageStatus = computed(() => isAdmin.value || auth.hasManagementPermission('projects.status.manage'));
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

async function changeProjectStatus({ project, status }) {
    if (loading.value || statusSaving.value) return;
    const previousStatus = project.status;
    project.status = status;
    statusSaving.value = true;
    try { await updateAdminProjectStatus(project.id, status); await loadProjects(); showNotice('Project status updated.'); }
    catch (error) { project.status = previousStatus; showNotice(error.response?.data?.message || 'Project status could not be updated.'); }
    finally { statusSaving.value = false; }
}

onMounted(async () => { loadProjects(); if (isAdmin.value) { loadOwners(); try { categories.value = (await getAdminProjectCategories()).data || []; } catch { categories.value = []; } } });
onBeforeUnmount(() => clearTimeout(noticeTimer));
</script>

<style src="../../../../css/pages/admin/projects/styles/AdminProjectsIndex.css"></style>
