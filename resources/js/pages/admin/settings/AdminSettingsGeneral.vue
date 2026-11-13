<template>
    <AdminSettingsLayout page-title="General settings" page-description="Set your workspace identity and regional preferences.">
        <div class="admin-settings-detail" :aria-busy="form.loading">
            <SettingsIntro title="Workspace preferences" description="These values are persisted and applied to supported application configuration." tone="is-indigo" />
            <p v-if="form.error" class="settings-feedback is-error" role="alert">{{ form.error }} <button v-if="!form.dirty" type="button" @click="form.load">Retry</button></p>
            <p v-if="form.notice" class="settings-feedback is-success" role="status">{{ form.notice }}</p>
            <section v-if="form.loading" class="admin-settings-section settings-loading" aria-label="Loading settings"><span></span><span></span></section>
            <template v-else-if="form.loaded">
                <section class="admin-settings-section settings-form-card" aria-labelledby="settings-general-workspace-title">
                    <header class="settings-form-heading"><div><h2 id="settings-general-workspace-title">Workspace information</h2><p>Name and contact details for your workspace.</p></div></header>
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Application name</span><input v-model="form.values.application_name" maxlength="100" autocomplete="organization"><small v-if="form.validationErrors.application_name">{{ form.validationErrors.application_name[0] }}</small></label>
                        <label class="settings-field"><span>Workspace name</span><input v-model="form.values.workspace_name" maxlength="100" autocomplete="organization"><small v-if="form.validationErrors.workspace_name">{{ form.validationErrors.workspace_name[0] }}</small></label>
                        <label class="settings-field settings-field--full"><span>Description</span><textarea v-model="form.values.workspace_description" maxlength="500" rows="3"></textarea><small v-if="form.validationErrors.workspace_description">{{ form.validationErrors.workspace_description[0] }}</small></label>
                        <label class="settings-field"><span>Contact email</span><input v-model="form.values.contact_email" type="email" maxlength="255" autocomplete="email"><small v-if="form.validationErrors.contact_email">{{ form.validationErrors.contact_email[0] }}</small></label>
                    </div>
                </section>

                <section class="admin-settings-section settings-form-card" aria-labelledby="settings-general-brand-title">
                    <header class="settings-form-heading"><div><h2 id="settings-general-brand-title">Workspace logo</h2><p>PNG, JPEG or WebP image. Maximum 2 MB.</p></div></header>
                    <div class="settings-logo-row">
                        <div class="settings-logo-preview"><img v-if="form.values.application_logo_url" :src="form.values.application_logo_url" alt="Workspace logo preview"><span v-else aria-hidden="true">P</span></div>
                        <div class="settings-logo-actions"><input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp" @change="uploadLogo"><button class="settings-button settings-button--secondary" type="button" :disabled="logoBusy" @click="logoInput?.click()">{{ logoBusy ? 'Uploading…' : (form.values.application_logo_url ? 'Replace logo' : 'Upload logo') }}</button><button v-if="form.values.application_logo_url" class="settings-button settings-button--text-danger" type="button" :disabled="logoBusy" @click="removeLogo">Remove</button><small v-if="logoError" role="alert">{{ logoError }}</small></div>
                    </div>
                </section>

                <section class="admin-settings-section settings-form-card" aria-labelledby="settings-general-regional-title">
                    <header class="settings-form-heading"><div><h2 id="settings-general-regional-title">Regional preferences</h2><p>Set the timezone and display formats used by application configuration.</p></div></header>
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Language</span><select v-model="form.values.default_language"><option value="en">English</option></select><small>English is the currently supported interface language.</small></label>
                        <label class="settings-field"><span>Timezone</span><select v-model="form.values.timezone"><option v-for="timezone in timezones" :key="timezone" :value="timezone">{{ timezone }}</option></select><small v-if="form.validationErrors.timezone">{{ form.validationErrors.timezone[0] }}</small></label>
                        <label class="settings-field"><span>Date format</span><select v-model="form.values.date_format"><option value="Y-m-d">YYYY-MM-DD</option><option value="d/m/Y">DD/MM/YYYY</option><option value="m/d/Y">MM/DD/YYYY</option><option value="d M Y">DD Mon YYYY</option></select><small v-if="form.validationErrors.date_format">{{ form.validationErrors.date_format[0] }}</small></label>
                        <label class="settings-field"><span>Time format</span><select v-model="form.values.time_format"><option value="24h">24 hour</option><option value="12h">12 hour</option></select><small v-if="form.validationErrors.time_format">{{ form.validationErrors.time_format[0] }}</small></label>
                    </div>
                </section>
                <SettingsSaveBar :dirty="form.dirty" :saving="form.saving" @save="form.save()" @reset="reset" />
            </template>
        </div>
    </AdminSettingsLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AdminSettingsLayout from './AdminSettingsLayout.vue';
import SettingsIntro from './components/SettingsIntro.vue';
import SettingsSaveBar from './components/SettingsSaveBar.vue';
import { removeSettingsLogo, uploadSettingsLogo } from '../../../features/settings/services/settingsApi.js';
import { useAdminSettingsForm } from '../../../features/settings/admin/composables/useAdminSettingsForm.js';

const form = useAdminSettingsForm('general');
const logoInput = ref(null);
const logoBusy = ref(false);
const logoError = ref('');
const timezones = Intl.supportedValuesOf ? Intl.supportedValuesOf('timeZone') : ['UTC', 'Asia/Baku', 'Europe/London', 'America/New_York'];

async function reset() {
    if (window.confirm('Restore General settings to defaults? This also removes the uploaded workspace logo.')) await form.reset();
}

async function uploadLogo(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    logoBusy.value = true; logoError.value = '';
    try {
        const response = await uploadSettingsLogo(file);
        form.mergeSavedValues(response.data);
        window.dispatchEvent(new CustomEvent('planora:settings-updated', { detail: form.values }));
        form.notice = 'Workspace logo updated.';
    } catch (exception) { logoError.value = exception.response?.data?.message || 'Logo upload failed.'; }
    finally { logoBusy.value = false; }
}

async function removeLogo() {
    if (!window.confirm('Remove the current workspace logo?')) return;
    logoBusy.value = true; logoError.value = '';
    try {
        const response = await removeSettingsLogo();
        form.mergeSavedValues(response.data);
        window.dispatchEvent(new CustomEvent('planora:settings-updated', { detail: form.values }));
        form.notice = 'Workspace logo removed.';
    } catch (exception) { logoError.value = exception.response?.data?.message || 'Logo could not be removed.'; }
    finally { logoBusy.value = false; }
}

onMounted(form.load);
</script>
