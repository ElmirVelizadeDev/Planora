<template>
    <main class="admin-dashboard" :aria-busy="loading">
        <section class="admin-welcome" aria-labelledby="admin-dashboard-title">
            <div>
                <p class="admin-dashboard-eyebrow">{{ today }}</p>
                <h1 id="admin-dashboard-title">{{ greeting }}, {{ firstName }}</h1>
                <p class="admin-dashboard-description">A current overview of Planora accounts and activity.</p>
            </div>
        </section>

        <p v-if="loading" class="admin-dashboard-feedback" role="status">Loading platform overview…</p>
        <div v-else-if="error" class="admin-dashboard-feedback is-error" role="alert">
            <span>Dashboard information couldn’t be loaded.</span>
            <button type="button" @click="loadDashboard">Try again</button>
        </div>

        <template v-else>
            <section class="admin-stat-grid" aria-label="Platform account statistics">
                <article v-for="stat in stats" :key="stat.label" class="admin-stat-card">
                    <span class="admin-stat-icon" :class="`is-${stat.tone}`" aria-hidden="true">
                        <svg v-if="stat.icon === 'users'" viewBox="0 0 24 24"><path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6-7.7a4 4 0 0 1 0 7.4M17 15h.5a3.5 3.5 0 0 1 3.5 3.5V20"/></svg>
                        <svg v-else-if="stat.icon === 'active'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                        <svg v-else-if="stat.icon === 'suspended'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6m0-6-6 6"/></svg>
                        <svg v-else viewBox="0 0 24 24"><path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6-7.7a4 4 0 0 1 0 7.4M17 15h.5a3.5 3.5 0 0 1 3.5 3.5V20"/></svg>
                    </span>
                    <strong>{{ formatNumber(stat.value) }}</strong>
                    <span>{{ stat.label }}</span>
                </article>
            </section>

            <div class="admin-dashboard-grid">
                <div class="admin-main-column">
                    <section class="admin-panel" aria-labelledby="recent-users-title">
                    <div class="admin-section-heading">
                        <div><h2 id="recent-users-title">Recent registrations</h2><p>Latest accounts created on Planora</p></div>
                        <RouterLink :to="{ name: 'admin.users.index' }">User management <span aria-hidden="true">→</span></RouterLink>
                    </div>
                    <div v-if="recentUsers.length" class="admin-user-table" role="table" aria-label="Recent user registrations">
                        <div role="rowgroup">
                            <div class="admin-user-table-heading" role="row">
                                <span role="columnheader">User</span><span role="columnheader">Registered</span><span role="columnheader">Role</span><span role="columnheader">Status</span>
                            </div>
                        </div>
                        <div role="rowgroup">
                            <div v-for="user in recentUsers.slice(0, 5)" :key="user.id" class="admin-user-row" role="row">
                                <div class="admin-user-identity" role="cell">
                                    <span class="admin-user-avatar" aria-hidden="true">{{ initials(user.name) }}</span>
                                    <span class="admin-user-copy"><strong>{{ user.name }}</strong><span>{{ user.email }}</span></span>
                                </div>
                                <time role="cell" :datetime="user.created_at">{{ formatDate(user.created_at) }}</time>
                                <span class="admin-user-meta" role="cell">{{ user.role }}</span>
                                <span class="admin-user-status" role="cell" :class="`is-${user.status}`">{{ user.status }}</span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="admin-empty-state">No registered accounts yet.</p>
                    </section>

                    <section class="admin-panel" aria-labelledby="recent-activity-title">
                    <div class="admin-section-heading">
                        <div><h2 id="recent-activity-title">Recent activity</h2><p>Recorded account events</p></div>
                        <RouterLink :to="{ name: 'admin.activity-logs.index' }">Activity log <span aria-hidden="true">→</span></RouterLink>
                    </div>
                    <ul v-if="recentActivity.length" class="admin-activity-list">
                        <li v-for="activity in recentActivity.slice(0, 5)" :key="activity.id" class="admin-activity-row">
                            <span class="admin-activity-marker" aria-hidden="true"></span>
                            <div class="admin-activity-copy">
                                <strong>{{ activity.description }}</strong>
                                <span>{{ activity.actor?.name || activity.user?.name || 'System' }} · {{ formatEvent(activity.event) }}</span>
                            </div>
                            <time :datetime="activity.created_at">{{ formatRelativeDate(activity.created_at) }}</time>
                        </li>
                    </ul>
                    <p v-else class="admin-empty-state">No account activity has been recorded yet.</p>
                    </section>
                </div>

                <aside class="admin-side-column" aria-label="Administration summary">
                    <section class="admin-panel admin-status-panel" aria-labelledby="account-status-title">
                        <div class="admin-section-heading">
                            <div><h2 id="account-status-title">Account status</h2><p>Current platform distribution</p></div>
                        </div>
                        <ul class="admin-status-list">
                            <li v-for="status in accountStatuses" :key="status.key">
                                <div class="admin-status-label"><span>{{ status.label }}</span><strong>{{ formatNumber(statistics[status.key]) }}</strong></div>
                                <div class="admin-status-track" aria-hidden="true"><span :class="`is-${status.key}`" :style="{ width: `${statusPercent(statistics[status.key])}%` }"></span></div>
                            </li>
                        </ul>
                    </section>

                    <section class="admin-panel admin-shortcuts-panel" aria-labelledby="admin-shortcuts-title">
                        <div class="admin-section-heading"><div><h2 id="admin-shortcuts-title">Quick administration</h2><p>Go to a management area</p></div></div>
                        <nav class="admin-shortcut-list" aria-label="Quick administration">
                            <RouterLink :to="{ name: 'admin.users.index' }"><span>Users</span><span aria-hidden="true">→</span></RouterLink>
                            <RouterLink :to="{ name: 'admin.roles.index' }"><span>Roles &amp; permissions</span><span aria-hidden="true">→</span></RouterLink>
                            <RouterLink :to="{ name: 'admin.settings.index' }"><span>Settings</span><span aria-hidden="true">→</span></RouterLink>
                        </nav>
                    </section>
                </aside>
            </div>
        </template>
    </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import { getAdminDashboard } from '../../features/dashboard/services/dashboardApi.js';

const auth = useAuthStore();
const loading = ref(true);
const error = ref(false);
const statistics = ref({ totalUsers: 0, activeUsers: 0, inactiveUsers: 0, suspendedUsers: 0, bannedUsers: 0, totalAdmins: 0 });
const recentUsers = ref([]);
const recentActivity = ref([]);
const now = new Date();

const firstName = computed(() => String(auth.user?.name || 'Admin').trim().split(/\s+/)[0]);
const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
});
const today = new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now);
const stats = computed(() => [
    { label: 'Total users', value: statistics.value.totalUsers, icon: 'users', tone: 'primary' },
    { label: 'Active users', value: statistics.value.activeUsers, icon: 'active', tone: 'success' },
    { label: 'Suspended users', value: statistics.value.suspendedUsers, icon: 'suspended', tone: 'neutral' },
    { label: 'Administrators', value: statistics.value.totalAdmins, icon: 'admins', tone: 'violet' },
]);
const accountStatuses = [
    { key: 'activeUsers', label: 'Active' },
    { key: 'inactiveUsers', label: 'Inactive' },
    { key: 'suspendedUsers', label: 'Suspended' },
    { key: 'bannedUsers', label: 'Banned' },
];

function formatNumber(value) {
    return new Intl.NumberFormat().format(value);
}

function statusPercent(value) {
    if (!statistics.value.totalUsers) return 0;
    return Math.round((Number(value) / statistics.value.totalUsers) * 100);
}

function formatDate(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value));
}

function formatRelativeDate(value) {
    if (!value) return '';
    const date = new Date(value);
    const elapsed = Date.now() - date.getTime();
    if (elapsed < 60_000) return 'Just now';
    if (elapsed < 3_600_000) return `${Math.floor(elapsed / 60_000)}m ago`;
    if (elapsed < 86_400_000) return `${Math.floor(elapsed / 3_600_000)}h ago`;
    return formatDate(value);
}

function formatEvent(event) {
    return String(event || '').replace(/[._-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function initials(name) {
    return String(name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0].toUpperCase()).join('') || 'U';
}

async function loadDashboard() {
    loading.value = true;
    error.value = false;
    try {
        const data = await getAdminDashboard();
        statistics.value = data.statistics;
        recentUsers.value = data.recentUsers;
        recentActivity.value = data.recentActivity;
    } catch {
        error.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(loadDashboard);
</script>

<style src="../../../css/pages/admin/styles/AdminDashboard.css" scoped></style>
