<template>
    <div class="admin-tasks-table-wrap" :class="{ 'is-loading': loading }">
            <table class="admin-tasks-table">
                <thead><tr><th scope="col">Task</th><th scope="col">Assigned user</th><th scope="col">Category</th><th scope="col">Priority</th><th scope="col">Status</th><th scope="col">Due date</th><th scope="col">Created</th><th scope="col" class="admin-tasks-actions-heading">Actions</th></tr></thead>
                <tbody>
                    <tr v-for="task in tasks" :key="task.id">
                        <td data-label="Task"><RouterLink class="admin-tasks-task-copy" :to="{ name: 'admin.tasks.show', params: { taskId: task.id } }"><strong>{{ task.title }}</strong><span>{{ task.description || `Task #${task.id}` }}</span></RouterLink></td>
                        <td data-label="Assigned user"><div class="admin-tasks-identity"><UserAvatar class="admin-tasks-avatar user-avatar--plain" :name="task.assigned_user?.name || 'Unassigned'" :image-src="task.assigned_user?.avatar || ''" /><span class="admin-tasks-user-copy"><strong>{{ task.assigned_user?.name || 'Unassigned' }}</strong><span>{{ task.assigned_user?.email || '—' }}</span></span></div></td>
                        <td data-label="Category">{{ task.category?.name || '—' }}</td>
                        <td data-label="Priority"><AdminTaskPriorityBadge :priority="task.priority" /></td>
                        <td data-label="Status"><AdminStatusSelect v-if="canManageStatus && !deletedView" :model-value="task.status" :options="ADMIN_TASK_STATUSES" :disabled="loading" :aria-label="`Change status for ${task.title}`" @update:model-value="$emit('status-change', { task, status: $event })" /><AdminTaskStatusBadge v-else :status="task.status" /></td>
                        <td data-label="Due date"><time v-if="task.due_date" :class="{ 'is-overdue': isOverdue(task) }" :datetime="task.due_date">{{ formatDate(task.due_date) }}</time><span v-else class="admin-tasks-muted">—</span></td>
                        <td data-label="Created"><time :datetime="task.created_at">{{ formatDate(task.created_at) }}</time></td>
                        <td data-label="Actions"><AdminActionDropdown v-if="!deletedView || isAdmin" :active-key="activeActionKey" :item-key="task.id" :label="`Actions for ${task.title}`" :items="actionItems(task)" @update:active-key="activeActionKey = $event" /><span v-else class="admin-tasks-muted">Deleted</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <footer class="admin-tasks-pagination"><p>Showing <strong>{{ rangeStart }}–{{ rangeEnd }}</strong> of <strong>{{ formatNumber(total) }}</strong> tasks</p><div class="admin-tasks-page-controls"><label>Rows <select :value="perPage" @change="$emit('per-page-change', Number($event.target.value))"><option v-for="size in [10,25,50,100]" :key="size" :value="size">{{ size }}</option></select></label><button type="button" :disabled="page <= 1 || loading" aria-label="Previous page" @click="$emit('page-change', page - 1)">‹</button><span>Page <strong>{{ page }}</strong> of {{ lastPage }}</span><button type="button" :disabled="page >= lastPage || loading" aria-label="Next page" @click="$emit('page-change', page + 1)">›</button></div></footer>
</template>

<script setup>
import { ref, watch } from 'vue';
import AdminActionDropdown from '../../../admin/components/AdminActionDropdown.vue';
import AdminTaskPriorityBadge from './AdminTaskPriorityBadge.vue';
import AdminTaskStatusBadge from './AdminTaskStatusBadge.vue';
import AdminStatusSelect from '../../../../features/admin/components/AdminStatusSelect.vue';
import UserAvatar from '../../../../shared/components/UserAvatar.vue';

const ADMIN_TASK_STATUSES = [
    { value: 'pending', label: 'Pending' },
    { value: 'in_progress', label: 'In progress' },
    { value: 'completed', label: 'Completed' },
];

const props = defineProps({
    tasks: { type: Array, required: true },
    loading: { type: Boolean, default: false },
    page: { type: Number, required: true },
    perPage: { type: Number, required: true },
    lastPage: { type: Number, required: true },
    total: { type: Number, required: true },
    deletedView: { type: Boolean, default: false },
    rangeStart: { type: Number, required: true },
    rangeEnd: { type: Number, required: true },
    isAdmin: { type: Boolean, default: true },
    canManageStatus: { type: Boolean, default: false },
});
const emit = defineEmits(['delete', 'restore', 'page-change', 'per-page-change', 'status-change']);
const activeActionKey = ref(null);

function actionItems(task) {
    if (props.deletedView) return [{ key: 'restore', label: 'Restore', icon: 'restore', onSelect: () => emit('restore', task) }];
    const items = [{ key: 'view', label: 'View', icon: 'view', to: { name: 'admin.tasks.show', params: { taskId: task.id } } }];
    if (props.isAdmin) {
        items.push({ key: 'edit', label: 'Edit', icon: 'edit', to: { name: 'admin.tasks.edit', params: { taskId: task.id } } });
        items.push({ key: 'delete', label: 'Delete', icon: 'delete', danger: true, separatorBefore: true, onSelect: () => emit('delete', task) });
    }
    return items;
}

watch(() => props.tasks.map(task => String(task.id)), (ids) => {
    if (activeActionKey.value !== null && !ids.includes(String(activeActionKey.value))) activeActionKey.value = null;
});

function formatNumber(value) { return new Intl.NumberFormat().format(Number(value) || 0); }
function formatDate(value) { if (!value) return '—'; const date = new Date(value); return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date); }
function initials(name) { return String(name || 'Unassigned').trim().split(/\s+/).slice(0, 2).map(part => part[0]?.toUpperCase()).join('') || '—'; }
function isOverdue(task) { return task.status !== 'completed' && task.due_date && new Date(task.due_date) < new Date(new Date().toDateString()); }
</script>
