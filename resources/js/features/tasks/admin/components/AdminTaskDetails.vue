<template>
    <header class="admin-task-header">
        <div class="admin-task-heading">
            <p class="admin-task-eyebrow">Task #{{ task.id }}</p>
            <h1>{{ task.title }}</h1>
            <div class="admin-task-heading-badges">
                <AdminTaskStatusBadge :status="task.status" context="detail" />
                <AdminTaskPriorityBadge :priority="task.priority" context="detail" suffix=" priority" />
            </div>
        </div>
        <div v-if="isAdmin || canManageStatus" class="admin-task-header-actions">
            <select v-if="canManageStatus" :value="task.status" :disabled="savingStatus" :aria-label="`Change status for ${task.title}`" @change="$emit('status-change', $event.target.value)"><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select>
            <span v-if="statusError" role="alert">{{ statusError }}</span>
            <RouterLink v-if="isAdmin" class="admin-task-primary-button" :to="{ name: 'admin.tasks.edit', params: { taskId: task.id } }">Edit task</RouterLink>
            <button v-if="isAdmin" class="admin-task-danger-button" type="button" @click="$emit('delete')">Delete</button>
        </div>
    </header>
    <div class="admin-task-grid">
        <div class="admin-task-main-column">
            <section class="admin-task-panel" aria-labelledby="task-description-title">
                <div class="admin-task-section-heading"><div><h2 id="task-description-title">Description</h2><p>Task overview and instructions</p></div></div>
                <p class="admin-task-description">{{ task.description || 'No description was provided for this task.' }}</p>
            </section>
            <section class="admin-task-panel" aria-labelledby="task-information-title">
                <div class="admin-task-section-heading"><div><h2 id="task-information-title">Task information</h2><p>Current task details</p></div></div>
                <dl class="admin-task-information">
                    <div><dt>Task ID</dt><dd>#{{ task.id }}</dd></div>
                    <div><dt>Owner</dt><dd>{{ task.owner?.name || '—' }}</dd></div>
                    <div><dt>Category</dt><dd>{{ task.category?.name || '—' }}</dd></div>
                    <div><dt>Status</dt><dd><AdminTaskStatusBadge :status="task.status" context="detail" /></dd></div>
                    <div><dt>Priority</dt><dd><AdminTaskPriorityBadge :priority="task.priority" context="detail" /></dd></div>
                    <div><dt>Due date</dt><dd :class="{ 'is-overdue': isOverdue }">{{ formatDate(task.due_date) }}</dd></div>
                    <div><dt>Created</dt><dd>{{ formatDate(task.created_at) }}</dd></div>
                    <div><dt>Last updated</dt><dd>{{ formatDate(task.updated_at) }}</dd></div>
                </dl>
            </section>
        </div>
        <aside class="admin-task-side-column"><AdminTaskPeople :user="task.assigned_user" /></aside>
    </div>
</template>

<script setup>
import AdminTaskPeople from './AdminTaskPeople.vue';
import AdminTaskPriorityBadge from './AdminTaskPriorityBadge.vue';
import AdminTaskStatusBadge from './AdminTaskStatusBadge.vue';

defineProps({
    task: { type: Object, required: true },
    isOverdue: { type: Boolean, default: false },
    isAdmin: { type: Boolean, default: true }, canManageStatus: { type: Boolean, default: false }, savingStatus: { type: Boolean, default: false }, statusError: { type: String, default: '' },
});
defineEmits(['delete', 'status-change']);

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
}
</script>
