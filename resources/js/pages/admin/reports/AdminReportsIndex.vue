<template>
    <main class="admin-reports" :aria-busy="loading">
        <header class="reports-page-header">
            <div>
                <p class="reports-eyebrow">PLANORA INSIGHTS</p>
                <h1>Reports &amp; Analytics</h1>
                <p class="reports-subtitle">A data-backed view of registrations, delivery, workload, and category usage.</p>
                <p class="reports-updated">{{ generatedAt ? `Updated ${formatDateTime(generatedAt)}` : 'Waiting for report data' }}<span v-if="report"> · {{ formatDate(report.period.from) }} – {{ formatDate(report.period.to) }}</span></p>
            </div>
            <div class="reports-header-actions">
                <button class="reports-button reports-button--secondary" type="button" :disabled="loading || Boolean(rangeError)" :aria-busy="loading" @click="refresh">
                    <svg :class="{ 'is-spinning': loading }" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/></svg>
                    {{ loading ? 'Refreshing…' : 'Refresh' }}
                </button>
                <button class="reports-button reports-button--primary" type="button" :disabled="!report || exporting || loading || Boolean(rangeError)" :aria-busy="exporting" @click="exportCsv">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v3h16v-3"/></svg>
                    {{ exporting ? 'Preparing CSV…' : 'Export CSV' }}
                </button>
            </div>
        </header>

        <section class="reports-filter-panel" aria-label="Report filters">
            <div class="reports-filter-heading"><div><h2>Report filters</h2><p>Date range uses UTC and scopes new records, trends, assignments, and category usage. User and Project scope related records; Task status affects task metrics and task-category usage. Current status totals stay current.</p></div><span v-if="report" class="reports-range-chip">{{ report.period.days }} days</span></div>
            <div class="reports-filter-grid">
                <label class="reports-filter-control reports-period-control"><span>Reporting period</span><select v-model="preset" @change="applyPreset(preset)">
                    <option value="today">Today</option><option value="last_7_days">Last 7 days</option><option value="last_30_days">Last 30 days</option><option value="last_90_days">Last 90 days</option><option value="this_month">This month</option><option value="last_month">Last month</option><option value="this_year">This year</option><option value="custom">Custom range</option>
                </select></label>
                <label v-if="preset === 'custom'" class="reports-filter-control"><span>From</span><input v-model="filters.from" type="date" :max="filters.to || today"></label>
                <label v-if="preset === 'custom'" class="reports-filter-control"><span>To</span><input v-model="filters.to" type="date" :min="filters.from || undefined" :max="today"></label>
                <ReportSearchFilter label="User" placeholder="Search users…" filter-type="users" v-model="filters.user_id" :options="options.users" :loading="optionLoading.users" :error="optionErrors.users" @search="searchOptions" />
                <ReportSearchFilter label="Project" placeholder="Search projects…" filter-type="projects" v-model="filters.project_id" :options="options.projects" :loading="optionLoading.projects" :error="optionErrors.projects" @search="searchOptions" />
                <label class="reports-filter-control"><span>Task status</span><select v-model="filters.task_status"><option value="">All task statuses</option><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select></label>
                <ReportSearchFilter label="Category" placeholder="Search categories…" filter-type="categories" v-model="filters.category_id" :options="options.categories" :loading="optionLoading.categories" :error="optionErrors.categories" @search="searchOptions" />
                <button class="reports-clear-filters" type="button" :disabled="!hasFilters" @click="clearFilters">Clear scope filters</button>
            </div>
            <p v-if="rangeError" class="reports-inline-error" role="alert">{{ rangeError }}</p>
        </section>

        <nav class="reports-section-nav" aria-label="Report sections">
            <a href="#reports-overview">Overview</a><a href="#reports-users">Users</a><a href="#reports-projects">Projects</a><a href="#reports-tasks">Tasks</a><a href="#reports-categories">Categories</a>
        </nav>

        <p v-if="exportError" class="reports-feedback is-error" role="alert">{{ exportError }}</p>
        <p v-if="loading && report" class="reports-feedback reports-refresh-state" role="status">Refreshing report data… Your selected filters are preserved.</p>
        <section v-if="loading && !report" class="reports-loading" aria-label="Loading reports" role="status">
            <div class="reports-loading-kpis"><span v-for="index in 10" :key="`kpi-${index}`" class="reports-skeleton"></span></div>
            <div class="reports-loading-charts"><span v-for="index in 6" :key="`chart-${index}`" class="reports-skeleton reports-skeleton--chart"></span></div>
        </section>
        <section v-else-if="error && !report" class="reports-error-state" role="alert"><strong>Reports are temporarily unavailable.</strong><p>{{ error }}</p><button class="reports-button reports-button--primary" type="button" @click="refresh">Try again</button></section>
        <template v-else-if="report">
            <p v-if="error" class="reports-feedback is-error" role="alert">{{ error }} <button type="button" @click="refresh">Retry</button></p>
            <section id="reports-overview" class="reports-kpi-grid" aria-label="Executive overview">
                <ReportKpiCard label="Users" :value="report.overview.users.total" :description="`${number(report.overview.users.active)} active · ${number(report.overview.users.inactive)} inactive · ${number(report.overview.users.suspended)} suspended · ${number(report.overview.users.banned)} banned`" icon="U" tone="primary" />
                <ReportKpiCard label="New registrations" :value="report.overview.users.new_in_period" :growth="report.overview.users.growth_percent" description="Compared with the prior period" icon="↗" tone="violet" />
                <ReportKpiCard label="Projects" :value="report.overview.projects.total" :description="`${number(report.overview.projects.active)} active · ${number(report.overview.projects.completed)} completed`" icon="P" tone="blue" />
                <ReportKpiCard label="Project completion" :value="report.overview.projects.completion_rate" :percent="true" description="Completed projects across the active scope" icon="✓" tone="green" />
                <ReportKpiCard label="Projects created" :value="report.overview.projects.new_in_period" :growth="report.overview.projects.growth_percent" description="Compared with the prior period" icon="＋" tone="blue" />
                <ReportKpiCard label="Tasks" :value="report.overview.tasks.total" :description="`${number(report.overview.tasks.pending)} pending · ${number(report.overview.tasks.in_progress)} in progress · ${number(report.overview.tasks.completed)} completed`" icon="T" tone="violet" />
                <ReportKpiCard label="Overdue tasks" :value="report.overview.tasks.overdue" description="Open tasks due before today" icon="!" tone="red" />
                <ReportKpiCard label="Tasks created" :value="report.overview.tasks.created_in_period" :growth="report.overview.tasks.growth_percent" description="Compared with the prior period" icon="＋" tone="green" />
                <ReportKpiCard label="Task completion" :value="report.overview.tasks.completion_rate" :percent="true" description="Currently completed tasks across the active scope" icon="✓" tone="green" />
                <ReportKpiCard label="Categories" :value="report.overview.categories.active" :description="`${number(report.overview.categories.archived)} archived · historical usage retained`" icon="C" tone="primary" />
            </section>

            <section id="reports-users" class="reports-section" aria-labelledby="reports-users-title">
                <header class="reports-section-heading"><div><p>ACCOUNTS</p><h2 id="reports-users-title">User analytics</h2><span>Registration activity follows the selected period; status and role charts show the current account mix.</span></div></header>
                <div class="reports-chart-grid reports-chart-grid--two">
                    <ReportTrendChart title="User registrations" description="New accounts by day" :series="report.user_analytics.registration_trend" color="#5B6CFA" />
                    <ReportBreakdownChart title="Current user status" description="Status of all accounts in the active scope" :items="report.user_analytics.status_distribution" />
                    <ReportBreakdownChart title="Current role mix" description="Roles across all accounts in the active scope" :items="report.user_analytics.role_distribution" />
                    <ReportTableCard title="Recent registrations" description="Latest accounts created in the selected period" table="registrations" :filters="filters" :rows="report.user_analytics.recent_registrations.data" :pagination="report.user_analytics.recent_registrations.meta" :columns="userColumns" empty-message="No registrations in this period.">
                        <template #name="{ value }"><strong class="reports-table-primary">{{ value }}</strong></template>
                        <template #status="{ value }"><span class="reports-status" :class="`is-${value}`">{{ statusLabel(value) }}</span></template>
                        <template #created_at="{ value }">{{ formatDate(value) }}</template>
                    </ReportTableCard>
                </div>
            </section>

            <section id="reports-projects" class="reports-section" aria-labelledby="reports-projects-title">
                <header class="reports-section-heading"><div><p>DELIVERY</p><h2 id="reports-projects-title">Project analytics</h2><span>Status counts cover all matching projects; creation trends, category usage, and recent records use the selected period.</span></div></header>
                <div class="reports-chart-grid reports-chart-grid--two">
                    <ReportTrendChart title="Project creation" description="Projects created by day" :series="report.project_analytics.creation_trend" color="#3C9DB5" />
                    <ReportBreakdownChart title="Project status" description="Current status of all projects in the active scope" :items="report.project_analytics.status_distribution" />
                    <ReportBreakdownChart title="Projects by category" description="Category usage for projects created in this period, including archived categories" :items="report.project_analytics.category_usage" />
                    <ReportTableCard title="Recent projects" description="Latest projects created in the selected period" table="projects" :filters="filters" :rows="report.project_analytics.recent_projects.data" :pagination="report.project_analytics.recent_projects.meta" :columns="projectColumns" empty-message="No projects were created in this period.">
                        <template #name="{ row, value }"><router-link class="reports-table-primary reports-record-link" :to="{ name: 'admin.projects.show', params: { projectId: row.id } }">{{ value }}</router-link></template>
                        <template #status="{ value }"><span class="reports-status" :class="`is-${value}`">{{ statusLabel(value) }}</span></template>
                        <template #category="{ row }"><span v-if="row.category" class="reports-category-name"><i :style="{ backgroundColor: row.category.color }"></i>{{ row.category.name }}<small v-if="row.category.deleted_at">Archived</small></span><span v-else class="reports-muted">Uncategorized</span></template>
                        <template #created_at="{ value }">{{ formatDate(value) }}</template>
                    </ReportTableCard>
                </div>
            </section>

            <section id="reports-tasks" class="reports-section" aria-labelledby="reports-tasks-title">
                <header class="reports-section-heading"><div><p>WORKLOAD</p><h2 id="reports-tasks-title">Task analytics</h2><span>Status, priority, and overdue totals cover the active scope; creation trend and assignee breakdown use the selected period. Overdue excludes completed tasks.</span></div></header>
                <div class="reports-chart-grid reports-chart-grid--three">
                    <ReportTrendChart title="Task creation" description="Tasks created by day" :series="report.task_analytics.creation_trend" color="#8D70D6" />
                    <ReportBreakdownChart title="Task status" description="Current status of all tasks in the active scope" :items="report.task_analytics.status_distribution" />
                    <ReportBreakdownChart title="Task priority" description="Current priority of all tasks in the active scope" :items="report.task_analytics.priority_distribution" />
                    <ReportBreakdownChart title="Most assigned" description="Top eight assignees in the selected period" :items="report.task_analytics.assignee_distribution" />
                    <ReportTableCard title="Recent tasks" description="Latest tasks created in the selected period" table="tasks" :filters="filters" :rows="report.task_analytics.recent_tasks.data" :pagination="report.task_analytics.recent_tasks.meta" :columns="recentTaskColumns" empty-message="No tasks were created in this period.">
                        <template #title="{ row, value }"><router-link class="reports-table-primary reports-record-link" :to="{ name: 'admin.tasks.show', params: { taskId: row.id } }">{{ value }}</router-link></template>
                        <template #project="{ row }">{{ row.project?.name || '—' }}</template>
                        <template #category="{ row }"><span v-if="row.category" class="reports-category-name"><i :style="{ backgroundColor: row.category.color }"></i>{{ row.category.name }}<small v-if="row.category.deleted_at">Archived</small></span><span v-else class="reports-muted">Uncategorized</span></template>
                        <template #status="{ value }"><span class="reports-status" :class="`is-${value}`">{{ statusLabel(value) }}</span></template>
                        <template #priority="{ value }"><span class="reports-priority" :class="`is-${value}`">{{ statusLabel(value) }}</span></template>
                        <template #created_at="{ value }">{{ formatDate(value) }}</template>
                    </ReportTableCard>
                    <ReportTableCard title="Overdue tasks" description="Open tasks currently past due" table="overdue" :filters="filters" :rows="report.task_analytics.overdue_tasks.data" :pagination="report.task_analytics.overdue_tasks.meta" :columns="taskColumns" empty-message="There are no overdue tasks in this scope.">
                        <template #title="{ row, value }"><router-link class="reports-table-primary reports-record-link" :to="{ name: 'admin.tasks.show', params: { taskId: row.id } }">{{ value }}</router-link></template>
                        <template #project="{ row }">{{ row.project?.name || '—' }}</template>
                        <template #status="{ value }"><span class="reports-status" :class="`is-${value}`">{{ statusLabel(value) }}</span></template>
                        <template #priority="{ value }"><span class="reports-priority" :class="`is-${value}`">{{ statusLabel(value) }}</span></template>
                        <template #due_date="{ value }">{{ formatDate(value) }}</template>
                    </ReportTableCard>
                    <ReportTableCard title="Upcoming deadlines" description="Open tasks due within 14 calendar days, including today" table="upcoming" :filters="filters" :rows="report.task_analytics.upcoming_deadlines.data" :pagination="report.task_analytics.upcoming_deadlines.meta" :columns="taskColumns" empty-message="No upcoming deadlines in this scope.">
                        <template #title="{ row, value }"><router-link class="reports-table-primary reports-record-link" :to="{ name: 'admin.tasks.show', params: { taskId: row.id } }">{{ value }}</router-link></template>
                        <template #project="{ row }">{{ row.project?.name || '—' }}</template>
                        <template #status="{ value }"><span class="reports-status" :class="`is-${value}`">{{ statusLabel(value) }}</span></template>
                        <template #priority="{ value }"><span class="reports-priority" :class="`is-${value}`">{{ statusLabel(value) }}</span></template>
                        <template #due_date="{ value }">{{ formatDate(value) }}</template>
                    </ReportTableCard>
                </div>
                <p class="reports-data-note"><strong>Completion timing is unavailable.</strong> Tasks store their current status and update timestamp, but no status-change history. A completion trend or on-time/late completion metric would imply timestamps the system does not record.</p>
            </section>

            <section id="reports-categories" class="reports-section" aria-labelledby="reports-categories-title">
                <header class="reports-section-heading"><div><p>CLASSIFICATION</p><h2 id="reports-categories-title">Category usage</h2><span>Charts show the eight most-used and five least-used categories for the selected period; the searchable table includes all categories and archived associations.</span></div></header>
                <div class="reports-chart-grid reports-chart-grid--two">
                    <ReportBreakdownChart title="Projects by category" description="Eight highest project counts in the selected period" :items="report.category_analytics.project_usage" />
                    <ReportBreakdownChart title="Tasks by category" description="Eight highest task counts in the selected period" :items="report.category_analytics.task_usage" />
                    <ReportBreakdownChart title="Least-used project categories" description="Five lowest project counts in this period" :items="report.category_analytics.least_project_usage" :show-zero-items="true" />
                    <ReportBreakdownChart title="Least-used task categories" description="Five lowest task counts in this period" :items="report.category_analytics.least_task_usage" :show-zero-items="true" />
                    <ReportTableCard title="Categories with no period usage" description="No matching project or task was created in this period" table="unused_categories" :filters="filters" :rows="report.category_analytics.unused.data" :pagination="report.category_analytics.unused.meta" :columns="categoryColumns" empty-message="Every category has usage in the selected period.">
                        <template #name="{ row }"><span class="reports-category-name"><i :style="{ backgroundColor: row.color }"></i><router-link class="reports-record-link" :to="{ name: 'admin.categories.show', params: { categoryId: row.id } }"><strong>{{ row.name }}</strong></router-link></span></template>
                        <template #status="{ value }"><span class="reports-status" :class="`is-${value}`">{{ statusLabel(value) }}</span></template>
                    </ReportTableCard>
                    <ReportTableCard title="Category usage details" description="Usage counts for the selected period" table="categories" :filters="filters" :rows="report.category_analytics.categories.data" :pagination="report.category_analytics.categories.meta" :columns="categoryColumns" empty-message="No categories are available.">
                        <template #name="{ row }"><span class="reports-category-name"><i :style="{ backgroundColor: row.color }"></i><router-link class="reports-record-link" :to="{ name: 'admin.categories.show', params: { categoryId: row.id } }"><strong>{{ row.name }}</strong></router-link></span></template>
                        <template #status="{ value }"><span class="reports-status" :class="`is-${value}`">{{ statusLabel(value) }}</span></template>
                    </ReportTableCard>
                    <ReportTableCard title="Assignment distribution" description="Task assignments in the selected period" table="assignees" :filters="filters" :rows="report.task_analytics.assignees.data" :pagination="report.task_analytics.assignees.meta" :columns="assigneeColumns" empty-message="No assigned tasks in this period.">
                        <template #name="{ value }"><strong class="reports-table-primary">{{ value }}</strong></template>
                        <template #status="{ value }"><span class="reports-status" :class="`is-${value}`">{{ statusLabel(value) }}</span></template>
                    </ReportTableCard>
                </div>
            </section>
        </template>
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ReportBreakdownChart from '../../../features/reports/admin/components/ReportBreakdownChart.vue';
import ReportKpiCard from '../../../features/reports/admin/components/ReportKpiCard.vue';
import ReportSearchFilter from '../../../features/reports/admin/components/ReportSearchFilter.vue';
import ReportTableCard from '../../../features/reports/admin/components/ReportTableCard.vue';
import ReportTrendChart from '../../../features/reports/admin/components/ReportTrendChart.vue';
import { useAdminReports } from '../../../features/reports/admin/composables/useAdminReports.js';

const { report, loading, exporting, error, exportError, generatedAt, filters, options, optionLoading, optionErrors, load, searchOptions, exportCsv } = useAdminReports();
const preset = ref('last_30_days');
const today = localDate(new Date());
let filterTimer;
const rangeError = computed(() => {
    if (preset.value === 'custom' && (!filters.from || !filters.to)) return 'Select both dates for a custom reporting range.';
    if (filters.from && filters.to && filters.to < filters.from) return 'The end date must be on or after the start date.';
    if (filters.from && filters.to) {
        const days = Math.round((Date.parse(`${filters.to}T00:00:00Z`) - Date.parse(`${filters.from}T00:00:00Z`)) / 86400000) + 1;
        if (days > 366) return 'Custom reporting ranges cannot exceed 366 days.';
    }
    return '';
});
const hasFilters = computed(() => Boolean(filters.user_id || filters.project_id || filters.task_status || filters.category_id));
const userColumns = [{ key: 'name', label: 'User' }, { key: 'role', label: 'Role' }, { key: 'status', label: 'Status' }, { key: 'created_at', label: 'Registered', format: 'datetime' }];
const projectColumns = [{ key: 'name', label: 'Project' }, { key: 'category', label: 'Category', sortable: false }, { key: 'status', label: 'Status' }, { key: 'created_at', label: 'Created', format: 'datetime' }];
const taskColumns = [{ key: 'title', label: 'Task' }, { key: 'project', label: 'Project', sortable: false }, { key: 'priority', label: 'Priority' }, { key: 'due_date', label: 'Due', format: 'date' }];
const recentTaskColumns = [{ key: 'title', label: 'Task' }, { key: 'project', label: 'Project', sortable: false }, { key: 'category', label: 'Category', sortable: false }, { key: 'status', label: 'Status' }, { key: 'priority', label: 'Priority' }, { key: 'created_at', label: 'Created', format: 'datetime' }];
const categoryColumns = [{ key: 'name', label: 'Category' }, { key: 'status', label: 'Status' }, { key: 'projects', label: 'Projects', sortKey: 'projects', format: 'number' }, { key: 'tasks', label: 'Tasks', sortKey: 'tasks', format: 'number' }];
const assigneeColumns = [{ key: 'name', label: 'Assignee' }, { key: 'role', label: 'Role' }, { key: 'account_status', label: 'Account' }, { key: 'tasks_count', label: 'Tasks', sortKey: 'tasks', format: 'number' }];

function localDate(date) {
    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`;
}
function applyPreset(value) {
    preset.value = value;
    if (value === 'custom') return;
    const now = new Date();
    const end = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
    let start = new Date(end);
    if (value === 'last_7_days') start.setUTCDate(start.getUTCDate() - 6);
    else if (value === 'last_30_days') start.setUTCDate(start.getUTCDate() - 29);
    else if (value === 'last_90_days') start.setUTCDate(start.getUTCDate() - 89);
    else if (value === 'this_month') start = new Date(Date.UTC(end.getUTCFullYear(), end.getUTCMonth(), 1));
    else if (value === 'last_month') {
        start = new Date(Date.UTC(end.getUTCFullYear(), end.getUTCMonth() - 1, 1));
        end.setUTCDate(0);
    } else if (value === 'this_year') start = new Date(Date.UTC(end.getUTCFullYear(), 0, 1));
    filters.from = localDate(start);
    filters.to = localDate(end);
}
function clearFilters() {
    filters.user_id = '';
    filters.project_id = '';
    filters.task_status = '';
    filters.category_id = '';
}
function refresh() {
    clearTimeout(filterTimer);
    load();
}
function formatDate(value) {
    if (!value) return '—';
    const date = new Date(`${String(value).slice(0, 10)}T12:00:00Z`);
    return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }).format(date);
}
function formatDateTime(value) {
    return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(value));
}
function number(value) { return new Intl.NumberFormat().format(Number(value) || 0); }
function statusLabel(value) { return String(value || '').replace(/\b\w/g, (letter) => letter.toUpperCase()).replace('_', ' '); }

applyPreset('last_30_days');
watch(filters, () => {
    clearTimeout(filterTimer);
    if (rangeError.value) return;
    filterTimer = setTimeout(load, 300);
}, { deep: true });
onMounted(() => {
    load();
});
onBeforeUnmount(() => clearTimeout(filterTimer));
</script>

<style src="../../../../css/pages/admin/reports/styles/AdminReportsIndex.css"></style>
