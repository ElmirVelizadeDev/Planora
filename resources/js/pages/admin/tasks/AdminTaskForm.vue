<template>
    <main class="admin-task-form-page" :aria-busy="loading || saving">
        <header class="admin-task-form-header"><div><p class="admin-task-form-eyebrow">Task management</p><h1>{{ editing ? 'Edit task' : 'Create task' }}</h1><p>{{ editing ? 'Update task details and assignment.' : 'Add a task to the platform and assign ownership.' }}</p></div><RouterLink :to="backRoute" class="admin-task-form-back">← Back to tasks</RouterLink></header>
        <div v-if="loadError" class="admin-task-form-alert" role="alert">{{ loadError }} <button type="button" @click="load">Try again</button></div>
        <div v-else-if="loading" class="admin-task-form-loading" role="status"><span class="admin-task-form-spinner"></span>Loading task information…</div>
        <form v-else class="admin-task-form-card" novalidate @submit.prevent="submit">
            <div class="admin-task-form-section-heading"><div><h2>Task details</h2><p>Provide a clear title, status, priority and due date.</p></div></div>
            <AdminTaskFormFields
                v-model:title="form.title"
                v-model:description="form.description"
                v-model:status="form.status"
                v-model:priority="form.priority"
                v-model:due-date="form.due_date"
                :errors="errors"
                @clear-error="clearError"
            >
                <AdminTaskAssignment v-model:owner-id="form.owner_id" v-model:assigned-user-id="form.assigned_user_id" :users="users" :errors="errors" />
            </AdminTaskFormFields>
            <p v-if="formError" class="admin-task-form-error" role="alert">{{ formError }}</p>
            <footer class="admin-task-form-actions"><RouterLink :to="backRoute" class="admin-task-form-cancel">Cancel</RouterLink><button type="submit" class="admin-task-form-submit" :disabled="saving || !users.length">{{ saving ? 'Saving…' : editing ? 'Save changes' : 'Create task' }}</button></footer>
        </form>
    </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminTaskAssignment from '../../../features/tasks/admin/components/AdminTaskAssignment.vue';
import AdminTaskFormFields from '../../../features/tasks/admin/components/AdminTaskFormFields.vue';
import { createAdminTask, getAdminTask, getTaskAssignees, updateAdminTask } from '../../../features/tasks/admin/services/adminTaskApi.js';

const route = useRoute(), router = useRouter();
const editing = computed(() => Boolean(route.params.taskId));
const backRoute = { name: 'admin.tasks.index' };
const loading = ref(false), saving = ref(false), loadError = ref(''), formError = ref(''), users = ref([]), errors = reactive({});
const form = reactive({ title: '', description: '', status: 'pending', priority: 'medium', due_date: '', owner_id: '', assigned_user_id: '' });
function clearError(key) { delete errors[key]; formError.value = ''; }
async function load() {
    loading.value = true; loadError.value = '';
    try {
        const [assigneeData, taskResponse] = await Promise.all([
            getTaskAssignees(),
            editing.value ? getAdminTask(route.params.taskId) : Promise.resolve(null),
        ]);
        users.value = assigneeData.data || [];
        if (editing.value) {
            const task = taskResponse.task || taskResponse.data || taskResponse;
            Object.assign(form, { title: task.title || '', description: task.description || '', status: task.status || 'pending', priority: task.priority || 'medium', due_date: task.due_date || '', owner_id: task.owner?.id ? String(task.owner.id) : '', assigned_user_id: task.assigned_user?.id ? String(task.assigned_user.id) : '' });
        }
    } catch (error) { loadError.value = error.response?.status === 404 ? 'The task or task management endpoint could not be found.' : 'Task information could not be loaded.'; }
    finally { loading.value = false; }
}
async function submit() {
    saving.value = true; formError.value = ''; Object.keys(errors).forEach(key => delete errors[key]);
    const payload = { ...form, description: form.description || null, due_date: form.due_date || null, owner_id: Number(form.owner_id), assigned_user_id: form.assigned_user_id ? Number(form.assigned_user_id) : null };
    try {
        const response = editing.value ? await updateAdminTask(route.params.taskId, payload) : await createAdminTask(payload);
        const task = response.task;
        await router.push({ name: 'admin.tasks.show', params: { taskId: task.id } });
    } catch (error) {
        const validation = error.response?.data?.errors || {};
        for (const [field, messages] of Object.entries(validation)) errors[field] = Array.isArray(messages) ? messages[0] : String(messages);
        formError.value = Object.keys(validation).length ? 'Check the highlighted fields and try again.' : 'The task could not be saved. Please try again.';
    } finally { saving.value = false; }
}
onMounted(load);
</script>

<style src="../../../../css/pages/admin/tasks/styles/AdminTaskForm.css"></style>
