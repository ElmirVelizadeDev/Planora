<template>
    <main class="admin-dashboard">
        <section class="admin-welcome" aria-labelledby="admin-dashboard-title">
            <div>
                <p class="admin-dashboard-eyebrow">Friday, November 6, 2026 · Platform overview</p>
                <h1 id="admin-dashboard-title">Welcome back, Admin!</h1>
                <p class="admin-dashboard-description">Here’s what’s happening across Planora today.</p>
            </div>
            <RouterLink class="admin-create-button" :to="{ name: 'admin.users.create' }"><span aria-hidden="true">＋</span> Add user</RouterLink>
        </section>

        <section class="admin-stat-grid" aria-label="Platform statistics">
            <article v-for="stat in stats" :key="stat.label" class="admin-stat-card" :class="`is-${stat.tone}`">
                <div class="admin-stat-topline">
                    <span class="admin-stat-icon" aria-hidden="true">
                        <svg v-if="stat.icon === 'users'" viewBox="0 0 24 24"><path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6-7.7a4 4 0 0 1 0 7.4M17 15h.5a3.5 3.5 0 0 1 3.5 3.5V20"/></svg>
                        <svg v-else-if="stat.icon === 'projects'" viewBox="0 0 24 24"><rect x="3.5" y="4" width="17" height="16" rx="3"/><path d="M8 4V2.5h8V4M8 10h8M8 14h5"/></svg>
                        <svg v-else-if="stat.icon === 'tasks'" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h5"/></svg>
                        <svg v-else viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                    </span>
                    <span class="admin-stat-context">{{ stat.context }}</span>
                </div>
                <strong>{{ stat.value }}</strong>
                <span class="admin-stat-label">{{ stat.label }}</span>
            </article>
        </section>

        <div class="admin-overview-grid">
            <section class="admin-panel admin-users-panel" aria-labelledby="recent-users-title">
                <div class="admin-section-heading">
                    <div><h2 id="recent-users-title">Recently registered users</h2><p>New members on the platform</p></div>
                    <RouterLink :to="{ name: 'admin.users.index' }">View all <span aria-hidden="true">→</span></RouterLink>
                </div>
                <ul class="admin-user-list">
                    <li v-for="user in recentUsers" :key="user.email" class="admin-user-row">
                        <span class="admin-user-avatar" :class="`is-${user.tone}`" aria-hidden="true">{{ user.initials }}</span>
                        <div class="admin-user-copy"><strong>{{ user.name }}</strong><span>{{ user.email }}</span></div>
                        <time :datetime="user.dateTime">{{ user.date }}</time>
                        <span class="admin-user-status" :class="`is-${user.statusTone}`">{{ user.status }}</span>
                    </li>
                </ul>
            </section>

            <section class="admin-panel admin-activity-panel" aria-labelledby="recent-activity-title">
                <div class="admin-section-heading">
                    <div><h2 id="recent-activity-title">Recent activity</h2><p>Latest platform updates</p></div>
                    <span class="admin-activity-period">Today</span>
                </div>
                <ul class="admin-activity-list">
                    <li v-for="activity in activities" :key="activity.title">
                        <span class="admin-activity-icon" :class="`is-${activity.tone}`" aria-hidden="true">
                            <svg v-if="activity.icon === 'user'" viewBox="0 0 24 24"><path d="M19 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-7A3.5 3.5 0 0 0 5 18.5V20M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
                            <svg v-else-if="activity.icon === 'project'" viewBox="0 0 24 24"><rect x="3.5" y="4" width="17" height="16" rx="3"/><path d="M8 10h8M8 14h5"/></svg>
                            <svg v-else viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                        </span>
                        <div class="admin-activity-copy"><strong>{{ activity.title }}</strong><span>{{ activity.detail }}</span></div>
                        <time :datetime="activity.dateTime">{{ activity.time }}</time>
                    </li>
                </ul>
                <RouterLink class="admin-activity-link" :to="{ name: 'admin.activity-logs.index' }">View activity logs <span aria-hidden="true">→</span></RouterLink>
            </section>
        </div>
    </main>
</template>

<script setup>
const stats = [
    { label: 'Total users', value: '1,284', context: 'All accounts', icon: 'users', tone: 'primary' },
    { label: 'Total projects', value: '386', context: 'Across Planora', icon: 'projects', tone: 'violet' },
    { label: 'Total tasks', value: '8,942', context: 'Platform-wide', icon: 'tasks', tone: 'warning' },
    { label: 'Completed tasks', value: '6,731', context: '75% completion', icon: 'done', tone: 'success' },
];

const recentUsers = [
    { initials: 'SC', name: 'Sofia Chen', email: 'sofia.chen@example.com', date: 'Today', dateTime: '2026-11-06', status: 'Active', statusTone: 'active', tone: 'blue' },
    { initials: 'JM', name: 'James Miller', email: 'james.miller@example.com', date: 'Nov 5', dateTime: '2026-11-05', status: 'Active', statusTone: 'active', tone: 'gold' },
    { initials: 'AP', name: 'Ava Patel', email: 'ava.patel@example.com', date: 'Nov 4', dateTime: '2026-11-04', status: 'Pending', statusTone: 'pending', tone: 'rose' },
];

const activities = [
    { title: 'New user registered', detail: 'Sofia Chen joined Planora', time: '9:42 AM', dateTime: '2026-11-06T09:42', icon: 'user', tone: 'primary' },
    { title: 'Project created', detail: 'Website refresh was created', time: '9:18 AM', dateTime: '2026-11-06T09:18', icon: 'project', tone: 'violet' },
    { title: 'Tasks completed', detail: '12 tasks marked as complete', time: '8:56 AM', dateTime: '2026-11-06T08:56', icon: 'done', tone: 'success' },
];
</script>

<style src="../../../css/pages/admin/styles/AdminDashboard.css" scoped></style>
