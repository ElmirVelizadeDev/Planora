<template>
    <AdminSettingsLayout page-title="Settings" page-description="Manage your workspace preferences and configuration.">
        <div class="admin-settings-overview" :aria-busy="loading">
            <p v-if="error" class="settings-feedback is-error" role="alert">{{ error }} <button type="button" @click="load">Retry</button></p>
            <template v-else-if="loading">
                <section class="admin-settings-section settings-loading" aria-label="Loading settings"><span></span><span></span><span></span></section>
            </template>
            <template v-else>
                <section class="admin-settings-welcome" aria-labelledby="admin-settings-welcome-title">
                    <span class="admin-settings-welcome__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M7 4v6m10-6v6M4 17h16m-13-3v6m10-6v6" /></svg></span>
                    <div class="admin-settings-welcome__copy">
                        <p class="admin-settings-eyebrow">WORKSPACE ADMINISTRATION</p>
                        <h2 id="admin-settings-welcome-title">Workspace configuration</h2>
                        <p>Settings are stored securely and applied to supported application services.</p>
                    </div>
                    <span class="admin-settings-readonly"><span aria-hidden="true"></span>Persistent settings</span>
                </section>

                <section class="admin-settings-section" aria-labelledby="admin-settings-areas-title">
                    <header class="admin-settings-section__heading">
                        <div><p class="admin-settings-eyebrow">CONFIGURATION AREAS</p><h2 id="admin-settings-areas-title">Settings sections</h2></div>
                        <p v-if="updatedAt">Last updated {{ formatDate(updatedAt) }}<template v-if="updatedBy"> by {{ updatedBy }}</template></p>
                        <p v-else>No settings changes recorded yet.</p>
                    </header>
                    <div class="admin-settings-area-grid">
                        <RouterLink v-for="section in sections" :key="section.name" class="admin-settings-area-card" :to="{ name: section.name }">
                            <span class="admin-settings-area-card__icon" :class="section.tone" aria-hidden="true"><svg v-if="section.id==='general'" viewBox="0 0 24 24"><path d="M4 7h16M8 4v6m8-6v6M4 17h16m-10-3v6m4-6v6" /></svg><svg v-else-if="section.id==='security'" viewBox="0 0 24 24"><path d="M12 3 19 6v5c0 4.7-2.9 8-7 10-4.1-2-7-5.3-7-10V6l7-3Z" /><path d="m9 12 2 2 4-4" /></svg><svg v-else viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="14" rx="2" /><path d="m5 7 7 5 7-5" /></svg></span>
                            <span class="admin-settings-area-card__body"><strong>{{ section.label }}</strong><span>{{ section.description }}</span></span>
                            <span class="settings-status" :class="overview.groups?.[section.id]?.configured ? 'is-ready' : 'is-pending'">{{ overview.groups?.[section.id]?.configured ? 'Configured' : 'Needs setup' }}</span>
                        </RouterLink>
                    </div>
                </section>
            </template>
        </div>
    </AdminSettingsLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AdminSettingsLayout from './AdminSettingsLayout.vue';
import { getSettingsOverview } from '../../../features/settings/services/settingsApi.js';

const loading = ref(true);
const error = ref('');
const overview = ref({ groups: {} });
const updatedAt = ref(null);
const updatedBy = ref(null);
const sections = [
    { id: 'general', name: 'admin.settings.general', label: 'General', description: 'Workspace identity and regional preferences.', tone: 'is-indigo' },
    { id: 'security', name: 'admin.settings.security', label: 'Security', description: 'Password, sign-in and verification policies.', tone: 'is-green' },
    { id: 'email', name: 'admin.settings.email', label: 'Email', description: 'Mail transport and sender configuration.', tone: 'is-blue' },
];

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const result = await getSettingsOverview();
        overview.value = result;
        updatedAt.value = result.updated_at;
        updatedBy.value = result.updated_by;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Settings overview could not be loaded.';
    } finally { loading.value = false; }
}

function formatDate(value) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

onMounted(load);
</script>
