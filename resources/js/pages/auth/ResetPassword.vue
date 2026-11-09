<template>
    <main class="auth-page page-reset-password">
        <section class="auth-card auth-recovery-card" aria-labelledby="reset-password-title">
            <div class="auth-status-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 20 6v5c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6l8-3Z"/><path d="m9 12 2 2 4-4"/></svg></div>
            <p class="auth-eyebrow">Password reset</p>
            <h1 id="reset-password-title">Choose a new password</h1>
            <p class="auth-description">Create a secure password for your Planora account.</p>

            <form class="auth-form" @submit.prevent="submit">
                <div class="auth-field"><label for="recovery-password">New password</label><div class="auth-password-control"><input id="recovery-password" v-model="password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" minlength="8" maxlength="128" required :aria-invalid="Boolean(fieldErrors.password)" @input="clearErrors"><button class="auth-password-toggle" type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword" @click="showPassword = !showPassword"><svg v-if="!showPassword" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.6"/></svg><svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.8 10.8 0 0 1 12 6c6.1 0 9.5 6 9.5 6a15 15 0 0 1-3.1 3.6M6.2 6.2C3.8 7.7 2.5 12 2.5 12s3.4 6 9.5 6c1.2 0 2.3-.2 3.3-.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg></button></div><small v-if="fieldErrors.password" class="auth-field-error">{{ fieldErrors.password[0] }}</small></div>
                <div class="auth-field"><label for="recovery-password-confirmation">Confirm new password</label><div class="auth-password-control"><input id="recovery-password-confirmation" v-model="confirmation" :type="showConfirmation ? 'text' : 'password'" autocomplete="new-password" minlength="8" maxlength="128" required :aria-invalid="Boolean(fieldErrors.password_confirmation || mismatch)" @input="clearErrors"><button class="auth-password-toggle" type="button" :aria-label="showConfirmation ? 'Hide password confirmation' : 'Show password confirmation'" :aria-pressed="showConfirmation" @click="showConfirmation = !showConfirmation"><svg v-if="!showConfirmation" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.6"/></svg><svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.8 10.8 0 0 1 12 6c6.1 0 9.5 6 9.5 6a15 15 0 0 1-3.1 3.6M6.2 6.2C3.8 7.7 2.5 12 2.5 12s3.4 6 9.5 6c1.2 0 2.3-.2 3.3-.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg></button></div><small v-if="fieldErrors.password_confirmation" class="auth-field-error">{{ fieldErrors.password_confirmation[0] }}</small><small v-else-if="mismatch" class="auth-field-error">Passwords do not match.</small><small v-else-if="confirmation && !mismatch" class="auth-password-match">Passwords match.</small></div>
                <p v-if="errorMessage" class="auth-error" role="alert"><span class="auth-alert-icon" aria-hidden="true">!</span><span>{{ errorMessage }}</span></p>
                <button class="auth-submit" type="submit" :disabled="loading || mismatch" :aria-busy="loading"><span v-if="loading" class="auth-spinner" aria-hidden="true"></span><span>{{ loading ? 'Updating password…' : 'Update password' }}</span><span v-if="!loading" aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch"><RouterLink :to="{ name: 'forgot-password' }">← Start recovery again</RouterLink></p>
        </section>
    </main>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { resetPassword } from '../../features/auth/services/authApi.js';

const router = useRouter();
const password = ref('');
const confirmation = ref('');
const showPassword = ref(false);
const showConfirmation = ref(false);
const loading = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});
const mismatch = computed(() => Boolean(confirmation.value) && password.value !== confirmation.value);

function clearErrors() { fieldErrors.value = {}; errorMessage.value = ''; }
async function submit() {
    clearErrors();
    if (password.value !== confirmation.value) { fieldErrors.value = { password_confirmation: ['The password confirmation does not match.'] }; return; }
    loading.value = true;
    try {
        await resetPassword({ password: password.value, password_confirmation: confirmation.value });
        await router.replace({ name: 'login', query: { reset: 'success' } });
    } catch (error) {
        fieldErrors.value = error.response?.data?.errors || {};
        if (fieldErrors.value.recovery_token) {
            errorMessage.value = fieldErrors.value.recovery_token[0];
            window.setTimeout(() => router.replace({ name: 'forgot-password' }), 1800);
        } else if (!Object.keys(fieldErrors.value).length) errorMessage.value = error.response?.data?.message || 'We could not update your password. Please try again.';
    } finally { loading.value = false; }
}
</script>

<style src="../../../css/pages/auth/reset-password.css"></style>
