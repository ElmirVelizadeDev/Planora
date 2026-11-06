<template>
    <main class="user-dashboard">
        <section class="dashboard-welcome" aria-labelledby="dashboard-title">
            <div>
                <p class="dashboard-eyebrow">Friday, November 6, 2026</p>
                <h1 id="dashboard-title">Good morning, Alex <span aria-hidden="true">✦</span></h1>
                <p class="dashboard-description">A little progress each day adds up. Here’s what’s on your plate.</p>
            </div>
            <RouterLink class="dashboard-create-button" :to="{ name: 'tasks.create' }">
                <span aria-hidden="true">＋</span> Create task
            </RouterLink>
        </section>

        <section class="dashboard-stat-grid" aria-label="Task statistics">
            <article v-for="stat in stats" :key="stat.label" class="dashboard-stat-card" :class="`is-${stat.tone}`">
                <div class="dashboard-stat-topline">
                    <span class="dashboard-stat-icon" aria-hidden="true">
                        <svg v-if="stat.icon === 'tasks'" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h5"/></svg>
                        <svg v-else-if="stat.icon === 'done'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                        <svg v-else-if="stat.icon === 'progress'" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        <svg v-else viewBox="0 0 24 24"><path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4m0 3h.01"/></svg>
                    </span>
                    <span class="dashboard-stat-caption">{{ stat.caption }}</span>
                </div>
                <strong>{{ stat.value }}</strong>
                <span class="dashboard-stat-label">{{ stat.label }}</span>
            </article>
        </section>

        <div class="dashboard-work-grid">
            <section class="dashboard-panel dashboard-tasks" aria-labelledby="tasks-title">
                <div class="dashboard-section-heading">
                    <div><h2 id="tasks-title">My Tasks</h2><p>Stay focused on what matters today.</p></div>
                    <RouterLink :to="{ name: 'tasks.index' }">View all <span aria-hidden="true">→</span></RouterLink>
                </div>
                <ul class="dashboard-task-list">
                    <li v-for="task in tasks" :key="task.title" class="dashboard-task-row">
                        <span class="dashboard-task-check" aria-hidden="true"></span>
                        <div class="dashboard-task-main"><strong>{{ task.title }}</strong><span>{{ task.project }}</span></div>
                        <span class="dashboard-task-priority" :class="`is-${task.priorityTone}`">{{ task.priority }}</span>
                        <span class="dashboard-task-status" :class="`is-${task.statusTone}`">{{ task.status }}</span>
                    </li>
                </ul>
            </section>

            <section class="dashboard-panel dashboard-deadlines" aria-labelledby="deadlines-title">
                <div class="dashboard-section-heading">
                    <div><h2 id="deadlines-title">Upcoming deadlines</h2><p>Coming up next</p></div>
                    <span class="dashboard-calendar-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="16" rx="3"/><path d="M7.5 3v4M16.5 3v4M4 9h16"/></svg>
                    </span>
                </div>
                <ul class="dashboard-deadline-list">
                    <li v-for="item in deadlines" :key="item.title">
                        <span class="deadline-date"><strong>{{ item.day }}</strong><small>{{ item.month }}</small></span>
                        <span class="deadline-copy"><strong>{{ item.title }}</strong><small>{{ item.project }}</small></span>
                    </li>
                </ul>
                <RouterLink class="dashboard-calendar-link" :to="{ name: 'calendar' }">Open calendar <span aria-hidden="true">→</span></RouterLink>
            </section>
        </div>
    </main>
</template>

<script setup>
const stats = [
    { label: 'Total tasks', value: '24', caption: 'Across all projects', icon: 'tasks', tone: 'primary' },
    { label: 'Completed', value: '16', caption: 'Great momentum', icon: 'done', tone: 'success' },
    { label: 'In progress', value: '05', caption: 'Currently underway', icon: 'progress', tone: 'warning' },
    { label: 'Overdue', value: '03', caption: 'Needs your attention', icon: 'alert', tone: 'danger' },
];

const tasks = [
    { title: 'Finalize the Q4 product roadmap', project: 'Product planning · Product', priority: 'High', priorityTone: 'high', status: 'In progress', statusTone: 'progress' },
    { title: 'Review onboarding feedback', project: 'Customer research · Growth', priority: 'Medium', priorityTone: 'medium', status: 'In progress', statusTone: 'progress' },
    { title: 'Prepare weekly team update', project: 'Team operations · Personal', priority: 'Low', priorityTone: 'low', status: 'To do', statusTone: 'todo' },
];

const deadlines = [
    { day: '06', month: 'NOV', title: 'Finalize Q4 roadmap', project: 'Product planning' },
    { day: '07', month: 'NOV', title: 'Send design review', project: 'Website refresh' },
    { day: '10', month: 'NOV', title: 'Prepare team update', project: 'Team operations' },
];
</script>

<style src="../../../../css/pages/app/dashboard/UserDashboard.css" scoped></style>
