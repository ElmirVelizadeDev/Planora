<template>
    <main class="admin-settings" :class="{ 'admin-settings--detail': route.name !== 'admin.settings.index' }">
        <header class="admin-settings__header">
            <nav class="admin-settings-breadcrumbs" aria-label="Breadcrumb">
                <RouterLink :to="{ name: 'admin.dashboard' }">Administration</RouterLink>
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5" /></svg>
                <span>Settings</span>
            </nav>

            <div class="admin-settings__title-row">
                <div>
                    <p class="admin-settings-eyebrow">ADMIN CONTROL CENTER</p>
                    <h1>{{ pageTitle }}</h1>
                    <p class="admin-settings__description">{{ pageDescription }}</p>
                </div>
                <span class="admin-settings-context">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.5 16 5v4.3c0 3.8-2.5 6.5-6 8.2-3.5-1.7-6-4.4-6-8.2V5l6-2.5Z" /><path d="m7.5 10 1.7 1.7 3.5-3.5" /></svg>
                    Workspace settings
                </span>
            </div>
        </header>

        <div class="admin-settings__layout">
            <aside class="admin-settings-nav-panel">
                <p class="admin-settings-nav-panel__label">SETTINGS</p>
                <nav class="admin-settings-nav" aria-label="Settings sections">
                    <RouterLink
                        v-for="item in sections"
                        :key="item.name"
                        class="admin-settings-nav__link"
                        :to="{ name: item.name }"
                        exact-active-class="is-active"
                    >
                        <span class="admin-settings-nav__icon" aria-hidden="true">
                            <svg v-if="item.icon === 'overview'" viewBox="0 0 24 24"><rect x="4" y="4" width="6" height="6" rx="1" /><rect x="14" y="4" width="6" height="6" rx="1" /><rect x="4" y="14" width="6" height="6" rx="1" /><rect x="14" y="14" width="6" height="6" rx="1" /></svg>
                            <svg v-else-if="item.icon === 'general'" viewBox="0 0 24 24"><path d="M4 7h16M7 4v6m10-6v6M4 17h16m-10-3v6m4-6v6" /></svg>
                            <svg v-else-if="item.icon === 'security'" viewBox="0 0 24 24"><path d="M12 3 19 6v5c0 4.7-2.9 8-7 10-4.1-2-7-5.3-7-10V6l7-3Z" /><path d="m9 12 2 2 4-4" /></svg>
                            <svg v-else viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="14" rx="2" /><path d="m5 7 7 5 7-5" /></svg>
                        </span>
                        <span class="admin-settings-nav__copy">
                            <strong>{{ item.label }}</strong>
                            <small>{{ item.description }}</small>
                        </span>
                        <svg class="admin-settings-nav__chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5" /></svg>
                    </RouterLink>
                </nav>

                <div class="admin-settings-nav-note">
                    <span class="admin-settings-nav-note__mark" aria-hidden="true">P</span>
                    <div><strong>Planora Admin</strong><span>Workspace configuration</span></div>
                </div>
            </aside>

            <section class="admin-settings__content" aria-label="Settings content">
                <slot />
            </section>
        </div>
    </main>
</template>

<script setup>
import { useRoute } from 'vue-router';

const route = useRoute();

defineProps({
    pageTitle: { type: String, required: true },
    pageDescription: { type: String, required: true },
});

const sections = [
    { name: 'admin.settings.index', label: 'Overview', description: 'Settings at a glance', icon: 'overview' },
    { name: 'admin.settings.general', label: 'General', description: 'Workspace preferences', icon: 'general' },
    { name: 'admin.settings.security', label: 'Security', description: 'Access and protection', icon: 'security' },
    { name: 'admin.settings.email', label: 'Email', description: 'Delivery preferences', icon: 'email' },
];
</script>

<style src="../../../../css/pages/admin/settings.css"></style>
