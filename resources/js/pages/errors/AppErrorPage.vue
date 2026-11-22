<template>
    <main class="planora-error-page" :class="[`is-${tone}`, `has-${icon}`]">
        <section class="planora-error-card" :aria-labelledby="`error-title-${code}`">
            <div class="planora-error-brand" aria-label="Planora">
                <span class="planora-error-mark" aria-hidden="true">P</span><span>Planora</span>
            </div>

            <div class="planora-error-content">
                <div class="planora-error-illustration" aria-hidden="true">
                    <svg v-if="icon === 'shield'" viewBox="0 0 64 64"><path d="M32 7 51 14v15c0 13-8 22-19 28C21 51 13 42 13 29V14l19-7Z"/><path d="m24 31 5 5 11-12"/></svg>
                    <svg v-else-if="icon === 'search'" viewBox="0 0 64 64"><circle cx="28" cy="28" r="16"/><path d="m40 40 13 13M22 28h12M28 22v12"/></svg>
                    <svg v-else-if="icon === 'clock'" viewBox="0 0 64 64"><circle cx="32" cy="32" r="22"/><path d="M32 18v15l10 6"/></svg>
                    <svg v-else-if="icon === 'gauge'" viewBox="0 0 64 64"><path d="M12 43a22 22 0 1 1 40 0"/><path d="m32 34 11-12M18 43h28"/><circle cx="32" cy="34" r="3"/></svg>
                    <svg v-else-if="icon === 'wrench'" viewBox="0 0 64 64"><path d="M39 13a16 16 0 0 0-19 20L9 44a6 6 0 0 0 9 9l12-12a16 16 0 0 0 20-19l-9 9-9-9 7-9Z"/></svg>
                    <svg v-else-if="icon === 'maintenance'" viewBox="0 0 64 64"><path d="M32 8 56 51H8L32 8Z"/><path d="M32 25v12M32 44h.01"/></svg>
                    <svg v-else-if="icon === 'key'" viewBox="0 0 64 64"><circle cx="23" cy="27" r="13"/><path d="m33 36 20 20M43 46l6-6M37 40l6-6"/></svg>
                    <svg v-else viewBox="0 0 64 64"><path d="m32 7 5.8 17.2L55 30l-17.2 5.8L32 53l-5.8-17.2L9 30l17.2-5.8L32 7Z"/><path d="m49 8 2 6 6 2-6 2-2 6-2-6-6-2 6-2 2-6Z"/></svg>
                </div>

                <div class="planora-error-copy">
                    <span class="planora-error-code">{{ code }} <span aria-hidden="true">·</span> {{ eyebrow }}</span>
                    <h1 :id="`error-title-${code}`">{{ title }}</h1>
                    <p class="planora-error-description">{{ description }}</p>
                    <p v-if="code === 503 && maintenanceMessageState" class="planora-error-note">{{ maintenanceMessageState }}</p>
                    <p v-if="code === 503 && estimatedAtState" class="planora-error-note">Estimated availability: <time>{{ estimatedAtState }}</time></p>
                    <p v-if="code === 503" class="planora-error-note" role="status" aria-live="polite">{{ availabilityMessage }}</p>
                    <p v-if="code === 429 && remainingSeconds > 0" class="planora-error-note" role="status" aria-live="polite">
                        You can try again in {{ formattedRemaining }}.
                    </p>
                    <p v-if="actionError" class="planora-error-action-error" role="alert">{{ actionError }}</p>

                    <div class="planora-error-actions">
                        <RouterLink v-if="resolvedPrimaryTo" class="planora-error-button planora-error-button--primary" :to="resolvedPrimaryTo">{{ resolvedPrimaryLabel }}</RouterLink>
                        <button v-else class="planora-error-button planora-error-button--primary" type="button" :disabled="primaryDisabled" @click="runPrimary">
                            {{ primaryDisabled && code === 429 ? `Wait ${formattedRemaining}` : primaryLabel }}
                        </button>

                        <RouterLink v-if="resolvedSecondaryDestination" class="planora-error-button planora-error-button--secondary" :to="resolvedSecondaryDestination">{{ resolvedSecondaryActionLabel }}</RouterLink>
                        <button v-else-if="secondaryAction" class="planora-error-button planora-error-button--secondary" type="button" @click="secondaryAction">{{ secondaryLabel }}</button>
                        <button v-if="showSignOut" class="planora-error-signout" type="button" :disabled="signingOut" @click="signOut">
                            {{ signingOut ? 'Signing out…' : 'Sign out' }}
                        </button>
                    </div>
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
import { blockedMaintenanceScopeFor, maintenanceRecoveryRouteFor } from '../../shared/utils/errorDestinations.js';
import { getMaintenanceStatus } from '../../features/system-maintenance/shared/services/maintenanceStatusApi.js';
import { rateLimitDeadline } from '../../shared/utils/rateLimitState.js';

const props = defineProps({
    code: { type: Number, required: true },
    eyebrow: { type: String, default: 'Request interrupted' },
    title: { type: String, default: 'Something went wrong' },
    description: { type: String, default: 'Planora could not complete that request. Please try again.' },
    tone: { type: String, default: 'accent' },
    icon: { type: String, default: 'spark' },
    primaryLabel: { type: String, default: 'Go to dashboard' },
    primaryTo: { type: [Object, String], default: null },
    primaryAction: { type: Function, default: null },
    secondaryLabel: { type: String, default: '' },
    secondaryTo: { type: [Object, String], default: null },
    secondaryAction: { type: Function, default: null },
    showSignOut: { type: Boolean, default: false },
    maintenanceMessage: { type: String, default: '' },
    estimatedAt: { type: String, default: '' },
});

const router = useRouter();
const auth = useAuthStore();
const signingOut = ref(false);
const actionError = ref('');
const availabilityMessage = ref('We will check availability periodically.');
const maintenanceMessageState = ref(props.maintenanceMessage);
const estimatedAtState = ref(props.estimatedAt);
const maintenanceScopesState = ref(null);
const maintenanceStateKnown = ref(false);
const now = ref(Date.now());
let availabilityPoll = null;
let countdownTimer = null;
let availabilityChecks = 0;
const MAX_AVAILABILITY_CHECKS = 8;
const remainingSeconds = computed(() => Math.max(0, Math.ceil(((rateLimitDeadline.value || 0) - now.value) / 1000)));
const formattedRemaining = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;
    return minutes ? `${minutes}m ${seconds}s` : `${seconds}s`;
});
const primaryDisabled = computed(() => props.code === 429 && remainingSeconds.value > 0);
const blockedActionScope = (target) => maintenanceStateKnown.value
    ? blockedMaintenanceScopeFor(router, target, maintenanceScopesState.value, auth)
    : null;
const resolvedPrimaryTo = computed(() => {
    if (!props.primaryTo) return null;
    const scope = blockedActionScope(props.primaryTo);
    return scope ? { name: 'error.503', query: { maintenanceScope: scope } } : props.primaryTo;
});
const resolvedPrimaryLabel = computed(() => resolvedPrimaryTo.value?.name === 'error.503' ? 'View service status' : props.primaryLabel);
const secondaryDestination = computed(() => {
    if (props.code !== 503) return props.secondaryTo;
    return maintenanceRecoveryRouteFor(auth, maintenanceScopesState.value);
});
const resolvedSecondaryDestination = computed(() => {
    const target = secondaryDestination.value;
    if (!target || props.code === 503) return target;
    const scope = blockedActionScope(target);
    return scope ? { name: 'error.503', query: { maintenanceScope: scope } } : target;
});
const resolvedSecondaryActionLabel = computed(() => resolvedSecondaryDestination.value?.name === 'error.503'
    ? 'View service status'
    : props.secondaryLabel);

async function runPrimary() {
    if (primaryDisabled.value) return;
    actionError.value = '';
    if (props.primaryAction) {
        await props.primaryAction();
        return;
    }
    if (props.code === 503) {
        availabilityChecks = 0;
        if (!availabilityPoll) availabilityPoll = window.setInterval(checkAvailability, 30_000);
        await checkAvailability();
        return;
    }
    window.location.reload();
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

async function checkAvailability() {
    availabilityChecks += 1;
    try {
        const response = await fetch('/api/v1/maintenance/status', {
            cache: 'no-store', credentials: 'same-origin', headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        if (response.ok) {
            const state = payload.data || {};
            maintenanceScopesState.value = state.scopes || null;
            maintenanceStateKnown.value = true;
            if (state.message) maintenanceMessageState.value = state.message;
            if (state.estimated_at) estimatedAtState.value = state.estimated_at;
            const scope = router.currentRoute.value.query.maintenanceScope;
            const safeDestination = maintenanceRecoveryRouteFor(auth, state.scopes);
            if (typeof scope === 'string' && state.scopes?.[scope] === false && safeDestination) {
                await router.replace(safeDestination);
                return;
            }
            const scopes = Object.values(state.scopes || {});
            if (!scope && scopes.length > 0 && scopes.every((enabled) => enabled === false) && safeDestination) {
                await router.replace(safeDestination);
                return;
            }
            availabilityMessage.value = availabilityChecks >= MAX_AVAILABILITY_CHECKS
                ? 'Automatic checks are paused. You can check again when you are ready.'
                : 'Planora is still unavailable. We will check again shortly.';
        } else {
            availabilityMessage.value = 'Planora is still unavailable. We will check again shortly.';
        }
    } catch {
        availabilityMessage.value = availabilityChecks >= MAX_AVAILABILITY_CHECKS
            ? 'Automatic checks are paused. Use Check availability to try again.'
            : 'Planora is still unavailable. We will check again shortly.';
    }

    if (availabilityChecks >= MAX_AVAILABILITY_CHECKS) {
        window.clearInterval(availabilityPoll);
        availabilityPoll = null;
    }
}

onMounted(() => {
    if (props.code !== 503) {
        getMaintenanceStatus().then((state) => {
            maintenanceScopesState.value = state.scopes || null;
            maintenanceStateKnown.value = true;
        }).catch(() => {
            // Keep ordinary recovery available when maintenance status cannot be checked.
        });
    }
    if (props.code === 429 && rateLimitDeadline.value > Date.now()) {
        countdownTimer = window.setInterval(() => {
            now.value = Date.now();
            if (remainingSeconds.value <= 0) {
                window.clearInterval(countdownTimer);
                countdownTimer = null;
            }
        }, 250);
    }
    if (props.code === 503) {
        availabilityPoll = window.setInterval(checkAvailability, 30_000);
        checkAvailability();
    }
});

onBeforeUnmount(() => {
    window.clearInterval(availabilityPoll);
    window.clearInterval(countdownTimer);
});

defineExpose({ checkAvailability, remainingSeconds });
</script>

<style src="../../../css/pages/errors.css"></style>
