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
                    <strong aria-label="Not available">—</strong>
                    <span>{{ stat.label }}</span>
                </article>
            </div>
        </section>

        <div class="dashboard-main-grid">
            <section class="dashboard-panel dashboard-tasks" aria-labelledby="dashboard-tasks-title">
                <div class="dashboard-section-heading">
                    <div><h2 id="dashboard-tasks-title">My tasks</h2><p>Recent and active work.</p></div>
                </div>
                <div class="dashboard-empty-state">
                    <span class="dashboard-empty-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="m8 12 2 2 5-5M17 8h.01"/></svg>
                    </span>
                    <div><strong>No task activity to show</strong><p>Task tracking isn’t available yet.</p></div>
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
import { computed } from 'vue';
import { useAuthStore } from '../../../features/auth/stores/authStore.js';

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
    { label: 'Total tasks', icon: 'tasks', tone: 'primary' },
    { label: 'Completed', icon: 'done', tone: 'success' },
    { label: 'In progress', icon: 'progress', tone: 'warning' },
    { label: 'Upcoming', icon: 'upcoming', tone: 'neutral' },
];
</script>

<style src="../../../../css/pages/app/dashboard/UserDashboard.css" scoped></style>
