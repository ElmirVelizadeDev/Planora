<template>
    <main class="auth-page page-login">
        <section class="auth-card" aria-labelledby="login-title">
            <RouterLink class="auth-back-home" :to="{ name: 'home' }"><span aria-hidden="true">←</span>Back to Home</RouterLink>
            <p class="auth-eyebrow">{{ challenge ? 'Two-step verification' : enrollment ? 'Administrator MFA setup' : 'Welcome back' }}</p>
            <h1 id="login-title">{{ enrollmentComplete ? 'Save your recovery codes' : enrollment ? 'Set up administrator MFA' : challenge ? 'Verify your identity' : 'Sign in to Planora' }}</h1>
            <p class="auth-description">{{ enrollmentComplete ? 'These codes are shown once. Store them securely before continuing.' : enrollment ? 'Scan this QR code with your authenticator app, then enter its six-digit code.' : challenge ? 'Enter your authenticator code or a one-time recovery code.' : 'Enter your details to continue to your workspace.' }}</p>
            <img v-if="enrollment && enrollmentQr" class="auth-admin-mfa-qr" :src="enrollmentQr" alt="Administrator authenticator setup QR code">
            <ul v-if="enrollmentComplete" class="auth-admin-mfa-recovery"><li v-for="code in recoveryCodes" :key="code">{{ code }}</li></ul>
            <p v-if="$route.query.reset === 'success'" class="auth-success" role="status">Your password has been updated. Sign in with your new password.</p>

            <form class="auth-form" @submit.prevent="submit">
                <label v-if="!challenge && !enrollment && !enrollmentComplete" class="auth-field">
                    <span>Email address</span>
                    <input v-model.trim="email" type="email" name="email" autocomplete="username" required maxlength="255" :aria-invalid="Boolean(fieldErrors.email)" :aria-describedby="fieldErrors.email ? 'login-email-error' : undefined" @input="clearFieldError('email')">
                    <small v-if="fieldErrors.email" id="login-email-error" class="auth-field-error">{{ fieldErrors.email[0] }}</small>
                </label>
                <div v-if="!challenge && !enrollment && !enrollmentComplete" class="auth-field">
                    <label for="login-password">Password</label>
                    <div class="auth-password-control">
                        <input id="login-password" v-model="password" :type="showPassword ? 'text' : 'password'" name="password" autocomplete="current-password" required maxlength="128" :aria-invalid="Boolean(fieldErrors.password)" :aria-describedby="fieldErrors.password ? 'login-password-error' : undefined" @input="clearFieldError('password')">
                        <button class="auth-password-toggle" type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword" @click="showPassword = !showPassword">
                            <svg v-if="!showPassword" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.6"/></svg>
                            <svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.8 10.8 0 0 1 12 6c6.1 0 9.5 6 9.5 6a15 15 0 0 1-3.1 3.6M6.2 6.2C3.8 7.7 2.5 12 2.5 12s3.4 6 9.5 6c1.2 0 2.3-.2 3.3-.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                        </button>
                    </div>
                    <small v-if="fieldErrors.password" id="login-password-error" class="auth-field-error">{{ fieldErrors.password[0] }}</small>
                </div>
                <label v-else-if="challenge || enrollment" class="auth-field"><span>{{ challenge && recoveryMode ? 'Recovery code' : 'Authenticator code' }}</span><input v-model.trim="verificationCode" type="text" :inputmode="challenge && recoveryMode ? 'text' : 'numeric'" autocomplete="one-time-code" :maxlength="challenge && recoveryMode ? 10 : 6" required autofocus></label>
                <div v-if="!challenge && !enrollment && !enrollmentComplete" class="auth-login-options">
                    <label class="auth-remember-control" for="login-remember">
                        <input id="login-remember" v-model="remember" type="checkbox" name="remember">
                        <span class="auth-remember-check" aria-hidden="true"></span>
                        <span>Remember me</span>
                    </label>
                    <RouterLink class="auth-inline-link" :to="{ name: 'forgot-password' }">Forgot password?</RouterLink>
                </div>
                <button v-if="challenge" class="auth-inline-link" type="button" @click="recoveryMode = !recoveryMode; verificationCode = ''; errorMessage = ''">Use {{ recoveryMode ? 'authenticator' : 'recovery' }} code</button>
                <p v-if="errorMessage" class="auth-error" role="alert"><span class="auth-alert-icon" aria-hidden="true">!</span><span>{{ errorMessage }}</span></p>
                <button class="auth-submit" type="submit" :disabled="loading" :aria-busy="loading">
                    <span v-if="loading" class="auth-spinner" aria-hidden="true"></span>
                    <span>{{ loading ? 'Verifying…' : enrollmentComplete ? 'Continue to dashboard' : enrollment ? 'Enable MFA and continue' : challenge ? 'Verify and continue' : 'Sign in to Planora' }}</span><span v-if="!loading" aria-hidden="true">→</span>
                </button>
                <button v-if="challenge" class="auth-inline-link" type="button" :disabled="loading" @click="challenge = false; password = ''; verificationCode = ''">Back to sign in</button>
            </form>

            <p class="auth-switch">Don't have an account? <RouterLink :to="{ name: 'register' }">Create one</RouterLink></p>
        </section>
    </main>
</template>

<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import { safeInternalRedirect } from '../../shared/utils/safeRedirect.js';
import { rateLimitMessage } from '../../shared/utils/httpError.js';

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();
const email = ref('');
const password = ref('');
const showPassword = ref(false);
const remember = ref(false);
const loading = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});
const challenge = ref(false);
const recoveryMode = ref(false);
const verificationCode = ref('');
const enrollment = ref(false);
const enrollmentQr = ref('');
const enrollmentComplete = ref(false);
const recoveryCodes = ref([]);

function clearFieldError(field) {
    if (fieldErrors.value[field]) {
        const nextErrors = { ...fieldErrors.value };
        delete nextErrors[field];
        fieldErrors.value = nextErrors;
    }
    errorMessage.value = '';
}

async function submit() {
    loading.value = true;
    errorMessage.value = '';
    fieldErrors.value = {};
    const requestedDestination = safeInternalRedirect(route.query.redirect, router);

    try {
        if (enrollmentComplete.value) {
            await router.replace('/admin/dashboard');
            return;
        }
        if (enrollment.value) {
            const result = await auth.verifyAdminMfaLoginEnrollment(verificationCode.value);
            recoveryCodes.value = result.recoveryCodes;
            enrollment.value = false;
            enrollmentComplete.value = true;
            verificationCode.value = '';
            return;
        }
        if (challenge.value) {
            const result = await auth.verifyAdminMfaLogin(recoveryMode.value ? { recovery_code: verificationCode.value } : { code: verificationCode.value });
            await router.replace(requestedDestination || result.redirectTo || { name: 'admin.dashboard' });
            return;
        }
        const result = await auth.login({ email: email.value, password: password.value, remember: remember.value });
        if (result.mfaRequired) {
            challenge.value = true;
            password.value = '';
            return;
        }
        if (result.mfaEnrollmentRequired) {
            const setup = await auth.beginAdminMfaLoginEnrollment();
            enrollmentQr.value = setup.qr_code_data_uri;
            enrollment.value = true;
            password.value = '';
            return;
        }
        await router.replace(requestedDestination || result.redirectTo || { name: 'error.403' });
    } catch (error) {
        fieldErrors.value = error.response?.data?.errors || {};
        if ((challenge.value || enrollment.value) && error.response?.status === 401) {
            challenge.value = false;
            enrollment.value = false;
            enrollmentQr.value = '';
            verificationCode.value = '';
            errorMessage.value = 'That verification challenge expired. Sign in again to continue.';
        } else if (error.response?.status === 429) {
            errorMessage.value = rateLimitMessage(error, 'Too many sign-in attempts.');
        } else errorMessage.value = error.response?.status === 403
            ? (error.response.data.message || 'This account is not authorized to sign in.')
            : (error.response?.data?.message === 'The given data was invalid.'
                ? ''
                : error.response?.data?.message || 'We could not sign you in. Check your details and try again.');
    } finally {
        loading.value = false;
    }
}

onBeforeUnmount(() => { enrollmentQr.value = ''; recoveryCodes.value = []; verificationCode.value = ''; });
</script>

<style src="../../../css/pages/auth/login.css"></style>
<style scoped>
.auth-admin-mfa-qr{display:block;width:240px;height:240px;max-width:100%;margin:18px auto;border:1px solid var(--color-border);border-radius:8px;background:#fff;image-rendering:pixelated}.auth-admin-mfa-recovery{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;padding:0;list-style:none}.auth-admin-mfa-recovery li{padding:8px;border:1px solid var(--color-border);border-radius:6px;font-family:monospace;text-align:center}
</style>
