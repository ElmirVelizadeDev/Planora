<template>
    <main class="auth-page page-register-verify">
        <section class="auth-card auth-verify-card" aria-labelledby="verify-title">
            <p class="auth-eyebrow">Email verification</p>
            <div class="auth-verify-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg>
            </div>
            <h1 id="verify-title">{{ isDatabaseDelivery ? 'Verify your email' : 'Check your inbox' }}</h1>
            <p class="auth-description">
                <template v-if="isDatabaseDelivery">Run <code>php artisan auth:local-otp registration {{ email }}</code> in the project terminal to generate a local six-digit code for <strong>{{ maskedEmail }}</strong>.</template>
                <template v-else>We sent a six-digit verification code to <strong>{{ maskedEmail }}</strong>. Enter it below to activate your account.</template>
            </p>

            <div v-if="!email" class="auth-error" role="alert">
                <span class="auth-alert-icon" aria-hidden="true">!</span><span>Your pending registration email could not be found. Please start registration again.
                <RouterLink :to="{ name: 'register' }">Return to registration</RouterLink></span>
            </div>
            <form v-else class="auth-form auth-verify-form" @submit.prevent="verifyCode">
                <fieldset class="auth-otp-fieldset" :disabled="loading">
                    <legend>Verification code</legend>
                    <div class="auth-otp-inputs" role="group" aria-label="Six digit verification code" @paste.prevent="pasteCode">
                        <input
                            v-for="(_, index) in digits"
                            :key="index"
                            :ref="(element) => { if (element) inputRefs[index] = element; }"
                            :value="digits[index]"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            autocomplete="one-time-code"
                            maxlength="1"
                            :aria-label="`Code digit ${index + 1}`"
                            :aria-invalid="Boolean(errorMessage)"
                            @input="onDigitInput(index, $event)"
                            @keydown="onDigitKeydown(index, $event)"
                        >
                    </div>
                </fieldset>
                <p v-if="errorMessage" class="auth-error" role="alert"><span class="auth-alert-icon" aria-hidden="true">!</span><span>{{ errorMessage }}</span></p>
                <p v-if="successMessage" class="auth-success" role="status"><span class="auth-alert-icon" aria-hidden="true">✓</span><span>{{ successMessage }}</span></p>
                <button class="auth-submit" type="submit" :disabled="loading || digits.join('').length !== 6" :aria-busy="loading">
                    <span v-if="loading" class="auth-spinner" aria-hidden="true"></span>
                    {{ loading ? 'Verifying…' : 'Verify email' }}
                </button>
            </form>

            <div v-if="email" class="auth-verify-resend">
                <span>{{ isDatabaseDelivery ? 'Need a fresh verification code?' : 'Didn’t receive the email?' }}</span>
                <button type="button" :disabled="loading || resending || countdown > 0" :aria-busy="resending" @click="resendCode">
                    <span v-if="resending" class="auth-spinner auth-spinner-small" aria-hidden="true"></span>
                    {{ resending ? (isDatabaseDelivery ? 'Generating…' : 'Sending…') : countdown > 0 ? `Resend code in ${countdown}s` : isDatabaseDelivery ? 'Generate new code' : 'Resend code' }}
                </button>
            </div>
            <p class="auth-switch">Wrong email address? <RouterLink :to="{ name: 'register' }">Go back</RouterLink></p>
        </section>
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';

const router = useRouter();
const auth = useAuthStore();
const email = ref('');
const isDatabaseDelivery = ref(false);
const maskedEmail = computed(() => {
    const [localPart, domain] = email.value.split('@');
    if (!localPart || !domain) return 'your email address';
    const maskedLocal = localPart.length < 3
        ? `${localPart.slice(0, 1)}•••`
        : `${localPart.slice(0, 1)}•••${localPart.slice(-1)}`;
    return `${maskedLocal}@${domain}`;
});
const digits = ref(Array(6).fill(''));
const inputRefs = ref([]);
const loading = ref(false);
const resending = ref(false);
const countdown = ref(60);
const errorMessage = ref('');
const successMessage = ref('');
let countdownTimer;

function startCountdown(seconds = 60) {
    clearInterval(countdownTimer);
    countdown.value = seconds;
    countdownTimer = setInterval(() => {
        if (countdown.value > 0) countdown.value--;
        else clearInterval(countdownTimer);
    }, 1000);
}

function onDigitInput(index, event) {
    const entered = event.target.value.replace(/\D/g, '');
    if (entered.length > 1) {
        digits.value = Array.from({ length: 6 }, (_, position) => entered[position] || '');
        errorMessage.value = '';
        inputRefs.value[Math.min(entered.length, 5)]?.focus();
        return;
    }
    const value = entered.slice(-1);
    digits.value[index] = value;
    errorMessage.value = '';
    if (value && index < 5) inputRefs.value[index + 1]?.focus();
}

function onDigitKeydown(index, event) {
    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        digits.value[index - 1] = '';
        inputRefs.value[index - 1]?.focus();
    } else if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault();
        inputRefs.value[index - 1]?.focus();
    } else if (event.key === 'ArrowRight' && index < 5) {
        event.preventDefault();
        inputRefs.value[index + 1]?.focus();
    }
}

function pasteCode(event) {
    const code = event.clipboardData?.getData('text')?.replace(/\D/g, '').slice(0, 6) || '';
    if (!code) return;
    digits.value = Array.from({ length: 6 }, (_, index) => code[index] || '');
    errorMessage.value = '';
    inputRefs.value[Math.min(code.length, 5)]?.focus();
}

async function verifyCode() {
    if (loading.value || digits.value.join('').length !== 6) return;
    loading.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    try {
        const result = await auth.verifyRegistration({ email: email.value, code: digits.value.join('') });
        sessionStorage.removeItem('planora.pending-registration-email');
        sessionStorage.removeItem('planora.pending-registration-delivery');
        sessionStorage.removeItem('planora.pending-registration-resend');
        await router.replace(result.redirectTo || '/dashboard');
    } catch (error) {
        errorMessage.value = error.response?.data?.errors?.code?.[0]
            || error.response?.data?.message
            || 'We could not verify that code. Please try again.';
    } finally {
        loading.value = false;
    }
}

async function resendCode() {
    if (!email.value || loading.value || resending.value || countdown.value > 0) return;
    resending.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    try {
        const response = await auth.resendRegistrationCode({ email: email.value });
        digits.value = Array(6).fill('');
        successMessage.value = isDatabaseDelivery.value
            ? 'If a pending registration exists, a new local code can be generated from the project terminal.'
            : 'If a pending registration exists, a new verification code will be sent.';
        const cooldown = Number(response.resend_in) || 60;
        sessionStorage.setItem('planora.pending-registration-resend', String(cooldown));
        startCountdown(cooldown);
        inputRefs.value[0]?.focus();
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'We could not resend the code. Please try again.';
        if (error.response?.status === 429) startCountdown(60);
    } finally {
        resending.value = false;
    }
}

onMounted(() => {
    email.value = sessionStorage.getItem('planora.pending-registration-email') || '';
    isDatabaseDelivery.value = sessionStorage.getItem('planora.pending-registration-delivery') === 'database';
    if (email.value) startCountdown(Number(sessionStorage.getItem('planora.pending-registration-resend')) || 60);
});
onBeforeUnmount(() => clearInterval(countdownTimer));
</script>

<style src="../../../css/pages/auth/verify-email.css"></style>
