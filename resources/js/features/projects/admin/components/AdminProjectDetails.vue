<template>
    <div class="admin-projects-detail-grid">
        <section class="admin-projects-detail-panel" aria-labelledby="project-description-heading">
            <div class="admin-projects-detail-section-heading"><div><h2 id="project-description-heading">Description</h2><p>Project overview</p></div></div>
            <p class="admin-projects-detail-description">{{ project.description || 'No description was provided for this project.' }}</p>
        </section>
        <section class="admin-projects-detail-panel" aria-labelledby="project-information-heading">
            <div class="admin-projects-detail-section-heading"><div><h2 id="project-information-heading">Project information</h2><p>Current project details</p></div></div>
            <dl class="admin-projects-detail-information">
                <div><dt>Project ID</dt><dd>#{{ project.id }}</dd></div>
                <div><dt>Project name</dt><dd>{{ project.name || '—' }}</dd></div>
                <div><dt>Owner</dt><dd>{{ project.owner?.name || 'Unassigned' }}</dd></div>
                <div><dt>Category</dt><dd>{{ project.category?.name || '—' }}</dd></div>
                <div><dt>Owner email</dt><dd>{{ project.owner?.email || '—' }}</dd></div>
                <div><dt>Status</dt><dd><AdminProjectStatusBadge :status="project.status" /></dd></div>
                <div><dt>Start date</dt><dd>{{ formatDate(project.start_date) }}</dd></div>
                <div><dt>Due date</dt><dd>{{ formatDate(project.due_date) }}</dd></div>
                <div><dt>Created</dt><dd>{{ formatDate(project.created_at) }}</dd></div>
            </dl>
        </section>
    </div>
</template>

<script setup>
import AdminProjectStatusBadge from './AdminProjectStatusBadge.vue';

defineProps({ project: { type: Object, required: true } });

function formatDate(value) {
    if (!value) return '—';
    const date = /^\d{4}-\d{2}-\d{2}$/.test(value)
        ? (() => { const [year, month, day] = value.split('-').map(Number); return new Date(year, month - 1, day); })()
        : new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
}
</script>
