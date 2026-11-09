<template>
    <main class="admin-task-detail" :aria-busy="loading">
        <RouterLink class="admin-task-back" :to="{ name: 'admin.tasks.index' }"><span aria-hidden="true">←</span> Back to tasks</RouterLink>
        <div v-if="error" class="admin-task-feedback is-error" role="alert"><span>{{ error }}</span><button type="button" @click="loadTask">Try again</button></div>
        <div v-else-if="loading" class="admin-task-loading" role="status"><span class="admin-task-spinner" aria-hidden="true"></span>Loading task…</div>
        <AdminTaskDetails v-else-if="task" :task="task" :is-overdue="isOverdue" @delete="deleteTask" />
        <AdminTaskDeleteDialog
            v-if="deleteDialogOpen && task"
            :task="task"
            :saving="deleteSaving"
            :error="deleteError"
            @close="closeDeleteDialog"
            @confirm="confirmDelete"
            @clear-error="deleteError = ''"
        />
    </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminTaskDeleteDialog from '../../../features/tasks/admin/components/AdminTaskDeleteDialog.vue';
import AdminTaskDetails from '../../../features/tasks/admin/components/AdminTaskDetails.vue';
import { deleteAdminTask, getAdminTask } from '../../../features/tasks/admin/services/adminTaskApi.js';

const route = useRoute(), router = useRouter(), task = ref(null), loading = ref(false), error = ref('');
const deleteDialogOpen = ref(false), deleteSaving = ref(false), deleteError = ref('');
const isOverdue = computed(() => task.value?.status !== 'completed' && task.value?.due_date && new Date(task.value.due_date) < new Date(new Date().toDateString()));

async function loadTask() {
    loading.value = true;
    error.value = '';
    try {
        const data = await getAdminTask(route.params.taskId);
        task.value = data.task || data.data || data;
    } catch (requestError) {
        error.value = requestError.response?.status === 404 ? 'This task could not be found.' : 'Task details could not be loaded. Please try again.';
    } finally {
        loading.value = false;
    }
}

function deleteTask() {
    deleteError.value = '';
    deleteDialogOpen.value = true;
}

function closeDeleteDialog() {
    if (deleteSaving.value) return;
    deleteDialogOpen.value = false;
    deleteError.value = '';
}

async function confirmDelete() {
    if (!task.value || deleteSaving.value) return;
    deleteSaving.value = true;
    deleteError.value = '';
    try {
        await deleteAdminTask(task.value.id);
        await router.push({ name: 'admin.tasks.index' });
    } catch {
        deleteError.value = 'The task could not be deleted. Please try again.';
    } finally {
        deleteSaving.value = false;
    }
}

onMounted(loadTask);
</script>

<style src="../../../../css/pages/admin/tasks/styles/AdminTasksShow.css"></style>
