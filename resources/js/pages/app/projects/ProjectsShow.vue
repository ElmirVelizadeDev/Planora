<template>
    <main class="user-project-detail" :aria-busy="loading">
        <RouterLink class="user-project-back" :to="{ name: 'projects.index' }">← Back to projects</RouterLink>
        <div v-if="loading" class="user-project-detail-state" role="status">Loading project…</div>
        <div v-else-if="error" class="user-project-detail-state is-error" role="alert"><strong>{{ notFound ? 'Project not found' : 'Project unavailable' }}</strong><p>{{ error }}</p><button v-if="!notFound" type="button" @click="load">Try again</button></div>
        <template v-else-if="project">
            <header class="user-project-detail-header"><div><p class="user-projects-eyebrow">Project #{{ project.id }}</p><div class="user-project-detail-title"><h1>{{ project.name }}</h1><span class="user-project-status" :class="`is-${project.status}`">{{ statusLabel(project.status) }}</span></div><p>{{ project.description || 'No project description provided.' }}</p></div></header>
            <section class="user-project-info"><div><span>Project owner</span><strong>{{ project.owner?.name || '—' }}</strong></div><div><span>Start date</span><strong>{{ formatDate(project.start_date) }}</strong></div><div><span>Due date</span><strong>{{ formatDate(project.due_date) }}</strong></div><div><span>Created</span><strong>{{ formatDate(project.created_at) }}</strong></div></section>
            <section class="user-project-tasks"><div class="user-project-section-heading"><div><h2>Tasks you can access</h2><p>Tasks assigned to you or owned by you within this project.</p></div><label>Status <select v-model="status" @change="filterProjectTasks"><option value="">All statuses</option><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select></label></div>
                <p v-if="tasksError" class="user-projects-error" role="alert">{{ tasksError }} <button type="button" @click="loadTasks">Try again</button></p><div v-else-if="tasksLoading && !tasks.length" class="user-project-detail-state" role="status">Loading tasks…</div><div v-else-if="!tasks.length" class="user-project-detail-state">No tasks you can access are attached to this project.</div><TaskList v-else :tasks="tasks" :loading="tasksLoading" :pending-statuses="pendingStatuses" @status-change="changeTaskStatus" @delete="taskToDelete = $event" />
                <div v-if="taskLastPage > 1" class="user-projects-pagination"><span>{{ taskTotal }} tasks</span><div><button type="button" :disabled="taskPage <= 1 || tasksLoading" @click="taskPage--; loadTasks()">Previous</button><span>Page {{ taskPage }} of {{ taskLastPage }}</span><button type="button" :disabled="taskPage >= taskLastPage || tasksLoading" @click="taskPage++; loadTasks()">Next</button></div></div>
            </section>
        </template>
        <div v-if="taskToDelete" class="user-task-modal-backdrop" @click.self="taskToDelete = null" @keydown.esc="taskToDelete = null"><section class="user-task-confirm" role="dialog" aria-modal="true" aria-labelledby="project-delete-task-title"><p class="user-task-eyebrow">Delete task</p><h2 id="project-delete-task-title">Delete “{{ taskToDelete.title }}”?</h2><p>This task will be moved to deleted records.</p><div class="user-task-confirm-actions"><button class="user-tasks-secondary-button" type="button" :disabled="deleting" @click="taskToDelete = null">Cancel</button><button class="user-tasks-delete-button" type="button" :disabled="deleting" @click="deleteTask">{{ deleting ? 'Deleting…' : 'Delete task' }}</button></div></section></div>
    </main>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import TaskList from '../../../features/tasks/user/components/TaskList.vue';
import { getUserProject, getUserProjectTasks } from '../../../features/projects/user/services/userProjectApi.js';
import { deleteUserTask, updateUserTaskStatus } from '../../../features/tasks/user/services/userTaskApi.js';
const route = useRoute(), project = ref(null), loading = ref(false), error = ref(''), notFound = ref(false);
const tasks = ref([]), tasksLoading = ref(false), tasksError = ref(''), status = ref(''), taskPage = ref(1), taskLastPage = ref(1), taskTotal = ref(0), pendingStatuses = ref({}), taskToDelete = ref(null), deleting = ref(false);
let projectRequestSequence = 0, taskRequestSequence = 0;
async function load() { const sequence = ++projectRequestSequence; const projectId = route.params.projectId; loading.value = true; error.value = ''; notFound.value = false; project.value = null; tasks.value = []; tasksError.value = ''; try { const response = await getUserProject(projectId); if (sequence !== projectRequestSequence) return; project.value = response.project || response.data; await loadTasks(); } catch (e) { if (sequence !== projectRequestSequence) return; notFound.value = e.response?.status === 404; error.value = notFound.value ? 'This project is not available to your account.' : 'Project details could not be loaded.'; } finally { if (sequence === projectRequestSequence) loading.value = false; } }
async function loadTasks() { const sequence = ++taskRequestSequence, projectId = route.params.projectId; tasksLoading.value = true; tasksError.value = ''; try { const response = await getUserProjectTasks(projectId, { page: taskPage.value, per_page: 10, ...(status.value ? { status: status.value } : {}) }); if (sequence !== taskRequestSequence) return; tasks.value = response.data || []; taskLastPage.value = Number(response.meta?.last_page || 1); taskTotal.value = Number(response.meta?.total || 0); } catch { if (sequence === taskRequestSequence) tasksError.value = 'Project tasks could not be loaded.'; } finally { if (sequence === taskRequestSequence) tasksLoading.value = false; } }
async function changeTaskStatus(task, nextStatus) { pendingStatuses.value = { ...pendingStatuses.value, [task.id]: true }; try { await updateUserTaskStatus(task.id, nextStatus); await loadTasks(); } catch { tasksError.value = 'Task status could not be updated.'; } finally { const next = { ...pendingStatuses.value }; delete next[task.id]; pendingStatuses.value = next; } }
async function deleteTask() { if (!taskToDelete.value || deleting.value) return; deleting.value = true; try { await deleteUserTask(taskToDelete.value.id); taskToDelete.value = null; taskPage.value = 1; await loadTasks(); } catch { tasksError.value = 'Task could not be deleted.'; } finally { deleting.value = false; } }
function filterProjectTasks() { taskPage.value = 1; loadTasks(); }
function statusLabel(value) { return ({ planned: 'Planned', active: 'Active', on_hold: 'On hold', completed: 'Completed' })[value] || 'Unknown'; }
function formatDate(value) { if (!value) return '—'; const date = new Date(value); return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date); }
watch(() => route.params.projectId, projectId => { if (projectId) { taskPage.value = 1; load(); } }, { immediate: true });
</script>

<style src="../../../../css/pages/app/projects/styles/ProjectsIndex.css"></style>
<style src="../../../../css/pages/app/projects/styles/ProjectsShow.css"></style>
