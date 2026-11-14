<template>
    <AdminSettingsLayout page-title="Email settings" page-description="Configure the application mail transport and verify it before relying on delivery.">
        <div class="admin-settings-detail" :aria-busy="form.loading">
            <SettingsIntro title="Email delivery" description="Credentials are encrypted at rest and never returned by the settings API." tone="is-blue" />
            <p v-if="form.error" class="settings-feedback is-error" role="alert">{{ form.error }} <button v-if="!form.dirty" type="button" @click="form.load">Retry</button></p>
            <p v-if="form.notice" class="settings-feedback is-success" role="status">{{ form.notice }}</p>
            <section v-if="form.loading" class="admin-settings-section settings-loading" aria-label="Loading settings"><span></span><span></span></section>
            <template v-else-if="form.loaded">
                <section class="admin-settings-section settings-form-card" aria-labelledby="settings-email-transport-title">
                    <header class="settings-form-heading"><div><h2 id="settings-email-transport-title">Mail transport</h2><p>Select a supported Laravel mailer and set the connection information.</p></div><span class="settings-status" :class="form.values.mail_password_configured ? 'is-ready' : 'is-pending'">{{ form.values.mail_password_configured ? 'Credential saved' : 'Credential not set' }}</span></header>
                    <div class="settings-form-grid">
                        <label class="settings-field"><span>Mailer</span><select v-model="form.values.mail_mailer"><option value="smtp">SMTP</option><option value="sendmail">Sendmail</option><option value="log">Log (development)</option><option value="array">Array (testing)</option></select><small v-if="form.validationErrors.mail_mailer">{{ form.validationErrors.mail_mailer[0] }}</small></label>
                        <label class="settings-field"><span>SMTP host</span><input v-model="form.values.mail_host" maxlength="255" autocomplete="off" placeholder="smtp.example.com"><small v-if="form.validationErrors.mail_host">{{ form.validationErrors.mail_host[0] }}</small></label>
                        <label class="settings-field"><span>SMTP port</span><input v-model.number="form.values.mail_port" type="number" min="1" max="65535"><small v-if="form.validationErrors.mail_port">{{ form.validationErrors.mail_port[0] }}</small></label>
                        <label class="settings-field"><span>Encryption</span><select v-model="form.values.mail_encryption"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option></select><small v-if="form.validationErrors.mail_encryption">{{ form.validationErrors.mail_encryption[0] }}</small></label>
                        <label class="settings-field"><span>SMTP username</span><input v-model="form.values.mail_username" maxlength="255" autocomplete="off"><small v-if="form.validationErrors.mail_username">{{ form.validationErrors.mail_username[0] }}</small></label>
                        <label class="settings-field"><span>SMTP password</span><input v-model="form.values.mail_password" type="password" maxlength="1000" autocomplete="new-password" :placeholder="form.values.mail_password_configured ? 'Saved securely — leave blank to keep it' : 'Enter SMTP password'"><small v-if="form.validationErrors.mail_password" role="alert">{{ form.validationErrors.mail_password[0] }}</small></label>
                        <label class="settings-field"><span>From address</span><input v-model="form.values.mail_from_address" type="email" maxlength="255" autocomplete="email"><small v-if="form.validationErrors.mail_from_address">{{ form.validationErrors.mail_from_address[0] }}</small></label>
                        <label class="settings-field"><span>From name</span><input v-model="form.values.mail_from_name" maxlength="100" autocomplete="organization"><small v-if="form.validationErrors.mail_from_name">{{ form.validationErrors.mail_from_name[0] }}</small></label>
                    </div>
                    <label v-if="form.values.mail_password_configured" class="settings-checkbox-danger"><input v-model="form.values.remove_mail_password" type="checkbox">Remove the saved SMTP credential</label>
                </section>

                <section class="admin-settings-section settings-form-card" aria-labelledby="settings-email-test-title">
                    <header class="settings-form-heading"><div><h2 id="settings-email-test-title">Send a test email</h2><p>Tests the currently saved transport configuration. Save changes before testing.</p></div></header>
                    <div class="settings-test-email"><label class="settings-field"><span>Recipient</span><input v-model="recipient" type="email" autocomplete="email" placeholder="admin@example.com"></label><button class="settings-button settings-button--secondary" type="button" :disabled="testing || form.dirty || !recipient" @click="testEmail">{{ testing ? 'Sending…' : 'Send test email' }}</button></div>
                    <p v-if="testMessage" class="settings-feedback" :class="testError ? 'is-error' : 'is-success'" :role="testError ? 'alert' : 'status'">{{ testMessage }}</p>
                </section>
                <SettingsSaveBar :dirty="form.dirty" :saving="form.saving" :confirmation="form.confirmation" @save="openSaveConfirmation" @reset="openResetConfirmation" @confirm="form.confirmConfirmation" @cancel="form.cancelConfirmation" />
            </template>
        </div>
    </AdminSettingsLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import AdminSettingsLayout from './AdminSettingsLayout.vue';
import SettingsIntro from './components/SettingsIntro.vue';
import SettingsSaveBar from './components/SettingsSaveBar.vue';
import { sendSettingsTestEmail } from '../../../features/settings/services/settingsApi.js';
import { useAdminSettingsForm } from '../../../features/settings/admin/composables/useAdminSettingsForm.js';

const form = useAdminSettingsForm('email');
const recipient = ref('');
const testing = ref(false);
const testMessage = ref('');
const testError = ref(false);

function openSaveConfirmation() {
    const credentialsChanged = Boolean(form.values.mail_password || form.values.remove_mail_password);
    const message = credentialsChanged
        ? 'Are you sure you want to save these Email settings? Credential changes affect future application email.'
        : 'Are you sure you want to save your updated Email settings?';
    form.openSaveConfirmation(message, () => form.mergeSavedValues({ mail_password: '', remove_mail_password: false }));
}
function openResetConfirmation() {
    form.openResetConfirmation('Are you sure you want to reset Email settings to their default values? This will also remove the saved SMTP credential.', () => form.mergeSavedValues({ mail_password: '', remove_mail_password: false }));
}
async function testEmail() {
    testing.value = true; testMessage.value = ''; testError.value = false;
    try {
        const response = await sendSettingsTestEmail(recipient.value.trim());
        testMessage.value = response.message;
    } catch (exception) {
        testError.value = true;
        testMessage.value = exception.response?.data?.message || 'The test email could not be sent.';
    } finally { testing.value = false; }
}
onMounted(async () => {
    await form.load();
    if (form.loaded) form.mergeSavedValues({ mail_password: '', remove_mail_password: false });
});
</script>
