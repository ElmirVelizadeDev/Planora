<template>
    <main class="auth-page page-register">
        <section class="auth-card" aria-labelledby="register-title">
            <p class="auth-eyebrow">Get started</p>
            <h1 id="register-title">Create your account</h1>
            <p class="auth-description">Set up your Planora account and start organizing your work.</p>

            <form class="auth-form" @submit.prevent="submit">
                <label class="auth-field">
                    <span>Full name</span>
                    <input v-model.trim="name" type="text" name="name" autocomplete="name" required maxlength="255">
                    <small v-if="fieldErrors.name" class="auth-field-error">{{ fieldErrors.name[0] }}</small>
                </label>
                <label class="auth-field">
                    <span>Email address</span>
                    <input v-model.trim="email" type="email" name="email" autocomplete="email" required>
                    <small v-if="fieldErrors.email" class="auth-field-error">{{ fieldErrors.email[0] }}</small>
                </label>
                <label class="auth-field">
                    <span>Password</span>
                    <input v-model="password" type="password" name="password" autocomplete="new-password" minlength="8" required>
                    <small v-if="fieldErrors.password" class="auth-field-error">{{ fieldErrors.password[0] }}</small>
                </label>
                <label class="auth-field">
                    <span>Confirm password</span>
                    <input v-model="passwordConfirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                    <small v-if="fieldErrors.password_confirmation" class="auth-field-error">{{ fieldErrors.password_confirmation[0] }}</small>
                </label>
                <p v-if="errorMessage" class="auth-error" role="alert">{{ errorMessage }}</p>
                <button class="auth-submit" type="submit" :disabled="loading">
                    {{ loading ? 'Creating account…' : 'Create account' }}
                </button>
            </form>

            <p class="auth-switch">Already have an account? <RouterLink :to="{ name: 'login' }">Sign in</RouterLink></p>
        </section>
    </main>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';

const router = useRouter();
const auth = useAuthStore();
const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});

async function submit() {
    loading.value = true;
    errorMessage.value = '';
    fieldErrors.value = {};

    if (password.value !== passwordConfirmation.value) {
        fieldErrors.value = { password_confirmation: ['The password confirmation does not match.'] };
        loading.value = false;
        return;
    }

    try {
        const result = await auth.register({
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        });
        await router.replace(result.redirectTo);
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
