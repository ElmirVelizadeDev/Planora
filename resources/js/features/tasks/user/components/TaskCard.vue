<template>
    <article class="user-task-card" :class="{ 'is-completed': task.status === 'completed' }">
        <div class="user-task-card-main">
            <div class="user-task-title-row">
                <RouterLink class="user-task-title" :to="{ name: 'tasks.show', params: { taskId: task.id } }">{{ task.title }}</RouterLink>
                <TaskStatusBadge :status="task.status" />
            </div>
            <p v-if="task.description" class="user-task-card-description">{{ task.description }}</p>
            <div class="user-task-meta">
                <TaskPriorityBadge :priority="task.priority" />
                <span v-if="task.due_date" class="user-task-due" :class="{ 'is-overdue': isOverdue(task) }">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
                    {{ isOverdue(task) ? 'Overdue · ' : 'Due ' }}{{ formatDate(task.due_date) }}
                </span>
                <span v-else class="user-task-due is-muted">No due date</span>
                <span class="user-task-owner-label">{{ task.is_owned ? 'My task' : 'Assigned to me' }}</span>
            </div>
        </div>
        <div class="user-task-actions">
            <label class="sr-only" :for="`status-${task.id}`">Change status for {{ task.title }}</label>
            <select :id="`status-${task.id}`" :value="task.status" :disabled="pending" aria-label="Task status" @change="$emit('status-change', $event.target.value)">
                <option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option>
            </select>
            <RouterLink class="user-task-action-link" :to="{ name: 'tasks.show', params: { taskId: task.id } }">View</RouterLink>
            <RouterLink v-if="task.is_owned" class="user-task-action-link" :to="{ name: 'tasks.edit', params: { taskId: task.id } }">Edit</RouterLink>
            <button v-if="task.is_owned" class="user-task-action-link is-danger" type="button" @click="$emit('delete', task)">Delete</button>
        </div>
    </article>
</template>

<script setup>
import TaskPriorityBadge from './TaskPriorityBadge.vue';
import TaskStatusBadge from './TaskStatusBadge.vue';

defineProps({ task: { type: Object, required: true }, pending: { type: Boolean, default: false } });
defineEmits(['status-change', 'delete']);

function formatDate(value) {
    const date = new Date(`${value}T00:00:00`);
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
}

function isOverdue(task) {
    return task.status !== 'completed' && task.due_date && new Date(`${task.due_date}T00:00:00`) < new Date(new Date().toDateString());
}
</script>
