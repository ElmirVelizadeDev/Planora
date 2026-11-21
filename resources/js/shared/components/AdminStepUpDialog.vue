<template>
    <Teleport to="body">
        <div v-if="pending" class="planora-step-up-backdrop" @click.self="cancel">
            <section class="planora-step-up-dialog" role="dialog" aria-modal="true" aria-labelledby="step-up-title">
                <p class="planora-step-up-eyebrow">Security check</p>
                <h2 id="step-up-title">Verify before continuing</h2>
                <p>This sensitive action needs a fresh identity check.</p>
                <form @submit.prevent="verify">
                    <label><span>{{ mfaEnabled ? (recoveryMode ? 'Recovery code' : 'Authenticator code') : 'Current password' }}</span>
                        <input v-model="value" :type="mfaEnabled ? 'text' : 'password'" :inputmode="mfaEnabled && !recoveryMode ? 'numeric' : undefined" :autocomplete="mfaEnabled ? 'one-time-code' : 'current-password'" :maxlength="mfaEnabled ? (recoveryMode ? 10 : 6) : 128" required autofocus>
                    </label>
                    <button v-if="mfaEnabled" class="planora-step-up-link" type="button" @click="recoveryMode = !recoveryMode; value = ''">Use {{ recoveryMode ? 'authenticator' : 'recovery' }} code</button>
                    <p v-if="error" class="planora-step-up-error" role="alert">{{ error }}</p>
                    <div class="planora-step-up-actions"><button type="button" :disabled="loading" @click="cancel">Cancel</button><button type="submit" :disabled="loading || !methodLoaded">{{ loading ? 'Verifying…' : 'Verify and continue' }}</button></div>
                </form>
            </section>
        </div>
    </Teleport>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import api from '../services/api.js';
import { useAuthStore } from '../../features/auth/stores/authStore.js';

const auth = useAuthStore();
const pending = ref(null);
const value = ref('');
const error = ref('');
const loading = ref(false);
const mfaEnabled = ref(false);
const recoveryMode = ref(false);
const methodLoaded = ref(true);

async function open(event) {
    if (pending.value) {
        event.detail.cancel();
        return;
    }
    pending.value = event.detail;
    value.value = '';
    error.value = '';
    recoveryMode.value = false;
    mfaEnabled.value = false;
    methodLoaded.value = !auth.hasRole('admin');
    if (auth.hasRole('admin')) {
        try { mfaEnabled.value = (await api.get('/admin/mfa')).data.enabled === true; methodLoaded.value = true; }
        catch { error.value = 'Could not load the security verification method. Please retry.'; }
    }
}

async function verify() {
    if (!pending.value || loading.value) return;
    loading.value = true;
    error.value = '';
    const credentials = mfaEnabled.value
        ? (recoveryMode.value ? { recovery_code: value.value } : { code: value.value })
        : { password: value.value };
    try {
        const current = pending.value;
        await current.verify(credentials);
        pending.value = null;
        value.value = '';
    } catch (exception) {
        error.value = exception.response?.data?.errors?.code?.[0]
            || exception.response?.data?.errors?.password?.[0]
            || exception.response?.data?.message
            || 'Identity verification failed. Try again.';
    } finally {
        loading.value = false;
    }
}

function cancel() {
    pending.value?.cancel();
    pending.value = null;
    value.value = '';
}

onMounted(() => window.addEventListener('planora:step-up-required', open));
onBeforeUnmount(() => window.removeEventListener('planora:step-up-required', open));
</script>

<style>
.planora-step-up-backdrop{position:fixed;inset:0;z-index:10000;display:grid;place-items:center;padding:20px;background:rgba(24,32,55,.48)}
.planora-step-up-dialog{width:min(100%,420px);padding:26px;border:1px solid var(--color-border);border-radius:16px;background:var(--color-surface-elevated);color:var(--color-text);box-shadow:var(--shadow-soft)}
.planora-step-up-dialog h2{margin:0;font-size:20px}.planora-step-up-dialog p{color:var(--color-text-secondary);font-size:14px;line-height:1.5}
.planora-step-up-eyebrow{margin:0 0 8px;color:var(--color-accent-text)!important;font-size:11px!important;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
.planora-step-up-dialog form{display:grid;gap:14px;margin-top:20px}.planora-step-up-dialog label{display:grid;gap:7px;font-size:13px;font-weight:600}
.planora-step-up-dialog input{box-sizing:border-box;width:100%;height:44px;padding:0 12px;border:1px solid var(--color-input-border);border-radius:9px;background:var(--color-input-background);color:var(--color-text);font:inherit}
.planora-step-up-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:8px}.planora-step-up-actions button{min-height:40px;padding:0 14px;border:1px solid var(--color-border);border-radius:9px;background:var(--color-surface);color:var(--color-text);font:inherit;font-weight:600;cursor:pointer}.planora-step-up-actions button[type=submit]{border-color:var(--color-accent);background:var(--color-accent);color:#fff}
.planora-step-up-actions button:disabled{opacity:.6;cursor:wait}.planora-step-up-link{justify-self:start;border:0;background:transparent;color:var(--color-accent-text);font:inherit;cursor:pointer}.planora-step-up-error{margin:0;color:var(--color-danger)!important;font-size:13px!important}
</style>
