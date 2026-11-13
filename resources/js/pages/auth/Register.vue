<template>
    <main class="auth-page page-register">
        <section class="auth-card" aria-labelledby="register-title">
            <RouterLink class="auth-back-home" :to="{ name: 'home' }"><span aria-hidden="true">←</span>Back to Home</RouterLink>
            <p class="auth-eyebrow">Get started</p>
            <h1 id="register-title">Create your account</h1>
            <p class="auth-description">A few details to create your personal Planora workspace.</p>

            <form class="auth-form" @submit.prevent="submit">
                <label class="auth-field">
                    <span>Full name</span>
                    <input v-model="name" type="text" name="name" autocomplete="name" required minlength="2" maxlength="50" :aria-invalid="Boolean(fieldErrors.name || nameClientError)" :aria-describedby="fieldErrors.name || nameClientError ? 'register-name-error' : undefined" @input="onNameInput">
                    <small v-if="fieldErrors.name || nameClientError" id="register-name-error" class="auth-field-error">{{ fieldErrors.name?.[0] || nameClientError }}</small>
                </label>
                <label class="auth-field">
                    <span>Email address</span>
                    <input v-model="email" type="email" name="email" autocomplete="email" required maxlength="255" :aria-invalid="Boolean(fieldErrors.email || emailClientError)" :aria-describedby="fieldErrors.email || emailClientError ? 'register-email-error' : undefined" @input="clearFieldError('email')" @blur="email = email.trim().toLowerCase()">
                    <small v-if="fieldErrors.email || emailClientError" id="register-email-error" class="auth-field-error">{{ fieldErrors.email?.[0] || emailClientError }}</small>
                </label>
                <div class="auth-field">
                    <label for="register-password">Password</label>
                    <div class="auth-password-control">
                        <input id="register-password" v-model="password" :type="showPassword ? 'text' : 'password'" name="password" autocomplete="new-password" minlength="8" maxlength="128" required :aria-describedby="fieldErrors.password ? 'password-guidance password-error' : 'password-guidance'" :aria-invalid="Boolean(fieldErrors.password)" @input="clearFieldError('password')">
                        <button class="auth-password-toggle" type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword" @click="showPassword = !showPassword">
                            <svg v-if="!showPassword" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.6"/></svg>
                            <svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.8 10.8 0 0 1 12 6c6.1 0 9.5 6 9.5 6a15 15 0 0 1-3.1 3.6M6.2 6.2C3.8 7.7 2.5 12 2.5 12s3.4 6 9.5 6c1.2 0 2.3-.2 3.3-.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                        </button>
                    </div>
                    <div v-if="password" id="password-strength" class="auth-password-strength" :class="`is-level-${passwordAssessment.score}`" aria-live="polite">
                        <div class="auth-password-strength-bars" aria-hidden="true">
                            <span v-for="level in 5" :key="level" :class="{ 'is-active': level <= passwordAssessment.score + 1 }"></span>
                        </div>
                        <span>{{ passwordAssessment.label }}</span>
                    </div>
                    <ul id="password-guidance" class="auth-password-guidance">
                        <li :class="{ 'is-met': password.length >= 8 }">At least 8 characters (up to 128).</li>
                        <li :class="{ 'is-met': password && !passwordAssessment.predictable }">Avoid easy-to-guess passwords.</li>
                        <li :class="{ 'is-met': password.length >= 12 && passwordAssessment.score >= 2 }">Choose a long, unique password.</li>
                    </ul>
                    <small v-if="fieldErrors.password" id="password-error" class="auth-field-error">{{ fieldErrors.password[0] }}</small>
                </div>
                <div class="auth-field">
                    <label for="register-password-confirmation">Confirm password</label>
                    <div class="auth-password-control">
                        <input id="register-password-confirmation" v-model="passwordConfirmation" :type="showPasswordConfirmation ? 'text' : 'password'" name="password_confirmation" autocomplete="new-password" minlength="8" maxlength="128" required :aria-invalid="Boolean(fieldErrors.password_confirmation || confirmationMismatch)" aria-describedby="password-confirmation-feedback" @input="clearFieldError('password_confirmation')">
                        <button class="auth-password-toggle" type="button" :aria-label="showPasswordConfirmation ? 'Hide password confirmation' : 'Show password confirmation'" :aria-pressed="showPasswordConfirmation" @click="showPasswordConfirmation = !showPasswordConfirmation">
                            <svg v-if="!showPasswordConfirmation" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.6"/></svg>
                            <svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A10.8 10.8 0 0 1 12 6c6.1 0 9.5 6 9.5 6a15 15 0 0 1-3.1 3.6M6.2 6.2C3.8 7.7 2.5 12 2.5 12s3.4 6 9.5 6c1.2 0 2.3-.2 3.3-.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                        </button>
                    </div>
                    <small v-if="fieldErrors.password_confirmation" id="password-confirmation-feedback" class="auth-field-error">{{ fieldErrors.password_confirmation[0] }}</small>
                    <small v-else-if="confirmationMismatch" id="password-confirmation-feedback" class="auth-field-error">Passwords do not match.</small>
                    <small v-else-if="passwordConfirmation && password === passwordConfirmation" id="password-confirmation-feedback" class="auth-password-match">Passwords match.</small>
                    <small v-else id="password-confirmation-feedback" class="auth-field-hint">Enter the same password again.</small>
                </div>
                <div class="auth-terms-field">
                    <input id="register-terms-accepted" v-model="termsAccepted" type="checkbox" name="terms_accepted" aria-label="I agree to the Terms & Conditions and acknowledge that I have read the Privacy Policy" :aria-invalid="Boolean(fieldErrors.terms_accepted)" :aria-describedby="fieldErrors.terms_accepted ? 'register-terms-error' : undefined" @change="clearFieldError('terms_accepted')">
                    <div class="auth-terms-copy">
                        <span>I agree to the <RouterLink :to="{ name: 'terms-and-conditions' }">Terms &amp; Conditions</RouterLink>.</span>
                        <span>I acknowledge that I have read the <RouterLink :to="{ name: 'privacy-policy' }">Privacy Policy</RouterLink>.</span>
                        <small v-if="fieldErrors.terms_accepted" id="register-terms-error" class="auth-field-error" role="alert">{{ fieldErrors.terms_accepted[0] }}</small>
                    </div>
                </div>
                <p class="auth-info"><span class="auth-alert-icon" aria-hidden="true">i</span><span>A six-digit verification code will be prepared for the next step. Your account is created only after you verify it.</span></p>
                <p v-if="errorMessage" class="auth-error" role="alert"><span class="auth-alert-icon" aria-hidden="true">!</span><span>{{ errorMessage }}</span></p>
                <button class="auth-submit" type="submit" :disabled="loading" :aria-busy="loading">
                    <span v-if="loading" class="auth-spinner" aria-hidden="true"></span>
                    <span>{{ loading ? 'Creating account…' : 'Create account' }}</span><span v-if="!loading" aria-hidden="true">→</span>
                </button>
            </form>

            <p class="auth-switch">Already have an account? <RouterLink :to="{ name: 'login' }">Sign in</RouterLink></p>
        </section>
    </main>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import { normalizePersonName, validatePersonName, validateSupportedEmail } from '../../shared/validation.js';

defineOptions({ name: 'Register' });

const router = useRouter();
const auth = useAuthStore();
const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const termsAccepted = ref(false);
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
const loading = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});

const confirmationMismatch = computed(() => Boolean(passwordConfirmation.value) && password.value !== passwordConfirmation.value);
const passwordAssessment = computed(() => assessPassword(password.value));
const nameClientError = computed(() => name.value ? validatePersonName(name.value) : '');
const emailClientError = computed(() => email.value ? validateSupportedEmail(email.value) : '');

function onNameInput() {
    name.value = normalizePersonName(name.value);
    clearFieldError('name');
}

function clearFieldError(field) {
    if (fieldErrors.value[field]) {
        const nextErrors = { ...fieldErrors.value };
        delete nextErrors[field];
        fieldErrors.value = nextErrors;
    }
    errorMessage.value = '';
}

const commonPasswords = new Set([
    'password', 'password1', 'password123', 'passw0rd', '12345678', '123456789',
    'qwerty', 'qwerty123', 'letmein', 'welcome', 'welcome1', 'admin', 'admin123',
    'iloveyou', 'monkey', 'dragon', 'sunshine', 'football', 'baseball', 'master',
    'login', 'abc12345', '00000000', '11111111',
]);

function isPredictablePassword(value) {
    const normalized = value.trim().toLowerCase();
    const compact = normalized.replace(/\s+/gu, '');
    if (commonPasswords.has(normalized) || commonPasswords.has(compact)) return true;
    if (/^(password|qwerty|letmein|welcome|admin|iloveyou|monkey|dragon|sunshine|football|baseball|master|login|passw0rd)[\W_]*\d*$/iu.test(compact)) return true;
    if (/^(.)\1{7,}$/su.test(value) || /^(.{1,4})\1{2,}$/su.test(value)) return true;
    return ['012345678', '123456789', '987654321', 'abcdefgh', 'hgfedcba', 'qwertyui', 'asdfghjk', 'zxcvbnm']
        .some(sequence => compact.includes(sequence));
}

function assessPassword(value) {
    if (!value) return { score: -1, label: '', predictable: false };
    if (isPredictablePassword(value)) return { score: 0, label: 'Very Weak', predictable: true };

    const categories = [/[a-z]/u, /[A-Z]/u, /\d/u, /[^\p{L}\p{N}\s]/u].filter(pattern => pattern.test(value)).length;
    const uniqueRatio = new Set(Array.from(value.toLowerCase())).size / Math.max(Array.from(value).length, 1);
    let score = value.length >= 20 ? 4 : value.length >= 16 ? 3 : value.length >= 12 ? 2 : value.length >= 8 ? 1 : 0;
    score += Math.min(2, Math.max(0, categories - 1));
    if (uniqueRatio < 0.45) score--;
    score = Math.max(0, Math.min(4, score));
    const labels = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];

    return { score, label: labels[score], predictable: false };
}

async function submit() {
    if (loading.value) return;
    loading.value = true;
    errorMessage.value = '';
    fieldErrors.value = {};

    if (!termsAccepted.value) {
        fieldErrors.value = { terms_accepted: ['Please agree to the Terms & Conditions and acknowledge that you have read the Privacy Policy to continue.'] };
        loading.value = false;
        return;
    }

    name.value = normalizePersonName(name.value);
    email.value = email.value.trim().toLowerCase();
    const nameError = validatePersonName(name.value);
    const emailError = validateSupportedEmail(email.value);
    if (nameError || emailError) {
        fieldErrors.value = {
            ...(nameError ? { name: [nameError] } : {}),
            ...(emailError ? { email: [emailError] } : {}),
        };
        loading.value = false;
        return;
    }

    if (password.value !== passwordConfirmation.value) {
        fieldErrors.value = { password_confirmation: ['The password confirmation does not match.'] };
        loading.value = false;
        return;
    }
    if (password.value.length < 8 || password.value.length > 128) {
        fieldErrors.value = { password: ['Password must be between 8 and 128 characters.'] };
        loading.value = false;
        return;
    }
    if (passwordAssessment.value.predictable) {
        fieldErrors.value = { password: ['Choose a less predictable password.'] };
        loading.value = false;
        return;
    }

    try {
        const result = await auth.register({
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
            terms_accepted: termsAccepted.value,
        });
        sessionStorage.setItem('planora.pending-registration-email', result.email || email.value.trim().toLowerCase());
        sessionStorage.setItem('planora.pending-registration-delivery', result.deliveryMode || 'email');
        sessionStorage.setItem('planora.pending-registration-resend', String(result.resendIn || 60));
        name.value = '';
        email.value = '';
        password.value = '';
        passwordConfirmation.value = '';
        termsAccepted.value = false;
        await router.replace({ name: 'verify-email' });
    } catch (error) {
        fieldErrors.value = error.response?.data?.errors || {};
        errorMessage.value = error.response?.data?.message === 'The given data was invalid.'
            ? ''
            : error.response?.data?.message || 'We could not create your account. Please try again.';
    } finally {
        loading.value = false;
    }
}
</script>

<style src="../../../css/pages/auth/register.css"></style>
