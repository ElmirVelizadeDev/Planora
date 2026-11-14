<template>
    <AdminSettingsLayout page-title="Security settings" page-description="Set the rules used for future sign-ins, passwords and verification codes.">
        <div class="admin-settings-detail" :aria-busy="form.loading">
            <SettingsIntro title="Security policies" description="These controls affect future registration, password recovery and login attempts. Existing passwords and active sessions are not changed." tone="is-green" />
            <p v-if="form.error" class="settings-feedback is-error" role="alert">{{ form.error }} <button v-if="!form.dirty" type="button" @click="form.load">Retry</button></p>
            <p v-if="form.notice" class="settings-feedback is-success" role="status">{{ form.notice }}</p>
            <section v-if="form.loading" class="admin-settings-section settings-loading" aria-label="Loading settings"><span></span><span></span></section>
            <template v-else-if="form.loaded">
                <section class="admin-settings-section settings-form-card" aria-labelledby="settings-security-password-title">
                    <header class="settings-form-heading"><div><h2 id="settings-security-password-title">Password policy</h2><p>Requirements apply to new registrations and password resets. Existing passwords remain unchanged.</p></div></header>
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Minimum password length</span><input v-model.number="form.values.minimum_password_length" type="number" min="8" max="64"><small v-if="form.validationErrors.minimum_password_length">{{ form.validationErrors.minimum_password_length[0] }}</small></label>
                    </div>
                    <div class="settings-toggle-list">
                        <label v-for="item in passwordRules" :key="item.key" class="settings-toggle"><span><strong>{{ item.title }}</strong><small>{{ item.description }}</small></span><input v-model="form.values[item.key]" type="checkbox" :aria-label="item.title"></label>
                    </div>
                </section>
                <section class="admin-settings-section settings-form-card" aria-labelledby="settings-security-login-title">
                    <header class="settings-form-heading"><div><h2 id="settings-security-login-title">Login protection</h2><p>Rate limits retain a separate hourly account cap in addition to this configurable lockout.</p></div></header>
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Maximum attempts</span><input v-model.number="form.values.max_login_attempts" type="number" min="3" max="20"><small>Allowed range: 3–20 attempts</small><small v-if="form.validationErrors.max_login_attempts">{{ form.validationErrors.max_login_attempts[0] }}</small></label>
                        <label class="settings-field"><span>Lockout window (minutes)</span><input v-model.number="form.values.login_lockout_minutes" type="number" min="1" max="60"><small>Allowed range: 1–60 minutes</small><small v-if="form.validationErrors.login_lockout_minutes">{{ form.validationErrors.login_lockout_minutes[0] }}</small></label>
                    </div>
                </section>
                <section class="admin-settings-section settings-form-card" aria-labelledby="settings-security-otp-title">
                    <header class="settings-form-heading"><div><h2 id="settings-security-otp-title">One-time verification codes</h2><p>Applies to registration and password recovery codes issued after the policy is saved.</p></div></header>
                    <div class="settings-form-grid settings-form-grid--three">
                        <label class="settings-field"><span>Code lifetime (minutes)</span><input v-model.number="form.values.otp_expiration_minutes" type="number" min="2" max="15"><small v-if="form.validationErrors.otp_expiration_minutes">{{ form.validationErrors.otp_expiration_minutes[0] }}</small></label>
                        <label class="settings-field"><span>Maximum verification attempts</span><input v-model.number="form.values.otp_max_attempts" type="number" min="3" max="10"><small v-if="form.validationErrors.otp_max_attempts">{{ form.validationErrors.otp_max_attempts[0] }}</small></label>
                        <label class="settings-field"><span>Resend cooldown (seconds)</span><input v-model.number="form.values.otp_resend_cooldown_seconds" type="number" min="30" max="600"><small v-if="form.validationErrors.otp_resend_cooldown_seconds">{{ form.validationErrors.otp_resend_cooldown_seconds[0] }}</small></label>
                    </div>
                </section>
                <SettingsSaveBar :dirty="form.dirty" :saving="form.saving" :confirmation="form.confirmation" @save="openSaveConfirmation" @reset="openResetConfirmation" @confirm="form.confirmConfirmation" @cancel="form.cancelConfirmation" />
            </template>
        </div>
    </AdminSettingsLayout>
</template>

<script setup>
import { onMounted } from 'vue';
import AdminSettingsLayout from './AdminSettingsLayout.vue';
import SettingsIntro from './components/SettingsIntro.vue';
import SettingsSaveBar from './components/SettingsSaveBar.vue';
import { useAdminSettingsForm } from '../../../features/settings/admin/composables/useAdminSettingsForm.js';

const form = useAdminSettingsForm('security');
const passwordRules = [
    { key: 'require_uppercase', title: 'Require uppercase letter', description: 'A–Z must appear in the password.' },
    { key: 'require_lowercase', title: 'Require lowercase letter', description: 'a–z must appear in the password.' },
    { key: 'require_number', title: 'Require a number', description: 'At least one numeric character is required.' },
    { key: 'require_special_character', title: 'Require a special character', description: 'At least one non-alphanumeric character is required.' },
];

function openSaveConfirmation() {
    form.openSaveConfirmation('Are you sure you want to save these changes? The updated security policy will apply to future sign-in, registration and verification requests.');
}
function openResetConfirmation() {
    form.openResetConfirmation('Are you sure you want to reset Security settings to their default values? This will replace the current security configuration.');
}
onMounted(form.load);
</script>
