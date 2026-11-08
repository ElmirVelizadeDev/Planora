<template>
    <div class="authenticated-home">
        <header class="public-header auth-home-header">
            <nav class="public-nav" aria-label="Account navigation">
                <RouterLink class="public-brand" :to="{ name: 'home' }" aria-label="Planora home">
                    <img :src="'/images/branding/planora-master-512.png'" width="36" height="36" alt="" />
                    <span>Planora</span>
                </RouterLink>
                <div class="auth-home-header-actions">
                    <RouterLink class="auth-home-header-link" :to="{ name: dashboardRoute.name }">Dashboard</RouterLink>
                    <ProfileDropdown class="auth-home-profile" :variant="profileVariant" />
                </div>
            </nav>
        </header>

        <main class="auth-home-main">
            <section class="auth-home-welcome" aria-labelledby="auth-home-title">
                <div>
                    <span class="home-eyebrow"><i aria-hidden="true"></i> Your Planora workspace</span>
                    <h1 id="auth-home-title">Welcome back, <span>{{ displayName }}</span></h1>
                    <p>Your next productive day starts here. Pick up where you left off or choose what comes next.</p>
                </div>
                <span class="auth-home-welcome-mark" aria-hidden="true">
                    <svg viewBox="0 0 48 48"><path d="M13 24.5 20.5 32 36 16.5"/><circle cx="24" cy="24" r="20"/></svg>
                </span>
            </section>

            <section class="auth-home-workspace" aria-labelledby="auth-home-workspace-title">
                <div class="auth-home-workspace-copy">
                    <span class="home-eyebrow">A clear place to begin</span>
                    <h2 id="auth-home-workspace-title">Your workspace, ready when you are.</h2>
                    <p>Review your work, organize what matters, and keep making steady progress.</p>
                    <RouterLink class="home-button home-button-primary auth-home-dashboard-button" :to="dashboardRoute">
                        Go to Dashboard <span aria-hidden="true">→</span>
                    </RouterLink>
                </div>
                <div class="auth-home-illustration" aria-hidden="true">
                    <div class="auth-home-illustration-window">
                        <div class="auth-home-illustration-top"><span></span><span></span><span></span><i></i></div>
                        <div class="auth-home-illustration-content">
                            <span class="auth-home-illustration-mark"><svg viewBox="0 0 24 24"><path d="m7 12 3 3 7-7"/><rect x="3.5" y="3.5" width="17" height="17" rx="4"/></svg></span>
                            <div class="auth-home-illustration-lines"><i></i><i></i></div>
                        </div>
                        <div class="auth-home-illustration-row"><span></span><i></i></div>
                        <div class="auth-home-illustration-row"><span></span><i></i></div>
                        <div class="auth-home-illustration-row"><span></span><i></i></div>
                    </div>
                    <span class="auth-home-illustration-orbit auth-home-orbit-one"></span>
                    <span class="auth-home-illustration-orbit auth-home-orbit-two"></span>
                </div>
            </section>

            <nav v-if="quickLinks.length" class="auth-home-shortcuts" aria-label="Quick access">
                <div class="auth-home-shortcut-heading"><span class="home-eyebrow">Quick access</span><span>Go directly to a part of your workspace.</span></div>
                <RouterLink v-for="item in quickLinks" :key="item.name" class="auth-home-shortcut" :to="{ name: item.name }">
                    <span class="auth-home-shortcut-icon" aria-hidden="true">
                        <svg v-if="item.icon === 'tasks'" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h5"/></svg>
                        <svg v-else-if="item.icon === 'profile'" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.6-3.3 3.1-5 7-5s6.4 1.7 7 5"/></svg>
                        <svg v-else viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.1.9-1.2 2.1-1.4-.5a7.7 7.7 0 0 1-1.4.8l-.3 1.5h-2.5l-.3-1.5a7.7 7.7 0 0 1-1.4-.8l-1.4.5-1.2-2.1 1.1-.9a6.8 6.8 0 0 1 0-1.6l-1.1-.9 1.2-2.1 1.4.5a7.7 7.7 0 0 1 1.4-.8l.3-1.5h2.5l.3 1.5a7.7 7.7 0 0 1 1.4.8l1.4-.5 1.2 2.1-1.1.9a6.8 6.8 0 0 1 0 1.6Z"/></svg>
                    </span>
                    <span><strong>{{ item.label }}</strong><small>{{ item.description }}</small></span>
                    <span class="auth-home-shortcut-arrow" aria-hidden="true">→</span>
                </RouterLink>
            </nav>
        </main>
        <footer class="auth-home-footer"><span>Planora</span><span>Thoughtful tools for focused work.</span></footer>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useAuthStore } from '../../../features/auth/stores/authStore.js';
import ProfileDropdown from '../ProfileDropdown.vue';

const auth = useAuthStore();
const displayName = computed(() => auth.user?.name?.trim() || 'there');
const isAdmin = computed(() => auth.hasRole('admin'));
const isUser = computed(() => auth.hasRole('user'));
const profileVariant = computed(() => isAdmin.value ? 'admin' : isUser.value ? 'user' : 'limited');
const dashboardRoute = computed(() => ({ name: isAdmin.value ? 'admin.dashboard' : isUser.value ? 'dashboard' : 'error.403' }));
const quickLinks = computed(() => {
    if (isAdmin.value) {
        return [
            { name: 'admin.tasks.index', label: 'Tasks', description: 'Open task management.', icon: 'tasks' },
            { name: 'admin.settings.index', label: 'Settings', description: 'Manage workspace settings.', icon: 'settings' },
        ];
    }
    if (isUser.value) {
        return [
            { name: 'tasks.index', label: 'My Tasks', description: 'Review and organize your tasks.', icon: 'tasks' },
            { name: 'profile.show', label: 'My Profile', description: 'View your account profile.', icon: 'profile' },
            { name: 'settings.index', label: 'Settings', description: 'Manage your account settings.', icon: 'settings' },
        ];
    }
    return [];
});
</script>

<style src="../../../../css/components/WorkspaceHeader.css"></style>
