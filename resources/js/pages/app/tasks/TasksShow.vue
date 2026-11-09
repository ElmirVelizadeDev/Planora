<template>
    <main class="user-task-detail" :aria-busy="loading">
        <RouterLink class="user-task-back" :to="{ name: 'tasks.index' }"><span aria-hidden="true">←</span> Back to My Tasks</RouterLink>
        <div v-if="loadError" class="user-task-detail-message is-error" role="alert"><span>{{ loadError }}</span><button type="button" @click="loadTask">Try again</button></div>
        <div v-else-if="loading" class="user-task-detail-message" role="status"><span class="user-task-spinner"></span>Loading task…</div>
        <template v-else-if="task">
            <p v-if="notice" class="user-task-detail-notice" role="status">{{ notice }}</p>
            <div v-if="actionError" class="user-task-detail-message is-error" role="alert">{{ actionError }}</div>
            <TaskDetails :task="task" :overdue="isOverdue" :status-saving="statusSaving" @delete="showDelete = true" @status-change="saveStatus" />
        </template>

        <div v-if="showDelete" class="user-task-modal-backdrop" @click.self="showDelete = false" @keydown.esc="showDelete = false">
            <section class="user-task-confirm" role="dialog" aria-modal="true" aria-labelledby="detail-delete-title">
                <p class="user-tasks-eyebrow">Delete task</p><h2 id="detail-delete-title">Delete “{{ task?.title }}”?</h2><p>This task will be moved to deleted records.</p>
                <div class="user-task-confirm-actions"><button class="user-tasks-secondary-button" type="button" @click="showDelete = false">Cancel</button><button class="user-tasks-delete-button" type="button" :disabled="deleting" @click="deleteTask">{{ deleting ? 'Deleting…' : 'Delete task' }}</button></div>
            </section>
        </div>
    </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import TaskDetails from '../../../features/tasks/user/components/TaskDetails.vue';
import { deleteUserTask, getUserTask, updateUserTaskStatus } from '../../../features/tasks/user/services/userTaskApi.js';

const route = useRoute(), router = useRouter();
const task = ref(null), loading = ref(false), deleting = ref(false), statusSaving = ref(false), loadError = ref(''), actionError = ref(''), notice = ref(''), showDelete = ref(false);
const isOverdue = computed(() => task.value?.status !== 'completed' && task.value?.due_date && new Date(`${task.value.due_date}T00:00:00`) < new Date(new Date().toDateString()));

async function loadTask() {
    loading.value = true;
    loadError.value = '';
    try {
        const response = await getUserTask(route.params.taskId);
        task.value = response.task || response.data || response;
    } catch (requestError) {
        loadError.value = requestError.response?.status === 404 ? 'This task could not be found.' : 'Task details could not be loaded. Please try again.';
    } finally {
        loading.value = false;
    }
}

async function saveStatus(nextStatus) {
    if (statusSaving.value) return;
    const previous = task.value.status;
    statusSaving.value = true;
    actionError.value = '';
    try {
        const response = await updateUserTaskStatus(task.value.id, nextStatus);
        task.value = response.task;
        notice.value = 'Task status updated.';
    } catch {
        actionError.value = 'The task status could not be updated. Please try again.';
        task.value.status = previous;
    } finally {
        statusSaving.value = false;
    }
}

async function deleteTask() {
    if (deleting.value || !task.value?.is_owned) return;
    deleting.value = true;
    actionError.value = '';
    try {
        await deleteUserTask(task.value.id);
        await router.push({ name: 'tasks.index', query: { notice: 'deleted' } });
    } catch {
        actionError.value = 'The task could not be deleted. Please try again.';
        showDelete.value = false;
    } finally {
        deleting.value = false;
    }
}

onMounted(async () => {
    await loadTask();
    if (route.query.notice === 'created') notice.value = 'Task created.';
    else if (route.query.notice === 'updated') notice.value = 'Task updated.';
    if (route.query.notice) router.replace({ name: 'tasks.show', params: { taskId: route.params.taskId } });
});
</script>

<style src="../../../../css/pages/app/tasks/index.css"></style>
<style src="../../../../css/pages/app/tasks/show.css"></style>
