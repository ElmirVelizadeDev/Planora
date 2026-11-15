<template>
    <main class="planora-error-page" :class="`is-${page.tone}`">
        <section class="planora-error-card" :aria-labelledby="`error-title-${code}`">
            <RouterLink class="planora-error-brand" :to="{ name: 'home' }" aria-label="Planora home">
                <span class="planora-error-mark" aria-hidden="true">P</span>
                <span>Planora</span>
            </RouterLink>
            <div class="planora-error-content">
                <span class="planora-error-code">{{ code }}</span>
                <h1 :id="`error-title-${code}`">{{ page.title }}</h1>
                <p class="planora-error-description">{{ page.description }}</p>
                <p v-if="code === 503" class="planora-error-note">{{ availabilityMessage }}</p>
                <p v-if="actionError" class="planora-error-action-error" role="alert">{{ actionError }}</p>
                <div class="planora-error-actions">
                    <RouterLink v-if="page.primaryRoute" class="planora-error-button planora-error-button--primary" :to="page.primaryRoute">
                        {{ page.primaryLabel }}
                    </RouterLink>
                    <button v-else class="planora-error-button planora-error-button--primary" type="button" @click="retry">
                        {{ page.primaryLabel }}
                    </button>
                    <button v-if="page.backLabel" class="planora-error-button planora-error-button--secondary" type="button" @click="goBack">
                        {{ page.backLabel }}
                    </button>
                    <button v-if="page.signOut" class="planora-error-signout" type="button" :disabled="signingOut" @click="signOut">
                        {{ signingOut ? 'Signing out…' : 'Sign out' }}
                    </button>
                </div>
            </div>
            <footer class="planora-error-footer"><span>Thoughtful tools for focused work</span><span>© Planora</span></footer>
        </section>
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';

const props = defineProps({ code: { type: Number, required: true } });
const router = useRouter();
const auth = useAuthStore();
const signingOut = ref(false);
const actionError = ref('');
const availabilityMessage = ref('We’ll keep checking while Planora is unavailable.');
let availabilityPoll = null;

const pages = {
    401: { title: 'Please sign in', description: 'Your session is missing or has expired. Sign in to continue to your Planora workspace.', primaryLabel: 'Go to sign in', primaryRoute: { name: 'login' }, backLabel: 'Go home', tone: 'accent' },
    403: { title: 'This area is restricted', description: 'Your account does not have permission to open this area. If you think this is a mistake, contact your workspace administrator.', primaryLabel: 'Go to workspace', primaryRoute: { name: 'home' }, backLabel: 'Go back', signOut: true, tone: 'warning' },
    404: { title: 'We couldn’t find that page', description: 'The page may have moved, or the address may be incorrect. You can return to your workspace and continue from there.', primaryLabel: 'Go to Planora', primaryRoute: { name: 'home' }, backLabel: 'Go back', tone: 'accent' },
    419: { title: 'Your session has expired', description: 'Refresh the page to start a new secure session. Any unsaved changes may need to be entered again.', primaryLabel: 'Refresh page', backLabel: 'Sign in', primaryRoute: null, tone: 'warning' },
    429: { title: 'Please slow down', description: 'Planora has received too many requests in a short time. Wait a moment, then try again.', primaryLabel: 'Try again', backLabel: 'Go home', tone: 'warning' },
    500: { title: 'Something went wrong', description: 'Planora couldn’t complete that request. Your information remains protected. Please try again or return to your workspace.', primaryLabel: 'Try again', backLabel: 'Go home', tone: 'danger' },
    503: { title: 'We’ll be back soon', description: 'Planora is temporarily unavailable while we carry out maintenance. Your work and account data remain safe.', primaryLabel: 'Check again', backLabel: 'Go home', tone: 'accent' },
};
const page = computed(() => pages[props.code] || pages[500]);

function retry() {
    window.location.reload();
}

function goBack() {
    if (props.code === 419 && page.value.backLabel === 'Sign in') {
        router.push({ name: 'login' });
        return;
    }
    if (window.history.state?.back) router.back();
    else router.replace({ name: 'home' });
}

async function signOut() {
    if (signingOut.value) return;
    signingOut.value = true;
    actionError.value = '';
    try {
        await auth.logout();
        await router.replace({ name: 'login' });
    } catch {
        actionError.value = 'Could not sign out right now. Please try again.';
    } finally {
        signingOut.value = false;
    }
}

onMounted(() => {
    if (props.code !== 503) return;
    availabilityPoll = window.setInterval(async () => {
        try {
            const response = await fetch('/', { method: 'GET', cache: 'no-store', credentials: 'same-origin' });
            if (response.ok) window.location.assign('/');
        } catch {
            availabilityMessage.value = 'We’ll keep checking while Planora is unavailable.';
        }
    }, 15_000);
});
onBeforeUnmount(() => window.clearInterval(availabilityPoll));
</script>

<style src="../../../css/pages/errors.css"></style>
