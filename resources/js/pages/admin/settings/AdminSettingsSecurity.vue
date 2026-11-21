<template>
    <AdminSettingsLayout page-title="Security settings" page-description="Set the rules used for future sign-ins, passwords and verification codes.">
        <div class="admin-settings-detail" :aria-busy="form.loading">
            <SettingsIntro title="Security policies" description="These controls affect future registration, password recovery and login attempts. Existing passwords and active sessions are not changed." tone="is-green" />
            <p v-if="form.error" class="settings-feedback is-error" role="alert">{{ form.error }} <button v-if="!form.dirty" type="button" @click="form.load">Retry</button></p>
            <p v-if="form.notice" class="settings-feedback is-success" role="status">{{ form.notice }}</p>
            <section v-if="form.loading" class="admin-settings-section settings-loading" aria-label="Loading settings"><span></span><span></span></section>
            <template v-else-if="form.loaded">
                <section class="admin-settings-section settings-form-card" aria-labelledby="admin-mfa-title">
                    <header class="settings-form-heading"><div><h2 id="admin-mfa-title">Administrator multi-factor authentication</h2><p>Add an authenticator code to protect administrator sign-ins and sensitive changes.</p></div><span class="admin-mfa-state" :class="{ 'is-enabled': mfaStatus.enabled }">{{ mfaStatus.enabled ? 'Enabled' : 'Not enabled' }}</span></header>
                    <p v-if="mfaError" class="settings-feedback is-error" role="alert">{{ mfaError }}</p>
                    <p v-if="mfaNotice" class="settings-feedback is-success" role="status">{{ mfaNotice }}</p>
                    <template v-if="!mfaStatus.enabled && !setup.secret">
                        <form class="admin-mfa-form" @submit.prevent="beginEnrollment"><label class="settings-field"><span>Confirm your password to start setup</span><input v-model="setup.password" type="password" autocomplete="current-password" required maxlength="128"></label><button class="settings-button settings-button--primary" type="submit" :disabled="mfaBusy">{{ mfaBusy ? 'Starting…' : 'Set up authenticator' }}</button></form>
                    </template>
                    <template v-else-if="setup.secret">
                        <img class="admin-mfa-qr" :src="setup.qrCodeDataUri" alt="Scan this QR code with your authenticator app">
                        <p>Enter this key manually in your authenticator app. It is shown only during setup.</p>
                        <code class="admin-mfa-secret">{{ setup.secret }}</code>
                        <p class="admin-mfa-uri-label">Setup URI</p><code class="admin-mfa-uri">{{ setup.otpauthUri }}</code>
                        <form class="admin-mfa-form" @submit.prevent="enableMfa"><label class="settings-field"><span>Enter the six-digit authenticator code</span><input v-model.trim="setup.code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required></label><div class="admin-mfa-actions"><button class="settings-button" type="button" :disabled="mfaBusy" @click="cancelSetup">Cancel setup</button><button class="settings-button settings-button--primary" type="submit" :disabled="mfaBusy">{{ mfaBusy ? 'Verifying…' : 'Verify and enable' }}</button></div></form>
                    </template>
                    <template v-else-if="mfaStatus.enabled">
                        <p>MFA is active for this administrator account. Store recovery codes securely; they are displayed only once.</p>
                        <div class="admin-mfa-actions"><button class="settings-button" type="button" :disabled="mfaBusy" @click="regenerateCodes">Regenerate recovery codes</button><button class="settings-button settings-button--danger" type="button" :disabled="mfaBusy" @click="disableMfa">Disable MFA</button></div>
                    </template>
                    <div v-if="recoveryCodes.length" class="admin-mfa-recovery" role="region" aria-label="New recovery codes"><strong>Save these recovery codes now</strong><p>Each code can be used once. They will not be shown again.</p><code v-for="code in recoveryCodes" :key="code">{{ code }}</code><button class="settings-button" type="button" @click="recoveryCodes = []">I have saved these codes</button></div>
                </section>
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
import { onBeforeUnmount, onMounted } from 'vue';
import AdminSettingsLayout from './AdminSettingsLayout.vue';
import SettingsIntro from './components/SettingsIntro.vue';
import SettingsSaveBar from './components/SettingsSaveBar.vue';
import { useAdminSettingsForm } from '../../../features/settings/admin/composables/useAdminSettingsForm.js';
import { cancelAdminMfaEnrollment, disableAdminMfa as disableMfaRequest, fetchAdminMfaStatus, regenerateAdminMfaRecoveryCodes, startAdminMfaEnrollment, verifyAdminMfaEnrollment } from '../../../features/auth/admin/services/adminMfaApi.js';
import { reactive, ref } from 'vue';

const form = useAdminSettingsForm('security');
const passwordRules = [
    { key: 'require_uppercase', title: 'Require uppercase letter', description: 'A–Z must appear in the password.' },
    { key: 'require_lowercase', title: 'Require lowercase letter', description: 'a–z must appear in the password.' },
    { key: 'require_number', title: 'Require a number', description: 'At least one numeric character is required.' },
    { key: 'require_special_character', title: 'Require a special character', description: 'At least one non-alphanumeric character is required.' },
];
const mfaStatus = reactive({ enabled: false });
const setup = reactive({ password: '', secret: '', otpauthUri: '', qrCodeDataUri: '', code: '' });
const recoveryCodes = ref([]);
const mfaError = ref('');
const mfaNotice = ref('');
const mfaBusy = ref(false);

async function loadMfaStatus() {
    try { Object.assign(mfaStatus, (await fetchAdminMfaStatus()).data); }
    catch { mfaError.value = 'Could not load administrator MFA status.'; }
}
async function beginEnrollment() {
    mfaBusy.value = true; mfaError.value = '';
    try { const data = (await startAdminMfaEnrollment(setup.password)).data; Object.assign(setup, { secret: data.secret, otpauthUri: data.otpauth_uri, qrCodeDataUri: data.qr_code_data_uri }); setup.password = ''; }
    catch (error) { mfaError.value = error.response?.data?.errors?.password?.[0] || error.response?.data?.message || 'MFA setup could not be started.'; }
    finally { mfaBusy.value = false; }
}
async function enableMfa() {
    mfaBusy.value = true; mfaError.value = '';
    try { const { data } = await verifyAdminMfaEnrollment(setup.code); recoveryCodes.value = data.recovery_codes; mfaStatus.enabled = true; Object.assign(setup, { secret: '', otpauthUri: '', qrCodeDataUri: '', code: '' }); mfaNotice.value = 'MFA is enabled.'; }
    catch (error) { mfaError.value = error.response?.data?.errors?.code?.[0] || error.response?.data?.message || 'MFA could not be enabled.'; }
    finally { mfaBusy.value = false; }
}
async function cancelSetup() {
    try { await cancelAdminMfaEnrollment(); }
    catch { mfaError.value = 'The pending MFA setup could not be cleared on the server.'; }
    finally { Object.assign(setup, { secret: '', otpauthUri: '', qrCodeDataUri: '', code: '', password: '' }); }
}
async function regenerateCodes() {
    mfaBusy.value = true; mfaError.value = '';
    try { recoveryCodes.value = (await regenerateAdminMfaRecoveryCodes()).data.recovery_codes; mfaNotice.value = 'Previous recovery codes were invalidated.'; }
    catch (error) { mfaError.value = error.response?.data?.message || 'Recovery codes could not be regenerated.'; }
    finally { mfaBusy.value = false; }
}
async function disableMfa() {
    mfaBusy.value = true; mfaError.value = '';
    try { await disableMfaRequest(); mfaStatus.enabled = false; recoveryCodes.value = []; mfaNotice.value = 'MFA has been disabled.'; }
    catch (error) { mfaError.value = error.response?.data?.message || 'MFA could not be disabled.'; }
    finally { mfaBusy.value = false; }
}

function openSaveConfirmation() {
    form.openSaveConfirmation('Are you sure you want to save these changes? The updated security policy will apply to future sign-in, registration and verification requests.');
}
function openResetConfirmation() {
    form.openResetConfirmation('Are you sure you want to reset Security settings to their default values? This will replace the current security configuration.');
}
onMounted(() => { form.load(); loadMfaStatus(); });
onBeforeUnmount(() => { Object.assign(setup, { password: '', secret: '', otpauthUri: '', qrCodeDataUri: '', code: '' }); recoveryCodes.value = []; });
</script>

<style scoped>
.admin-mfa-state{padding:5px 10px;border-radius:999px;background:var(--color-surface-hover);color:var(--color-text-secondary);font-size:12px;font-weight:650;white-space:nowrap}.admin-mfa-state.is-enabled{background:var(--color-success-subtle);color:var(--color-success)}
.admin-mfa-qr{display:block;width:256px;height:256px;max-width:100%;margin:16px 0;border:1px solid var(--color-border);border-radius:8px;background:#fff;image-rendering:pixelated}
.admin-mfa-form{display:grid;gap:12px;max-width:560px;margin-top:18px}.admin-mfa-form input{box-sizing:border-box;width:100%;min-height:42px;padding:0 12px;border:1px solid var(--color-input-border);border-radius:9px;background:var(--color-input-background);color:var(--color-text);font:inherit}
.admin-mfa-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}.admin-mfa-secret,.admin-mfa-uri,.admin-mfa-recovery code{display:block;max-width:100%;overflow-wrap:anywhere;padding:12px;border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface);color:var(--color-text);font-size:13px}.admin-mfa-uri-label{margin-bottom:6px}.admin-mfa-recovery{display:grid;gap:8px;margin-top:20px;padding:16px;border:1px solid var(--color-border);border-radius:10px}.admin-mfa-recovery p{margin:0}
.settings-button--danger{color:var(--color-danger)}
</style>
