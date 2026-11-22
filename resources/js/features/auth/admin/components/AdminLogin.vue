<template>
    <main class="admin-login-page">
        <section class="admin-login-card" aria-labelledby="admin-login-title">
            <div class="admin-login-brand" aria-label="Planora">
                <img :src="'/images/branding/planora-master-512.png'" alt="" width="34" height="34">
                <span>Planora</span>
            </div>
            <h1 id="admin-login-title">{{ challenge ? 'Verify your identity' : 'Administrator Sign In' }}</h1>
            <p class="admin-login-description">{{ challenge ? 'Enter the code from your authenticator app or a recovery code.' : 'Sign in to access the Planora administration panel.' }}</p>

            <form class="admin-login-form" @submit.prevent="submit">
                <label v-if="!challenge" class="admin-login-field">
                    <span>Email address</span>
                    <input v-model.trim="email" type="email" name="email" autocomplete="username" required maxlength="255" :aria-invalid="Boolean(fieldErrors.email)" :aria-describedby="fieldErrors.email ? 'admin-email-error' : undefined" @input="clearFieldError('email')">
                    <small v-if="fieldErrors.email" id="admin-email-error" class="admin-login-field-error">{{ fieldErrors.email[0] }}</small>
                </label>
                <div v-if="!challenge" class="admin-login-field">
                    <label for="admin-login-password">Password</label>
                    <div class="admin-login-password-control">
                        <input id="admin-login-password" v-model="password" :type="showPassword ? 'text' : 'password'" name="password" autocomplete="current-password" required maxlength="128" :aria-invalid="Boolean(fieldErrors.password)" :aria-describedby="fieldErrors.password ? 'admin-password-error' : undefined" @input="clearFieldError('password')">
                        <button class="admin-login-password-toggle" type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword" @click="showPassword = !showPassword">{{ showPassword ? 'Hide' : 'Show' }}</button>
                    </div>
                    <small v-if="fieldErrors.password" id="admin-password-error" class="admin-login-field-error">{{ fieldErrors.password[0] }}</small>
                </div>
                <label v-else class="admin-login-field">
                    <span>{{ recoveryMode ? 'Recovery code' : 'Authenticator code' }}</span>
                    <input v-model.trim="verificationCode" :type="recoveryMode ? 'text' : 'text'" inputmode="numeric" autocomplete="one-time-code" :maxlength="recoveryMode ? 10 : 6" required autofocus>
                </label>
                <button v-if="challenge" class="admin-login-password-toggle" type="button" @click="recoveryMode = !recoveryMode; verificationCode = ''; errorMessage = ''">Use {{ recoveryMode ? 'authenticator' : 'recovery' }} code</button>
                <p v-if="errorMessage" class="admin-login-error" role="alert">{{ errorMessage }}</p>
                <button class="admin-login-submit" type="submit" :disabled="loading" :aria-busy="loading">
                    <span v-if="loading" class="admin-login-spinner" aria-hidden="true"></span>
                    <span>{{ loading ? 'Verifying…' : challenge ? 'Verify and continue' : 'Sign In' }}</span>
                </button>
                <button v-if="challenge" class="admin-login-password-toggle" type="button" :disabled="loading" @click="challenge = false; verificationCode = ''; password = ''">Back to sign in</button>
            </form>
        </section>
    </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
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
        if (!auth.hasRole('admin')) {
            auth.clearUser();
            errorMessage.value = 'This account is not authorized to access administration.';
            return;
        }
        await router.replace(result.redirectTo || { name: 'admin.dashboard' });
    } catch (error) {
        fieldErrors.value = error.response?.data?.errors || {};
        if (challenge.value && error.response?.status === 401) {
            challenge.value = false;
            verificationCode.value = '';
            errorMessage.value = 'That verification challenge expired. Sign in again to continue.';
        } else if (error.response?.status === 429) {
            errorMessage.value = rateLimitMessage(error, 'Too many sign-in attempts. Please wait before trying again.');
        } else {
            errorMessage.value = 'We could not verify this administrator account. Check your details and try again.';
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
</script>

<style src="../../../../../css/pages/auth/admin-login.css"></style>
