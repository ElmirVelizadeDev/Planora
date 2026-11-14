<template>
        <div class="admin-projects-table-wrap" :class="{ 'is-loading': loading }">
            <table class="admin-projects-table">
                <thead><tr><th scope="col">Project</th><th scope="col">Owner</th><th scope="col">Category</th><th scope="col">Status</th><th scope="col">Start date</th><th scope="col">Due date</th><th scope="col">Created</th><th scope="col" class="actions-heading">Actions</th></tr></thead>
                <tbody>
                    <tr v-for="project in projects" :key="project.id">
                        <td data-label="Project"><RouterLink class="admin-projects-project-copy" :to="{ name: 'admin.projects.show', params: { projectId: project.id } }"><strong :title="project.name">{{ project.name }}</strong><span :title="project.description || ''">{{ project.description || 'No description' }}</span></RouterLink></td>
                        <td data-label="Owner"><div v-if="project.owner" class="admin-projects-owner"><span>{{ initials(project.owner.name) }}</span><div><strong>{{ project.owner.name }}</strong><small>{{ project.owner.email }}</small></div></div><span v-else class="admin-projects-muted">Unassigned</span></td>
                        <td data-label="Category">{{ project.category?.name || '—' }}</td>
                        <td data-label="Status"><AdminStatusSelect v-if="canManageStatus" :model-value="project.status" :options="ADMIN_PROJECT_STATUSES" :disabled="loading" :aria-label="`Change status for ${project.name}`" @update:model-value="$emit('status-change', { project, status: $event })" /><AdminProjectStatusBadge v-else :status="project.status" /></td>
                        <td data-label="Start date"><time v-if="project.start_date" :datetime="project.start_date">{{ formatDate(project.start_date) }}</time><span v-else class="admin-projects-muted">—</span></td>
                        <td data-label="Due date"><time v-if="project.due_date" :datetime="project.due_date">{{ formatDate(project.due_date) }}</time><span v-else class="admin-projects-muted">—</span></td>
                        <td data-label="Created"><time :datetime="project.created_at">{{ formatDate(project.created_at) }}</time></td>
                        <td data-label="Actions"><div class="admin-projects-actions"><RouterLink :to="{ name: 'admin.projects.show', params: { projectId: project.id } }">View</RouterLink><template v-if="isAdmin"><button type="button" @click="$emit('edit', project)">Edit</button><button class="is-danger" type="button" @click="$emit('delete', project)">Delete</button></template></div></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <footer class="admin-projects-pagination"><p>Showing <strong>{{ rangeStart }}–{{ rangeEnd }}</strong> of <strong>{{ formatNumber(total) }}</strong> projects</p><div class="admin-projects-page-controls"><button type="button" aria-label="Previous page" :disabled="page <= 1 || loading" @click="$emit('page-change', page - 1)">‹</button><span>Page <strong>{{ page }}</strong> of {{ lastPage }}</span><button type="button" aria-label="Next page" :disabled="page >= lastPage || loading" @click="$emit('page-change', page + 1)">›</button></div></footer>
</template>

<script setup>
import AdminProjectStatusBadge from './AdminProjectStatusBadge.vue';
import AdminStatusSelect from '../../../../shared/components/AdminStatusSelect.vue';
import { ADMIN_PROJECT_STATUSES } from '../composables/useAdminProjects.js';

defineProps({
    projects: { type: Array, required: true }, loading: { type: Boolean, default: false },
    page: { type: Number, required: true }, lastPage: { type: Number, required: true },
    total: { type: Number, required: true }, rangeStart: { type: Number, required: true }, rangeEnd: { type: Number, required: true },
    isAdmin: { type: Boolean, default: true }, canManageStatus: { type: Boolean, default: false },
});
defineEmits(['edit', 'delete', 'page-change', 'status-change']);

function formatNumber(value) { return new Intl.NumberFormat().format(Number(value) || 0); }
function formatDate(value) {
    if (!value) return '—';
    const date = /^\d{4}-\d{2}-\d{2}$/.test(value)
        ? (() => { const [year, month, day] = value.split('-').map(Number); return new Date(year, month - 1, day); })()
        : new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
}
function initials(name) { return String(name || 'Unassigned').trim().split(/\s+/).slice(0, 2).map(part => part[0]?.toUpperCase()).join('') || '—'; }
</script>
