<template>
    <main class="user-dashboard">
        <section class="dashboard-welcome" aria-labelledby="dashboard-title">
            <div>
                <p class="dashboard-eyebrow">{{ today }}</p>
                <h1 id="dashboard-title">{{ greeting }}, {{ firstName }}</h1>
                <p class="dashboard-description">A calm overview of your personal workspace.</p>
            </div>
        </section>

        <section class="dashboard-overview" aria-labelledby="dashboard-overview-title">
            <div class="dashboard-section-heading">
                <div>
                    <h2 id="dashboard-overview-title">Task overview</h2>
                    <p>Tasks, progress, and upcoming deadlines.</p>
                </div>
            </div>
            <div class="dashboard-stat-grid">
                <article v-for="stat in overviewStats" :key="stat.label" class="dashboard-stat-card">
                    <span class="dashboard-stat-icon" :class="`is-${stat.tone}`" aria-hidden="true">
                        <svg v-if="stat.icon === 'tasks'" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h5"/></svg>
                        <svg v-else-if="stat.icon === 'done'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                        <svg v-else-if="stat.icon === 'progress'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        <svg v-else viewBox="0 0 24 24"><path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4m0 3h.01"/></svg>
                    </span>
                    <strong :aria-label="tasksError ? 'Task statistics unavailable' : undefined">{{ tasksLoading || tasksError ? '—' : formatNumber(taskStatistics[stat.key]) }}</strong>
                    <span>{{ stat.label }}</span>
                </article>
            </div>
        </section>

        <div class="dashboard-main-grid">
            <section class="dashboard-panel dashboard-tasks" aria-labelledby="dashboard-tasks-title">
                <div class="dashboard-section-heading">
                    <div><h2 id="dashboard-tasks-title">My tasks</h2><p>Recent and active work.</p></div>
                    <RouterLink class="dashboard-tasks-view-all" :to="{ name: 'tasks.index' }">View all</RouterLink>
                </div>
                <div v-if="tasksLoading" class="dashboard-empty-state" role="status">
                    <span class="dashboard-empty-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="m8 12 2 2 5-5M17 8h.01"/></svg>
                    </span>
                    <div><strong>Loading your tasks</strong><p>Fetching your latest task activity.</p></div>
                </div>
                <div v-else-if="tasksError" class="dashboard-empty-state" role="alert"><span class="dashboard-empty-mark" aria-hidden="true">!</span><div><strong>Tasks could not be loaded</strong><p>{{ tasksError }}</p><button class="dashboard-tasks-retry" type="button" @click="loadDashboardTasks">Try again</button></div></div>
                <div v-else-if="recentTasks.length" class="dashboard-task-list">
                    <RouterLink v-for="task in recentTasks" :key="task.id" class="dashboard-task-row" :to="{ name: 'tasks.show', params: { taskId: task.id } }">
                        <span class="dashboard-task-mark" :class="`is-${task.status}`" aria-hidden="true"><svg viewBox="0 0 24 24"><path v-if="task.status === 'completed'" d="m6 12 4 4 8-8"/><circle v-else cx="12" cy="12" r="8"/></svg></span>
                        <span class="dashboard-task-copy"><strong>{{ task.title }}</strong><small>{{ task.due_date ? `Due ${formatDate(task.due_date)}` : 'No due date' }}</small></span>
                        <span class="dashboard-task-status" :class="`is-${task.status}`">{{ label(task.status) }}</span>
                    </RouterLink>
                </div>
                <div v-else class="dashboard-empty-state">
                    <span class="dashboard-empty-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="m8 12 2 2 5-5M17 8h.01"/></svg></span>
                    <div><strong>No task activity to show</strong><p>Create a task to start organizing your work.</p><RouterLink class="dashboard-tasks-create" :to="{ name: 'tasks.create' }">Create a task</RouterLink></div>
                </div>
            </section>

            <section class="dashboard-panel dashboard-account" aria-labelledby="dashboard-account-title">
                <div class="dashboard-section-heading">
                    <div><h2 id="dashboard-account-title">Account overview</h2><p>Your current account details.</p></div>
                </div>
                <dl class="dashboard-account-list">
                    <div><dt>Email</dt><dd>{{ auth.user?.email || '—' }}</dd></div>
                    <div><dt>Account status</dt><dd><span class="dashboard-account-status" :class="`is-${auth.user?.status || 'unknown'}`">{{ auth.user?.status || 'Unknown' }}</span></dd></div>
                    <div><dt>Email verification</dt><dd>{{ auth.user?.email_verified_at ? 'Verified' : 'Not verified' }}</dd></div>
                    <div><dt>Member since</dt><dd>{{ memberSince }}</dd></div>
                </dl>
            </section>
        </div>
    </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '../../../features/auth/stores/authStore.js';
import { getUserTasks } from '../../../features/tasks/user/services/userTaskApi.js';

const auth = useAuthStore();
const now = new Date();

const firstName = computed(() => String(auth.user?.name || 'there').trim().split(/\s+/)[0]);
const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
});
const today = new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now);
const memberSince = computed(() => auth.user?.created_at
    ? new Intl.DateTimeFormat(undefined, { month: 'short', year: 'numeric' }).format(new Date(auth.user.created_at))
    : '—');
const overviewStats = [
    { key: 'total', label: 'Total tasks', icon: 'tasks', tone: 'primary' },
    { key: 'completed', label: 'Completed', icon: 'done', tone: 'success' },
    { key: 'in_progress', label: 'In progress', icon: 'progress', tone: 'warning' },
    { key: 'upcoming', label: 'Upcoming', icon: 'upcoming', tone: 'neutral' },
];
const taskStatistics = ref({ total: 0, completed: 0, in_progress: 0, upcoming: 0 });
const recentTasks = ref([]), tasksLoading = ref(true), tasksError = ref('');
function formatNumber(value) { return new Intl.NumberFormat().format(Number(value) || 0); }
function label(value) { return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase()); }
function formatDate(value) { if (!value) return '—'; const date = new Date(`${value}T00:00:00`); return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short' }).format(date); }
async function loadDashboardTasks() {
    tasksLoading.value = true; tasksError.value = '';
    try {
        const recent = await getUserTasks({ per_page: 4, sort: 'newest' });
        recentTasks.value = recent.data || [];
        taskStatistics.value = { ...taskStatistics.value, ...(recent.meta?.statistics || {}) };
    } catch {
        tasksError.value = 'Please try again in a moment.';
    } finally { tasksLoading.value = false; }
}
onMounted(loadDashboardTasks);
</script>

<style src="../../../../css/pages/app/dashboard/UserDashboard.css" scoped></style>
