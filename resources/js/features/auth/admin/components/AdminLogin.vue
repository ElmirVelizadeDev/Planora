<template>
    <main class="admin-login-page">
        <section class="admin-login-card" aria-labelledby="admin-login-title">
            <div class="admin-login-brand" aria-label="Planora">
                <img :src="'/images/branding/planora-master-512.png'" alt="" width="34" height="34">
                <span>Planora</span>
            </div>
            <h1 id="admin-login-title">{{ enrollmentComplete ? 'Save your recovery codes' : enrollment ? 'Set up administrator MFA' : challenge ? 'Verify your identity' : 'Administrator Sign In' }}</h1>
            <p class="admin-login-description">{{ enrollmentComplete ? 'These codes are shown once. Store them securely before continuing.' : enrollment ? 'Scan this QR code with your authenticator app, then enter its six-digit code.' : challenge ? 'Enter the code from your authenticator app or a recovery code.' : 'Sign in to access the Planora administration panel.' }}</p>
            <div v-if="enrollment && !enrollmentComplete" class="admin-login-mfa-setup"><img v-if="enrollmentQr" :src="enrollmentQr" alt="Administrator authenticator setup QR code"></div>
            <ul v-if="enrollmentComplete" class="admin-login-recovery-codes"><li v-for="code in recoveryCodes" :key="code">{{ code }}</li></ul>

            <form class="admin-login-form" @submit.prevent="submit">
                <label v-if="!challenge && !enrollment && !enrollmentComplete" class="admin-login-field">
                    <span>Email address</span>
                    <input v-model.trim="email" type="email" name="email" autocomplete="username" required maxlength="255" :aria-invalid="Boolean(fieldErrors.email)" :aria-describedby="fieldErrors.email ? 'admin-email-error' : undefined" @input="clearFieldError('email')">
                    <small v-if="fieldErrors.email" id="admin-email-error" class="admin-login-field-error">{{ fieldErrors.email[0] }}</small>
                </label>
                <div v-if="!challenge && !enrollment && !enrollmentComplete" class="admin-login-field">
                    <label for="admin-login-password">Password</label>
                    <div class="admin-login-password-control">
                        <input id="admin-login-password" v-model="password" :type="showPassword ? 'text' : 'password'" name="password" autocomplete="current-password" required maxlength="128" :aria-invalid="Boolean(fieldErrors.password)" :aria-describedby="fieldErrors.password ? 'admin-password-error' : undefined" @input="clearFieldError('password')">
                        <button class="admin-login-password-toggle" type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword" @click="showPassword = !showPassword">{{ showPassword ? 'Hide' : 'Show' }}</button>
                    </div>
                    <small v-if="fieldErrors.password" id="admin-password-error" class="admin-login-field-error">{{ fieldErrors.password[0] }}</small>
                </div>
                <label v-else-if="challenge || enrollment" class="admin-login-field">
                    <span>{{ recoveryMode ? 'Recovery code' : 'Authenticator code' }}</span>
                    <input v-model.trim="verificationCode" :type="recoveryMode ? 'text' : 'text'" inputmode="numeric" autocomplete="one-time-code" :maxlength="recoveryMode ? 10 : 6" required autofocus>
                </label>
                <button v-if="challenge" class="admin-login-password-toggle" type="button" @click="recoveryMode = !recoveryMode; verificationCode = ''; errorMessage = ''">Use {{ recoveryMode ? 'authenticator' : 'recovery' }} code</button>
                <p v-if="errorMessage" class="admin-login-error" role="alert">{{ errorMessage }}</p>
                <button class="admin-login-submit" type="submit" :disabled="loading" :aria-busy="loading">
                    <span v-if="loading" class="admin-login-spinner" aria-hidden="true"></span>
                    <span>{{ loading ? 'Verifying…' : enrollmentComplete ? 'Continue to dashboard' : enrollment ? 'Enable MFA and continue' : challenge ? 'Verify and continue' : 'Sign In' }}</span>
                </button>
                <button v-if="challenge" class="admin-login-password-toggle" type="button" :disabled="loading" @click="challenge = false; verificationCode = ''; password = ''">Back to sign in</button>
            </form>
        </section>
    </main>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/authStore.js';
import { rateLimitMessage } from '../../../../shared/utils/httpError.js';

const router = useRouter();
const auth = useAuthStore();
const email = ref('');
const password = ref('');
const showPassword = ref(false);
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
            await router.replace(result.redirectTo || { name: 'admin.dashboard' });
            return;
        }
        const result = await auth.loginAsAdministrator({ email: email.value, password: password.value });
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
        if (!auth.hasRole('admin')) {
            auth.clearUser();
            errorMessage.value = 'This account is not authorized to access administration.';
            return;
        }
        await router.replace(result.redirectTo || { name: 'admin.dashboard' });
    } catch (error) {
        fieldErrors.value = error.response?.data?.errors || {};
        if ((challenge.value || enrollment.value) && error.response?.status === 401) {
            challenge.value = false;
            enrollment.value = false;
            enrollmentQr.value = '';
            verificationCode.value = '';
            errorMessage.value = 'That verification challenge expired. Sign in again to continue.';
        } else if (error.response?.status === 429) {
            errorMessage.value = rateLimitMessage(error, 'Too many sign-in attempts. Please wait before trying again.');
        } else {
            const responseMessage = error.response?.data?.message;
            errorMessage.value = responseMessage === 'The given data was invalid.'
                ? ''
                : responseMessage || 'We could not verify this administrator account. Check your details and try again.';
        }
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    try {
        const user = await auth.fetchCurrentUser();
        if (user?.role === 'admin' && user?.status === 'active') {
            await router.replace({ name: 'admin.dashboard' });
        }
    } catch {
        // The dedicated form remains available when there is no usable session.
    }
});

onBeforeUnmount(() => { enrollmentQr.value = ''; recoveryCodes.value = []; verificationCode.value = ''; });
</script>

<style src="../../../../../css/pages/auth/admin-login.css"></style>
<style scoped>
.admin-login-mfa-setup img{display:block;width:240px;height:240px;max-width:100%;margin:18px auto;border:1px solid var(--color-border);border-radius:8px;background:#fff;image-rendering:pixelated}.admin-login-recovery-codes{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;padding:0;list-style:none}.admin-login-recovery-codes li{padding:8px;border:1px solid var(--color-border);border-radius:6px;font-family:monospace;text-align:center}
</style>
