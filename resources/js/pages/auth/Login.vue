<template>
    <main class="auth-page page-login">
        <section class="auth-card" aria-labelledby="login-title">
            <p class="auth-eyebrow">Welcome back</p>
            <h1 id="login-title">Sign in to Planora</h1>
            <p class="auth-description">Enter your account details to continue to your workspace.</p>

            <form class="auth-form" @submit.prevent="submit">
                <label class="auth-field">
                    <span>Email address</span>
                    <input v-model.trim="email" type="email" name="email" autocomplete="username" required>
                    <small v-if="fieldErrors.email" class="auth-field-error">{{ fieldErrors.email[0] }}</small>
                </label>
                <label class="auth-field">
                    <span>Password</span>
                    <input v-model="password" type="password" name="password" autocomplete="current-password" required>
                    <small v-if="fieldErrors.password" class="auth-field-error">{{ fieldErrors.password[0] }}</small>
                </label>
                <RouterLink class="auth-inline-link" :to="{ name: 'forgot-password' }">Forgot password?</RouterLink>
                <p v-if="errorMessage" class="auth-error" role="alert">{{ errorMessage }}</p>
                <button class="auth-submit" type="submit" :disabled="loading">
                    {{ loading ? 'Signing in…' : 'Sign in' }}
                </button>
            </form>

            <p class="auth-switch">Don't have an account? <RouterLink :to="{ name: 'register' }">Create one</RouterLink></p>
        </section>
    </main>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';

const router = useRouter();
const auth = useAuthStore();
const email = ref('');
const password = ref('');
const loading = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});

async function submit() {
    loading.value = true;
    errorMessage.value = '';
    fieldErrors.value = {};

    try {
        const result = await auth.login({ email: email.value, password: password.value });
        await router.replace(result.redirectTo || '/403');
    } catch (error) {
        fieldErrors.value = error.response?.data?.errors || {};
        errorMessage.value = error.response?.status === 403
            ? (error.response.data.message || 'This account is not authorized to sign in.')
            : (error.response?.data?.message === 'The given data was invalid.'
                ? ''
                : error.response?.data?.message || 'We could not sign you in. Check your details and try again.');
    } finally {
        loading.value = false;
    }
}
</script>

<style src="../../../css/pages/auth/login.css"></style>
