<template>
    <section class="admin-projects-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-project-form-title" @keydown.esc="$emit('cancel')">
        <p class="admin-projects-dialog-eyebrow">{{ project ? 'Project settings' : 'New project' }}</p>
        <h2 id="admin-project-form-title">{{ project ? 'Edit project' : 'Create project' }}</h2>
        <div v-if="error" class="admin-projects-dialog-error" role="alert">{{ error }}</div>
        <form class="admin-projects-form" @submit.prevent="submit">
            <label>Project name <span>*</span><input v-model.trim="form.name" maxlength="180" required :aria-invalid="Boolean(fieldError('name'))"><small v-if="fieldError('name')">{{ fieldError('name') }}</small></label>
            <label>Description<textarea v-model.trim="form.description" rows="3" maxlength="5000"></textarea><small v-if="fieldError('description')">{{ fieldError('description') }}</small></label>
            <label>Owner<select v-model="form.owner_id" required><option value="" disabled>Select an active user</option><option v-for="owner in owners" :key="owner.id" :value="String(owner.id)">{{ owner.name }} · {{ owner.email }}</option></select><small v-if="ownerError">{{ ownerError }}</small><small v-if="fieldError('owner_id')">{{ fieldError('owner_id') }}</small></label>
            <label>Status<select v-model="form.status"><option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select><small v-if="fieldError('status')">{{ fieldError('status') }}</small></label>
            <div class="admin-projects-date-fields"><label>Start date<input v-model="form.start_date" type="date"><small v-if="fieldError('start_date')">{{ fieldError('start_date') }}</small></label><label>Due date<input v-model="form.due_date" type="date"><small v-if="fieldError('due_date')">{{ fieldError('due_date') }}</small></label></div>
            <div class="admin-projects-dialog-actions"><button class="admin-projects-secondary-button" type="button" :disabled="saving" @click="$emit('cancel')">Cancel</button><button class="admin-projects-primary-button" type="submit" :disabled="saving">{{ saving ? 'Saving…' : project ? 'Save changes' : 'Create project' }}</button></div>
        </form>
    </section>
</template>

<script setup>
import { reactive, watch } from 'vue';
import { ADMIN_PROJECT_STATUSES } from '../composables/useAdminProjects.js';

const props = defineProps({ project: { type: Object, default: null }, owners: { type: Array, default: () => [] }, ownerError: { type: String, default: '' }, saving: { type: Boolean, default: false }, error: { type: String, default: '' }, errors: { type: Object, default: () => ({}) } });
const emit = defineEmits(['submit', 'cancel']);
const statuses = ADMIN_PROJECT_STATUSES;
const form = reactive({ name: '', description: '', owner_id: '', status: 'planned', start_date: '', due_date: '' });

watch(() => props.project, project => {
    Object.assign(form, {
        name: project?.name || '', description: project?.description || '',
        owner_id: project?.owner?.id ? String(project.owner.id) : '',
        status: project?.status || 'planned', start_date: project?.start_date || '', due_date: project?.due_date || '',
    });
}, { immediate: true });

function fieldError(field) {
    const messages = props.errors[field];
    return Array.isArray(messages) ? messages[0] || '' : typeof messages === 'string' ? messages : '';
}
function submit() {
    emit('submit', {
        ...form,
        owner_id: form.owner_id || null,
        start_date: form.start_date || null,
        due_date: form.due_date || null,
    });
}
</script>
