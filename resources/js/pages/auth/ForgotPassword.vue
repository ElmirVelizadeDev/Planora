<template>
    <main class="auth-page page-forgot-password">
        <section class="auth-card auth-recovery-card" aria-labelledby="forgot-password-title">
            <div class="auth-status-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 7.5 12 13l8-5.5"/><rect x="3" y="5" width="18" height="14" rx="2.5"/></svg>
            </div>
            <p class="auth-eyebrow">Account recovery</p>
            <h1 id="forgot-password-title">{{ step === 'email' ? 'Reset your password' : 'Check your email' }}</h1>
            <p class="auth-description">{{ step === 'email' ? 'Enter the email address linked to your account and we’ll send a verification code.' : `Enter the six-digit code sent to ${email}. It expires in 5 minutes.` }}</p>

            <form v-if="step === 'email'" class="auth-form" @submit.prevent="submitEmail">
                <label class="auth-field"><span>Email address</span><input v-model.trim="email" type="email" name="email" autocomplete="email" required maxlength="255" :aria-invalid="Boolean(fieldErrors.email)" @input="clearErrors"></label>
                <small v-if="fieldErrors.email" class="auth-field-error">{{ fieldErrors.email[0] }}</small>
                <p v-if="errorMessage" class="auth-error" role="alert"><span class="auth-alert-icon" aria-hidden="true">!</span><span>{{ errorMessage }}</span></p>
                <p v-if="localDevelopment" class="auth-info" role="status">For local testing, run <code>php artisan auth:local-otp recovery {{ email }}</code> in the project terminal to generate a code.</p>
                <p v-if="sent" class="auth-success" role="status">If an account exists for this email, a recovery code has been sent.</p>
                <button class="auth-submit" type="submit" :disabled="loading" :aria-busy="loading"><span v-if="loading" class="auth-spinner" aria-hidden="true"></span><span>{{ loading ? 'Checking account…' : 'Continue' }}</span><span v-if="!loading" aria-hidden="true">→</span></button>
            </form>

            <form v-else class="auth-form" @submit.prevent="submitCode">
                <div class="auth-otp-fields" role="group" aria-label="Six-digit verification code" @paste="pasteCode">
                    <input v-for="(_, index) in digits" :key="index" :ref="el => setInputRef(el, index)" v-model="digits[index]" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" :aria-label="`Code digit ${index + 1}`" :aria-invalid="Boolean(fieldErrors.code)" @input="onDigitInput($event, index)" @keydown.backspace="onBackspace($event, index)">
                </div>
                <small v-if="fieldErrors.code" class="auth-field-error" role="alert">{{ fieldErrors.code[0] }}</small>
                <p v-if="errorMessage" class="auth-error" role="alert"><span class="auth-alert-icon" aria-hidden="true">!</span><span>{{ errorMessage }}</span></p>
                <p v-if="localDevelopment" class="auth-info" role="status">For local testing, run <code>php artisan auth:local-otp recovery {{ email }}</code> in the project terminal to generate a fresh code.</p>
                <p class="auth-field-hint">{{ expired ? 'This code has expired.' : `Code expires in ${countdown}.` }}</p>
                <button class="auth-submit" type="submit" :disabled="loading || code.length !== 6 || expired" :aria-busy="loading"><span v-if="loading" class="auth-spinner" aria-hidden="true"></span><span>{{ loading ? 'Verifying…' : 'Verify code' }}</span><span v-if="!loading" aria-hidden="true">→</span></button>
                <p class="auth-resend-row">Didn’t receive a code? <button class="auth-inline-link" type="button" :disabled="resendSeconds > 0 || loading" @click="resend">{{ resendSeconds > 0 ? `Resend in ${resendSeconds}s` : 'Resend code' }}</button></p>
            </form>

            <p class="auth-switch"><RouterLink :to="{ name: 'login' }">← Back to sign in</RouterLink></p>
        </section>
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { requestPasswordRecovery, resendPasswordRecovery, verifyPasswordRecovery } from '../../features/auth/services/authApi.js';

const router = useRouter();
const step = ref('email');
const email = ref('');
const digits = ref(['', '', '', '', '', '']);
const inputRefs = ref([]);
const loading = ref(false);
const sent = ref(false);
const localDevelopment = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});
const secondsLeft = ref(300);
const resendSeconds = ref(60);
const code = computed(() => digits.value.join(''));
const expired = computed(() => secondsLeft.value <= 0);
const countdown = computed(() => `${Math.floor(secondsLeft.value / 60)}:${String(secondsLeft.value % 60).padStart(2, '0')}`);
let timer;

onMounted(() => { timer = window.setInterval(() => { if (step.value === 'code') { secondsLeft.value = Math.max(0, secondsLeft.value - 1); resendSeconds.value = Math.max(0, resendSeconds.value - 1); } }, 1000); });
onBeforeUnmount(() => window.clearInterval(timer));

function clearErrors() { fieldErrors.value = {}; errorMessage.value = ''; }
function setInputRef(element, index) { if (element) inputRefs.value[index] = element; }
function onDigitInput(event, index) {
    const value = event.target.value.replace(/\D/g, '').slice(-1);
    digits.value[index] = value;
    event.target.value = value;
    clearErrors();
    if (value && index < 5) inputRefs.value[index + 1]?.focus();
}
function onBackspace(event, index) {
    if (!digits.value[index] && index > 0) inputRefs.value[index - 1]?.focus();
}
function pasteCode(event) {
    const pasted = event.clipboardData?.getData('text')?.replace(/\D/g, '').slice(0, 6);
    if (!pasted) return;
    event.preventDefault();
    digits.value = Array.from({ length: 6 }, (_, index) => pasted[index] || '');
    inputRefs.value[Math.min(pasted.length, 5)]?.focus();
    clearErrors();
}
async function submitEmail() {
    if (loading.value) return;
    loading.value = true; clearErrors();
    try {
        const response = await requestPasswordRecovery({ email: email.value });
        localDevelopment.value = response.data.delivery_mode === 'local';
        sent.value = true; step.value = 'code'; secondsLeft.value = Number(response.data.expires_in) || 300; resendSeconds.value = Number(response.data.resend_in) || 60;
        window.setTimeout(() => inputRefs.value[0]?.focus(), 0);
    } catch (error) {
        fieldErrors.value = error.response?.data?.errors || {};
        errorMessage.value = fieldErrors.value.email ? '' : (error.response?.status === 429 ? (error.response.data.message || 'Please wait before trying again.') : (error.response?.status === 503 ? error.response.data.message : 'We could not check this email address. Please try again.'));
    } finally { loading.value = false; }
}
async function resend() {
    loading.value = true; clearErrors();
    try {
        const response = await resendPasswordRecovery({ email: email.value });
        localDevelopment.value = response.data.delivery_mode === 'local';
        digits.value = ['', '', '', '', '', '']; secondsLeft.value = Number(response.data.expires_in) || 300; resendSeconds.value = Number(response.data.resend_in) || 60;
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'We could not resend the code. Please try again.';
    } finally { loading.value = false; }
}
async function submitCode() {
    loading.value = true; clearErrors();
    try {
        const response = await verifyPasswordRecovery({ email: email.value, code: code.value });
        await router.replace({ name: 'reset-password' });
    } catch (error) {
        fieldErrors.value = error.response?.data?.errors || {};
        errorMessage.value = error.response?.data?.status === 429 || error.response?.status === 429
            ? (error.response.data.message || 'Too many attempts. Request a new code.')
            : '';
        digits.value = ['', '', '', '', '', '']; inputRefs.value[0]?.focus();
        if (!errorMessage.value && !fieldErrors.value.code) fieldErrors.value = { code: ['This code could not be verified. Request a new one and try again.'] };
    } finally { loading.value = false; }
}
</script>

<style src="../../../css/pages/auth/forgot-password.css"></style>
