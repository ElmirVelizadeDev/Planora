<template>
    <main class="admin-dashboard" :aria-busy="loading">
        <header class="dashboard-header">
            <div>
                <p class="dashboard-date">{{ today }}</p>
                <h1>Dashboard</h1>
                <p class="dashboard-description">A clear view of Planora and the work that needs attention.</p>
            </div>
            <button class="dashboard-refresh" type="button" :disabled="loading" :aria-busy="loading" @click="loadDashboard">
                <svg class="dashboard-refresh-icon" :class="{ 'is-spinning': loading }" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/></svg>
                {{ loading ? 'Refreshing…' : 'Refresh' }}
            </button>
        </header>

        <section v-if="error" class="dashboard-feedback is-error" role="alert">
            <span>{{ errorMessage }}</span>
            <button type="button" :disabled="loading" @click="loadDashboard">Try again</button>
        </section>
        <section v-if="loading && !data" class="dashboard-feedback" role="status">Loading your dashboard…</section>

        <template v-else-if="data">
            <section v-if="metricCards.length" class="dashboard-metrics" aria-label="Platform overview">
                <article v-for="metric in metricCards" :key="metric.label" class="dashboard-metric-card" :class="{ 'is-attention': metric.attention }">
                    <span class="dashboard-metric-icon" :class="`is-${metric.icon}`" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <template v-if="metric.icon === 'users'"><circle cx="9" cy="8" r="3"/><path d="M3.5 20v-1a5.5 5.5 0 0 1 11 0v1M16 5.5a3 3 0 0 1 0 5.8M17 14a4.5 4.5 0 0 1 3.5 4.4V20"/></template>
                            <template v-else-if="metric.icon === 'active'"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></template>
                            <template v-else-if="metric.icon === 'projects'"><path d="M3 7.5h7l2 2H21v9.5H3z"/><path d="M3 7.5V5h7l2 2h5"/></template>
                            <template v-else-if="metric.icon === 'tasks'"><path d="M8 6h12M8 12h12M8 18h12"/><path d="m3 6 1 1 2-2M3 12l1 1 2-2M3 18l1 1 2-2"/></template>
                            <template v-else-if="metric.icon === 'overdue'"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></template>
                            <template v-else><path d="M4 6h16v14H4zM8 3v6M16 3v6M4 10h16"/></template>
                        </svg>
                    </span>
                    <span class="dashboard-metric-copy"><span class="dashboard-metric-label">{{ metric.label }}</span><strong>{{ number(metric.value) }}</strong><small v-if="metric.supportingValue">{{ number(metric.supportingValue) }} active</small></span>
                </article>
            </section>

            <section v-if="charts.length" class="dashboard-chart-grid" aria-label="Status overview">
                <article v-for="chart in charts" :key="chart.key" class="dashboard-card dashboard-chart-card">
                    <header class="dashboard-card-heading"><div><h2>{{ chart.title }}</h2><p>{{ chart.subtitle }}</p></div><span class="dashboard-chart-total">{{ number(chart.total) }}</span></header>
                    <ul class="dashboard-chart-list" :aria-label="`${chart.title} counts`">
                        <li v-for="item in chart.items" :key="item.key" class="dashboard-chart-item">
                            <div class="dashboard-chart-label"><span><i :style="{ '--status-color': item.color }"></i>{{ item.label }}</span><strong>{{ number(item.value) }}</strong></div>
                            <div class="dashboard-chart-track" aria-hidden="true"><span :style="{ width: `${item.percent}%`, '--status-color': item.color }"></span></div>
                        </li>
                    </ul>
                    <p v-if="chart.total === 0" class="dashboard-chart-empty">No {{ chart.key }} yet.</p>
                </article>
            </section>

            <section v-if="data.sections.users || data.sections.activity" class="dashboard-lower-grid" aria-label="Recent updates">
                <article v-if="data.sections.activity" class="dashboard-card">
                    <header class="dashboard-card-heading"><div><h2>Recent activity</h2><p>Latest events visible to your account</p></div><RouterLink :to="{ name: 'admin.activity-logs.index' }">View all</RouterLink></header>
                    <ul v-if="data.activity?.length" class="dashboard-activity-list">
                        <li v-for="event in data.activity" :key="event.id" class="dashboard-activity-row"><span class="dashboard-activity-dot" aria-hidden="true"></span><div><strong>{{ event.description }}</strong><span>{{ event.actor || 'System' }} · {{ label(event.event) }}</span></div><time>{{ relativeDate(event.created_at) }}</time></li>
                    </ul>
                    <p v-else class="dashboard-empty">No recent activity has been recorded.</p>
                </article>

                <article v-if="data.sections.users" class="dashboard-card">
                    <header class="dashboard-card-heading"><div><h2>Recent registrations</h2><p>Newest accounts on Planora</p></div><RouterLink :to="{ name: 'admin.users.index' }">Manage users</RouterLink></header>
                    <ul v-if="data.users?.recent_registrations?.length" class="dashboard-registration-list">
                        <li v-for="user in data.users.recent_registrations" :key="user.id" class="dashboard-registration-row">
                            <span class="dashboard-avatar" aria-hidden="true">{{ initials(user.name) }}</span>
                            <span class="dashboard-registration-copy"><strong>{{ user.name }}</strong><small>{{ user.email }}</small></span>
                            <span class="dashboard-user-status" :class="`is-${user.status}`">{{ label(user.status) }}</span>
                            <time>{{ date(user.created_at) }}</time>
                        </li>
                    </ul>
                    <p v-else class="dashboard-empty">No registrations yet.</p>
                </article>
            </section>

            <nav v-if="quickLinks.length" class="dashboard-quick-links" aria-label="Quick links">
                <span>Quick links</span>
                <RouterLink v-for="link in quickLinks" :key="link.name" :to="{ name: link.name }">{{ link.label }}<span aria-hidden="true">→</span></RouterLink>
            </nav>

            <section v-if="!metricCards.length && !charts.length && !data.sections.activity && !data.sections.users" class="dashboard-card dashboard-limited-state">
                <h2>Your dashboard is ready</h2><p>Open a permitted module using the quick links above.</p>
            </section>
        </template>
    </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import { getAdminDashboard } from '../../features/dashboard/services/dashboardApi.js';

const auth = useAuthStore();
const isAdmin = computed(() => auth.hasRole('admin'));
const loading = ref(true);
const error = ref(false);
const errorMessage = ref('Dashboard information couldn’t be loaded.');
const data = ref(null);
const requestActive = ref(false);
const today = new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());

const metricCards = computed(() => {
    if (!data.value) return [];
    const cards = [];
    if (data.value.sections.users) {
        cards.push({ label: 'Total users', value: data.value.users.total, supportingValue: data.value.users.active, icon: 'users' });
    }
    if (data.value.sections.categories) cards.push({ label: 'Categories', value: data.value.categories.total, icon: 'categories' });
    if (data.value.sections.projects) cards.push({ label: 'Total projects', value: data.value.projects.total, icon: 'projects' });
    if (data.value.sections.tasks) {
        cards.push({ label: 'Total tasks', value: data.value.tasks.total, icon: 'tasks' }, { label: 'Overdue tasks', value: data.value.tasks.overdue, icon: 'overdue', attention: data.value.tasks.overdue > 0 });
    }
    return cards;
});

const charts = computed(() => {
    if (!data.value) return [];
    const chartData = [];
    if (data.value.sections.tasks) chartData.push({ key: 'tasks', title: 'Task status', subtitle: 'Current workload by status', total: data.value.tasks.total, statuses: [
        { key: 'pending', label: 'Pending', value: data.value.tasks.pending, color: '#97a1b2' },
        { key: 'in_progress', label: 'In progress', value: data.value.tasks.in_progress, color: '#5b6cfa' },
        { key: 'completed', label: 'Completed', value: data.value.tasks.completed, color: '#4c9a7b' },
    ] });
    if (data.value.sections.projects) chartData.push({ key: 'projects', title: 'Project status', subtitle: 'Current portfolio by status', total: data.value.projects.total, statuses: [
        { key: 'planned', label: 'Planned', value: data.value.projects.planned, color: '#a5adba' },
        { key: 'active', label: 'Active', value: data.value.projects.active, color: '#5b6cfa' },
        { key: 'on_hold', label: 'On hold', value: data.value.projects.on_hold, color: '#c18a3d' },
        { key: 'completed', label: 'Completed', value: data.value.projects.completed, color: '#4c9a7b' },
    ] });
    return chartData.map((chart) => ({
        ...chart,
        items: chart.statuses.map((item) => ({ ...item, percent: chart.total ? Math.round((Number(item.value || 0) / chart.total) * 100) : 0 })),
    }));
});

const quickLinks = computed(() => {
    if (!data.value) return [];
    const links = [];
    if (data.value.sections.users) links.push({ name: 'admin.users.index', label: 'Users' });
    if (data.value.sections.categories) links.push({ name: 'admin.categories.index', label: 'Categories' });
    if (data.value.sections.projects) links.push({ name: 'admin.projects.index', label: 'Projects' });
    if (data.value.sections.tasks) links.push({ name: 'admin.tasks.index', label: 'Tasks' });
    if (data.value.sections.reports) links.push({ name: 'admin.reports', label: 'Reports' });
    if (data.value.sections.activity) links.push({ name: 'admin.activity-logs.index', label: 'Activity Logs' });
    if (isAdmin.value) links.push({ name: 'admin.roles-permissions.index', label: 'Roles & Permissions' }, { name: 'admin.settings.index', label: 'Settings' });
    return links;
});

function number(value) { return new Intl.NumberFormat().format(Number(value || 0)); }
function label(value) { return String(value || '').replace(/[._-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }
function initials(value) { return String(value || '').trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('') || 'U'; }
function date(value) { return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value)) : '—'; }
function relativeDate(value) {
    if (!value) return '—';
    const timestamp = new Date(value).getTime();
    if (!Number.isFinite(timestamp)) return '—';
    const minutes = Math.max(0, Math.floor((Date.now() - timestamp) / 60000));
    return minutes < 1 ? 'Just now' : minutes < 60 ? `${minutes}m ago` : minutes < 1440 ? `${Math.floor(minutes / 60)}h ago` : date(value);
}

async function loadDashboard() {
    if (requestActive.value) return;
    requestActive.value = true;
    loading.value = true;
    error.value = false;
    try {
        data.value = await getAdminDashboard();
    } catch (cause) {
        errorMessage.value = cause.response?.data?.message || 'Dashboard information couldn’t be loaded.';
        error.value = true;
    } finally {
        loading.value = false;
        requestActive.value = false;
    }
}

onMounted(loadDashboard);
</script>

<style src="../../../css/pages/admin/styles/AdminDashboard.css" scoped></style>
