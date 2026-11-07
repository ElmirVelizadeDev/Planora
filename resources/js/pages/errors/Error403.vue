<template>
    <main class="page-error403">
        <section class="error403-card" aria-labelledby="forbidden-title">
            <span class="error403-code">403</span>
            <h1 id="forbidden-title">Access denied</h1>
            <p>Your account does not have permission to open this area.</p>
            <button type="button" :disabled="signingOut" @click="signOut">
                {{ signingOut ? 'Signing out…' : 'Sign out' }}
            </button>
            <small v-if="error" role="alert">{{ error }}</small>
        </section>
    </main>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';

const router = useRouter();
const auth = useAuthStore();
const signingOut = ref(false);
const error = ref('');

async function signOut() {
    if (signingOut.value) return;

    signingOut.value = true;
    error.value = '';
    try {
        await auth.logout();
        await router.replace({ name: 'login' });
    } catch {
        error.value = 'Could not sign out. Please try again.';
    } finally {
        signingOut.value = false;
    }
}
</script>

<style src="../../../css/pages/errors/styles/Error403.css"></style>
