<template>
    <main class="admin-project-detail" :aria-busy="detailLoading">
        <RouterLink class="admin-project-back" :to="{ name: 'admin.projects.index' }"><span aria-hidden="true">←</span> Back to projects</RouterLink>

        <div v-if="detailLoading" class="admin-project-detail-state" role="status"><span class="admin-project-detail-spinner" aria-hidden="true"></span> Loading project…</div>
        <div v-else-if="detailError" class="admin-project-detail-state is-error" role="alert">
            <div><strong>{{ detailNotFound ? 'Project not found' : 'Project details unavailable' }}</strong><p>{{ detailError }}</p></div>
            <button v-if="!detailNotFound" type="button" @click="loadProject(route.params.projectId)">Try again</button>
        </div>
        <template v-else-if="detail">
            <header class="admin-project-detail-header">
                <div class="admin-project-detail-heading"><p class="admin-project-detail-eyebrow">Project #{{ detail.id }}</p><h1>{{ detail.name || 'Untitled project' }}</h1><AdminProjectStatusBadge :status="detail.status" /></div>
                <div class="admin-project-detail-actions"><button class="admin-project-detail-primary" type="button" @click="editCurrentProject">Edit project</button><button class="admin-project-detail-danger" type="button" @click="openDelete(detail)">Delete</button></div>
            </header>
            <AdminProjectDetails :project="detail" />
            <section class="admin-project-tasks-panel">
                <header class="admin-project-tasks-heading"><div><h2>Project tasks</h2><p>Tasks associated with this project.</p></div><button type="button" @click="openTaskForm">Create task</button></header>
                <div class="admin-project-tasks-filters"><label>Status <select v-model="taskStatus" @change="resetProjectTaskFilters"><option value="">All statuses</option><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select></label><label>Priority <select v-model="taskPriority" @change="resetProjectTaskFilters"><option value="">All priorities</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></label><label>Category <select v-model="taskCategoryId" @change="resetProjectTaskFilters"><option value="">All categories</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label></div>
                <p v-if="taskError" class="admin-project-tasks-error" role="alert">{{ taskError }} <button type="button" @click="loadProjectTasks">Try again</button></p>
                <p v-else-if="taskLoading && !projectTasks.length" class="admin-project-tasks-state" role="status">Loading project tasks…</p>
                <p v-else-if="!projectTasks.length" class="admin-project-tasks-state">No tasks are associated with this project.</p>
                <div v-else class="admin-project-tasks-list"><article v-for="task in projectTasks" :key="task.id"><div class="admin-project-task-copy"><RouterLink :to="{ name: 'admin.tasks.show', params: { taskId: task.id } }">{{ task.title }}</RouterLink><span>{{ task.assigned_user?.name || 'Unassigned' }} · {{ task.due_date ? formatTaskDate(task.due_date) : 'No due date' }}</span></div><div class="admin-project-task-badges"><AdminTaskStatusBadge :status="task.status"/><AdminTaskPriorityBadge :priority="task.priority"/></div><div class="admin-project-task-actions"><RouterLink :to="{ name: 'admin.tasks.edit', params: { taskId: task.id } }">Edit</RouterLink><button type="button" @click="taskToDelete = task">Delete</button></div></article></div>
                <footer v-if="taskLastPage > 1" class="admin-project-tasks-pagination"><span>{{ projectTaskTotal }} tasks</span><div><button type="button" :disabled="taskPage <= 1 || taskLoading" @click="taskPage--; loadProjectTasks()">Previous</button><span>Page {{ taskPage }} of {{ taskLastPage }}</span><button type="button" :disabled="taskPage >= taskLastPage || taskLoading" @click="taskPage++; loadProjectTasks()">Next</button></div></footer>
            </section>
        </template>

        <div v-if="dialog.type === 'edit'" class="admin-projects-backdrop" @click.self="closeDialog">
            <AdminProjectForm :project="dialog.project" :owners="owners" :categories="categories" :owner-error="ownersError" :saving="dialog.saving" :error="dialog.error" :errors="dialog.errors" @cancel="closeDialog" @submit="saveFromDialog" />
        </div>
        <AdminProjectDeleteDialog v-if="dialog.type === 'delete'" :project="dialog.project" :saving="dialog.saving" :error="dialog.error" @close="closeDialog" @confirm="deleteFromDialog" />
        <div v-if="taskFormOpen" class="admin-projects-backdrop" @click.self="taskFormOpen = false"><section class="admin-project-task-form" role="dialog" aria-modal="true"><h2>Create project task</h2><p v-if="taskFormError" class="admin-project-tasks-error" role="alert">{{ taskFormError }}</p><form @submit.prevent="createProjectTask"><AdminTaskFormFields v-model:title="taskForm.title" v-model:description="taskForm.description" v-model:status="taskForm.status" v-model:priority="taskForm.priority" v-model:due-date="taskForm.due_date" v-model:category-id="taskForm.category_id" :categories="categories" :errors="taskFieldErrors" @clear-error="clearTaskFieldError"><label>Owner<select v-model="taskForm.owner_id" required><option value="">Select active user</option><option v-for="person in taskAssignees" :key="person.id" :value="String(person.id)">{{ person.name }} · {{ person.email }}</option></select><small v-if="taskFieldErrors.owner_id">{{ taskFieldErrors.owner_id[0] }}</small></label><label>Assigned user<select v-model="taskForm.assigned_user_id"><option value="">Unassigned</option><option v-for="person in taskAssignees" :key="person.id" :value="String(person.id)">{{ person.name }} · {{ person.email }}</option></select><small v-if="taskFieldErrors.assigned_user_id">{{ taskFieldErrors.assigned_user_id[0] }}</small></label></AdminTaskFormFields><div class="admin-project-task-form-actions"><button type="button" :disabled="taskSaving" @click="taskFormOpen = false">Cancel</button><button type="submit" :disabled="taskSaving">{{ taskSaving ? 'Creating…' : 'Create task' }}</button></div></form></section></div>
        <AdminTaskDeleteDialog v-if="taskToDelete" :task="taskToDelete" :saving="taskDeleteSaving" :error="taskDeleteError" @close="taskToDelete = null" @confirm="deleteProjectTask" />
        <div v-if="notice" class="admin-project-detail-notice" role="status">{{ notice }}</div>
    </main>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminProjectDeleteDialog from '../../../features/projects/admin/components/AdminProjectDeleteDialog.vue';
import AdminProjectDetails from '../../../features/projects/admin/components/AdminProjectDetails.vue';
import AdminProjectForm from '../../../features/projects/admin/components/AdminProjectForm.vue';
import AdminProjectStatusBadge from '../../../features/projects/admin/components/AdminProjectStatusBadge.vue';
import { useAdminProjects } from '../../../features/projects/admin/composables/useAdminProjects.js';
import { createAdminProjectTask, getAdminProjectTasks, getAdminProjectCategories } from '../../../features/projects/admin/services/adminProjectApi.js';
import { deleteAdminTask, getTaskAssignees } from '../../../features/tasks/admin/services/adminTaskApi.js';
import AdminTaskFormFields from '../../../features/tasks/admin/components/AdminTaskFormFields.vue';
import AdminTaskDeleteDialog from '../../../features/tasks/admin/components/AdminTaskDeleteDialog.vue';
import AdminTaskStatusBadge from '../../../features/tasks/admin/components/AdminTaskStatusBadge.vue';
import AdminTaskPriorityBadge from '../../../features/tasks/admin/components/AdminTaskPriorityBadge.vue';

const route = useRoute();
const router = useRouter();
const {
    detail, detailLoading, detailError, detailNotFound, owners, ownersError, ownersLoading,
    dialog, loadProject, loadOwners, openEdit, openDelete, closeDialog, saveProject, confirmDelete,
} = useAdminProjects();
const notice = ref('');
const projectTasks = ref([]), taskLoading = ref(false), taskError = ref(''), taskStatus = ref(''), taskPriority = ref(''), taskCategoryId = ref(''), taskPage = ref(1), taskLastPage = ref(1), projectTaskTotal = ref(0), categories = ref([]);
const taskFormOpen = ref(false), taskSaving = ref(false), taskFormError = ref(''), taskFieldErrors = ref({}), taskAssignees = ref([]), taskToDelete = ref(null), taskDeleteSaving = ref(false), taskDeleteError = ref('');
const taskForm = reactive({ title: '', description: '', status: 'pending', priority: 'medium', due_date: '', owner_id: '', assigned_user_id: '', category_id: '' });
let noticeTimer;

async function saveFromDialog(payload) {
    if (await saveProject(payload, { refreshList: false })) showNotice('Project saved.');
}

function editCurrentProject() {
    if (!detail.value) return;
    openEdit(detail.value);
    if (!owners.value.length && !ownersLoading.value) loadOwners();
}

async function deleteFromDialog() {
    if (await confirmDelete({ refreshList: false })) await router.push({ name: 'admin.projects.index' });
}

async function loadProjectTasks() {
    if (!route.params.projectId) return;
    taskLoading.value = true; taskError.value = '';
    try { const response = await getAdminProjectTasks(route.params.projectId, { page: taskPage.value, per_page: 10, ...(taskStatus.value ? { status: taskStatus.value } : {}), ...(taskPriority.value ? { priority: taskPriority.value } : {}), ...(taskCategoryId.value ? { category_id: taskCategoryId.value } : {}) }); projectTasks.value = response.data || []; taskLastPage.value = Number(response.meta?.last_page || 1); projectTaskTotal.value = Number(response.meta?.total || 0); }
    catch { taskError.value = 'Project tasks could not be loaded.'; }
    finally { taskLoading.value = false; }
}
function resetProjectTaskFilters() { taskPage.value = 1; loadProjectTasks(); }
async function openTaskForm() {
    taskFormOpen.value = true; taskFormError.value = ''; taskFieldErrors.value = {};
    Object.assign(taskForm, { title: '', description: '', status: 'pending', priority: 'medium', due_date: '', owner_id: detail.value?.owner?.id ? String(detail.value.owner.id) : '', assigned_user_id: '', category_id: '' });
    try { const [response, categoryResponse] = await Promise.all([getTaskAssignees(), getAdminProjectCategories()]); taskAssignees.value = response.data || []; categories.value = categoryResponse.data || []; }
    catch { taskFormError.value = 'Eligible users could not be loaded.'; }
}
async function createProjectTask() {
    if (taskSaving.value) return; taskSaving.value = true; taskFormError.value = ''; taskFieldErrors.value = {};
    try { await createAdminProjectTask(route.params.projectId, { ...taskForm, owner_id: Number(taskForm.owner_id), assigned_user_id: taskForm.assigned_user_id ? Number(taskForm.assigned_user_id) : null, due_date: taskForm.due_date || null }); taskFormOpen.value = false; taskPage.value = 1; await loadProjectTasks(); }
    catch (e) { taskFieldErrors.value = e.response?.data?.errors || {}; taskFormError.value = e.response?.data?.message || 'The task could not be created.'; }
    finally { taskSaving.value = false; }
}
function clearTaskFieldError(field) { const next = { ...taskFieldErrors.value }; delete next[field]; taskFieldErrors.value = next; }
async function deleteProjectTask() { if (!taskToDelete.value || taskDeleteSaving.value) return; taskDeleteSaving.value = true; taskDeleteError.value = ''; try { await deleteAdminTask(taskToDelete.value.id); taskToDelete.value = null; taskPage.value = 1; await loadProjectTasks(); } catch { taskDeleteError.value = 'Task could not be deleted.'; } finally { taskDeleteSaving.value = false; } }
function formatTaskDate(value) { const date = new Date(`${value}T00:00:00`); return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date); }

function showNotice(message) {
    notice.value = message;
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => { notice.value = ''; }, 3200);
}

watch(() => route.params.projectId, projectId => {
    if (projectId) { loadProject(projectId); loadProjectTasks(); getAdminProjectCategories().then(response => { categories.value = response.data || []; }).catch(() => { categories.value = []; }); }
}, { immediate: true });
onBeforeUnmount(() => clearTimeout(noticeTimer));
</script>

<style src="../../../../css/pages/admin/projects/styles/AdminProjectsIndex.css"></style>
<style src="../../../../css/pages/admin/projects/styles/AdminProjectsShow.css"></style>
