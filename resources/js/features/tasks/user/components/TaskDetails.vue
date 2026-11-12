<template>
        <header class="user-task-detail-header">
            <div class="user-task-detail-heading">
                <p class="user-tasks-eyebrow">Task #{{ task.id }}</p>
                <h1>{{ task.title }}</h1>
                <div class="user-task-detail-badges">
                    <TaskStatusBadge :status="task.status" />
                    <TaskPriorityBadge :priority="task.priority" />
                    <span v-if="task.due_date && overdue" class="user-task-badge is-overdue">Overdue</span>
                </div>
            </div>
            <div class="user-task-detail-actions">
                <RouterLink v-if="task.is_owned" class="user-tasks-primary-button" :to="{ name: 'tasks.edit', params: { taskId: task.id } }">Edit task</RouterLink>
                <button v-if="task.is_owned" class="user-tasks-danger-button" type="button" @click="$emit('delete')">Delete</button>
            </div>
        </header>
        <div class="user-task-detail-grid">
            <div class="user-task-detail-main">
                <section class="user-task-detail-panel">
                    <div class="user-task-detail-section-heading"><div><h2>Description</h2><p>Task notes and details</p></div></div>
                    <p class="user-task-full-description">{{ task.description || 'No description was added to this task.' }}</p>
                </section>
                <section class="user-task-detail-panel">
                    <div class="user-task-detail-section-heading"><div><h2>Task information</h2><p>Dates and current progress</p></div></div>
                    <dl class="user-task-detail-info">
                        <div><dt>Task ID</dt><dd>#{{ task.id }}</dd></div>
                        <div><dt>Status</dt><dd><TaskStatusBadge :status="task.status" /></dd></div>
                        <div><dt>Priority</dt><dd><TaskPriorityBadge :priority="task.priority" /></dd></div>
                        <div><dt>Category</dt><dd>{{ task.category?.name || '—' }}</dd></div>
                        <div><dt>Due date</dt><dd :class="{ 'is-overdue': overdue }">{{ formatDate(task.due_date) }}</dd></div>
                        <div><dt>Created</dt><dd>{{ formatDateTime(task.created_at) }}</dd></div>
                        <div><dt>Last updated</dt><dd>{{ formatDateTime(task.updated_at) }}</dd></div>
                    </dl>
                </section>
            </div>
            <aside class="user-task-detail-aside">
                <section class="user-task-detail-panel">
                    <div class="user-task-detail-section-heading"><div><h2>Progress</h2><p>Update the task status</p></div></div>
                    <label class="user-task-detail-status-label" :for="`detail-task-status-${task.id}`">Status</label>
                    <select :id="`detail-task-status-${task.id}`" :value="task.status" :disabled="statusSaving" @change="$emit('status-change', $event.target.value)"><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select>
                    <p class="user-task-detail-helper">{{ task.is_owned ? 'You own this task.' : 'This task was assigned to you. Editing and deletion are managed by its owner.' }}</p>
                </section>
            </aside>
        </div>
</template>

<script setup>
import TaskPriorityBadge from './TaskPriorityBadge.vue';
import TaskStatusBadge from './TaskStatusBadge.vue';

defineProps({
    task: { type: Object, required: true },
    overdue: { type: Boolean, default: false },
    statusSaving: { type: Boolean, default: false },
});
defineEmits(['delete', 'status-change']);

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(`${value}T00:00:00`);
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
}

function formatDateTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}
</script>
