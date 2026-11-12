<template>
    <main class="user-task-form-page" :aria-busy="loading || saving">
        <header class="user-task-form-header"><div><p class="user-task-form-eyebrow">My tasks</p><h1>{{ pageTitle }}</h1><p>{{ pageDescription }}</p></div><RouterLink class="user-task-back" :to="{ name: 'tasks.index' }">← Back to My Tasks</RouterLink></header>
        <div v-if="loadError" class="user-task-form-message is-error" role="alert">{{ loadError }} <button type="button" @click="load">Try again</button></div>
        <div v-else-if="loading" class="user-task-form-message" role="status"><span class="user-task-spinner"></span>Loading task…</div>
        <form v-else class="user-task-form-card" novalidate @submit.prevent="submit">
            <TaskForm
                v-model:title="form.title"
                v-model:description="form.description"
                v-model:status="form.status"
                v-model:priority="form.priority"
                v-model:due-date="form.due_date"
                :errors="errors"
                @clear-error="clearError"
            />
            <label class="user-task-category-field">Category<select v-model="form.category_id"><option value="">No category</option><option v-if="archivedCategory" :value="String(archivedCategory.id)" disabled>{{ archivedCategory.name }} (archived · existing)</option><option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option></select><small v-if="errors.category_id">{{ errors.category_id }}</small></label>
            <div v-if="formError" class="user-task-form-error" role="alert">{{ formError }}</div>
            <footer class="user-task-form-actions"><RouterLink class="user-task-cancel" :to="{ name: 'tasks.index' }">Cancel</RouterLink><button class="user-task-submit" type="submit" :disabled="saving">{{ saving ? 'Saving…' : editing ? 'Save changes' : 'Create task' }}</button></footer>
        </form>
    </main>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import TaskForm from './TaskForm.vue';
import { createUserTask, getUserTask, updateUserTask } from '../services/userTaskApi.js';
import { getUserCategories } from '../../../categories/user/services/userCategoryApi.js';

const props = defineProps({
    mode: { type: String, required: true, validator: value => ['create', 'edit'].includes(value) },
    taskId: { type: [String, Number], default: null },
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
});
const emit = defineEmits(['saved']);
const editing = computed(() => props.mode === 'edit');
const loading = ref(false), saving = ref(false), loadError = ref(''), formError = ref('');
const errors = reactive({}), categories = ref([]), archivedCategory = ref(null);
const form = reactive({ title: '', description: '', status: 'pending', priority: 'medium', due_date: '', category_id: '' });
function clearError(field) { delete errors[field]; formError.value = ''; }
async function load() {
    loading.value = true; loadError.value = '';
    try { categories.value = (await getUserCategories({ per_page: 50, sort: 'name', direction: 'asc' })).data || []; }
    catch { categories.value = []; }
    if (!editing.value) { loading.value = false; return; }
    if (!props.taskId) { loadError.value = 'This task could not be found.'; loading.value = false; return; }
    Object.assign(form, { title: '', description: '', status: 'pending', priority: 'medium', due_date: '' });
    Object.keys(errors).forEach(field => delete errors[field]);
    try {
        const response = await getUserTask(props.taskId);
        const task = response.task || response.data || response;
        if (!task.is_owned) { loadError.value = 'Only the task owner can edit this task.'; return; }
        archivedCategory.value = task.category?.status === 'archived' ? task.category : null;
        Object.assign(form, { title: task.title || '', description: task.description || '', status: task.status || 'pending', priority: task.priority || 'medium', due_date: task.due_date || '', category_id: task.category?.id ? String(task.category.id) : '' });
    } catch (error) {
        loadError.value = error.response?.status === 404 ? 'This task could not be found.' : 'Task details could not be loaded.';
    } finally { loading.value = false; }
}
async function submit() {
    formError.value = '';
    Object.keys(errors).forEach(field => delete errors[field]);
    if (!form.title.trim()) { errors.title = 'Enter a task title.'; return; }
    saving.value = true;
    try {
        const payload = { ...form, title: form.title.trim(), description: form.description.trim() || null, due_date: form.due_date || null, category_id: form.category_id || null };
        const response = editing.value
            ? await updateUserTask(props.taskId, payload)
            : await createUserTask(payload);
        emit('saved', response.task, props.mode);
    } catch (error) {
        const validation = error.response?.data?.errors || {};
        for (const [field, messages] of Object.entries(validation)) errors[field] = Array.isArray(messages) ? messages[0] : String(messages);
        formError.value = Object.keys(validation).length ? 'Review the highlighted fields and try again.' : 'The task could not be saved. Please try again.';
    } finally { saving.value = false; }
}
watch(() => [props.mode, props.taskId], load, { immediate: true });
</script>

<style src="../../../../../css/pages/app/tasks/form.css"></style>
