<template>
    <main class="system-maintenance" aria-labelledby="system-maintenance-title" :aria-busy="loading || saving">
        <header class="system-maintenance__heading">
            <div>
                <p class="system-maintenance__eyebrow">ADMINISTRATION / OPERATIONS</p>
                <h1 id="system-maintenance-title">System Maintenance</h1>
                <p>Control planned maintenance using Laravel’s application-wide maintenance gate.</p>
            </div>
            <span class="system-maintenance__scope"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.5 16 5v4.3c0 3.7-2.5 6.4-6 8.2-3.5-1.8-6-4.5-6-8.2V5l6-2.5Z"/><path d="m7.5 10 1.7 1.7 3.5-3.5"/></svg>Administrator control</span>
        </header>

        <p v-if="feedback" class="system-maintenance__feedback" :class="`is-${feedback.type}`" :role="feedback.type === 'error' ? 'alert' : 'status'">{{ feedback.message }}</p>

        <section class="system-maintenance__status-card" aria-labelledby="maintenance-status-heading">
            <div class="system-maintenance__status-main">
                <span class="system-maintenance__status-icon" :class="!statusKnown ? 'is-unknown' : status.active ? 'is-active' : 'is-inactive'" aria-hidden="true">
                    <svg v-if="!statusKnown" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 10v5m0-8h.01"/></svg>
                    <svg v-else-if="status.active" viewBox="0 0 24 24"><path d="M12 3 21 20H3L12 3Z"/><path d="M12 9v5m0 3h.01"/></svg>
                    <svg v-else viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="m8.5 12 2.3 2.3 4.8-4.8"/></svg>
                </span>
                <div>
                    <p class="system-maintenance__eyebrow">CURRENT STATUS</p>
                    <h2 id="maintenance-status-heading">{{ loading ? 'Checking status…' : !statusKnown ? 'Status unavailable' : status.active ? 'Maintenance is active' : 'Planora is available' }}</h2>
                    <p>{{ !statusKnown ? 'The live maintenance state could not be confirmed.' : status.active ? 'Ordinary visitors and application requests are receiving a 503 response.' : 'The application is available to authenticated users and visitors.' }}</p>
                </div>
            </div>
            <span class="system-maintenance__status-badge" :class="!statusKnown ? 'is-unknown' : status.active ? 'is-active' : 'is-inactive'" :aria-label="`Maintenance ${!statusKnown ? 'status unknown' : status.active ? 'active' : 'inactive'}`">
                <span aria-hidden="true"></span>{{ !statusKnown ? (loading ? 'Checking' : 'Unknown') : status.active ? 'Active' : 'Inactive' }}
            </span>
        </section>

        <div class="system-maintenance__grid">
            <section class="system-maintenance__panel system-maintenance__control" aria-labelledby="maintenance-control-heading">
                <div class="system-maintenance__panel-heading">
                    <span class="system-maintenance__panel-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M12.9 3.2a4.1 4.1 0 0 0-5.3 5.3l-4.4 4.4a1.6 1.6 0 0 0 2.3 2.3l4.4-4.4a4.1 4.1 0 0 0 5.3-5.3l-2.3 2.3-2.1-2.1 2.3-2.5Z"/></svg></span>
                    <div><h2 id="maintenance-control-heading">Maintenance control</h2><p>Enable or restore access for everyone.</p></div>
                </div>

                <template v-if="!status.active">
                    <label class="system-maintenance__field-label" for="maintenance-message">Visitor message</label>
                    <textarea id="maintenance-message" v-model="message" rows="3" maxlength="500" :disabled="loading || saving" aria-describedby="maintenance-message-help" />
                    <p id="maintenance-message-help" class="system-maintenance__field-help">This message appears on the branded 503 maintenance page. {{ message.length }}/500</p>

                    <label class="system-maintenance__field-label" for="maintenance-estimate">Estimated completion <span>Optional</span></label>
                    <input id="maintenance-estimate" v-model="estimatedAt" type="datetime-local" :min="minimumDateTime" :disabled="loading || saving" />
                    <p class="system-maintenance__field-help">Shown to visitors when you provide an estimate.</p>
                    <p v-if="status.estimated_at" class="system-maintenance__configured-estimate">Configured estimate · {{ formatEstimate(status.estimated_at) }}</p>
                </template>
                <template v-else>
                    <div class="system-maintenance__current-copy">
                        <span>Current visitor message</span>
                        <p>{{ status.message }}</p>
                        <span v-if="status.estimated_at" class="system-maintenance__estimate">Estimated completion · {{ formatEstimate(status.estimated_at) }}</span>
                    </div>
                    <p v-if="!status.estimated_at" class="system-maintenance__field-help">No completion estimate is configured.</p>
                </template>

                <button class="system-maintenance__action" :class="status.active ? 'is-disable' : 'is-enable'" type="button" :disabled="loading || saving || !statusKnown || (!status.active && !message.trim())" @click="requestAction">
                    <svg v-if="status.active" viewBox="0 0 20 20" aria-hidden="true"><path d="M7 4H4v12h3m3-9 4 3-4 3m-3-3h7"/></svg>
                    <svg v-else viewBox="0 0 20 20" aria-hidden="true"><path d="M10 3.5v13M3.5 10h13"/></svg>
                    {{ saving ? (pendingAction === 'enable' ? 'Enabling…' : 'Disabling…') : status.active ? 'Disable Maintenance Mode' : 'Enable Maintenance Mode' }}
                </button>
                <button v-if="loadError" class="system-maintenance__retry" type="button" :disabled="loading" @click="loadStatus(true)">Retry status check</button>
            </section>

            <section class="system-maintenance__panel system-maintenance__impact" aria-labelledby="maintenance-impact-heading">
                <div class="system-maintenance__panel-heading">
                    <span class="system-maintenance__panel-icon is-neutral" aria-hidden="true"><svg viewBox="0 0 20 20"><circle cx="10" cy="10" r="7.3"/><path d="M10 9v4m0-6h.01"/></svg></span>
                    <div><h2 id="maintenance-impact-heading">What to expect</h2><p>Maintenance applies across the application.</p></div>
                </div>
                <ul class="system-maintenance__impact-list">
                    <li><span aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m4 10 4 4 8-8"/></svg></span><div><strong>Visitors see a branded service notice</strong><p>The configured message and optional estimate appear with HTTP 503.</p></div></li>
                    <li><span aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m4 10 4 4 8-8"/></svg></span><div><strong>Workspace and API requests are paused</strong><p>Ordinary accounts cannot use protected pages or application operations while maintenance is active.</p></div></li>
                    <li><span aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m4 10 4 4 8-8"/></svg></span><div><strong>This control stays available to administrators</strong><p>Use this page to restore access. The server-side Artisan commands remain available as an operational fallback.</p></div></li>
                </ul>
                <div class="system-maintenance__notice"><strong>Before enabling</strong><span>Save active work and confirm the deployment or maintenance window is ready. Enabling affects all users immediately.</span></div>
            </section>
        </div>

        <AppConfirmDialog
            :open="confirmOpen"
            :title="pendingAction === 'enable' ? 'Enable maintenance mode?' : 'Disable maintenance mode?'"
            :message="pendingAction === 'enable' ? 'Ordinary visitors and application requests will receive a 503 maintenance response. This control page will remain available to administrators.' : 'Planora will become available to all users and API clients as soon as maintenance mode is disabled.'"
            :confirm-label="pendingAction === 'enable' ? 'Enable maintenance' : 'Disable maintenance'"
            :cancel-label="'Keep current state'"
            :danger="pendingAction === 'enable'"
            @cancel="confirmOpen = false"
            @confirm="confirmAction"
        />
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppConfirmDialog from '../../../shared/components/AppConfirmDialog.vue';
import { disableSystemMaintenance, enableSystemMaintenance, getSystemMaintenanceStatus } from '../../../features/system-maintenance/admin/services/systemMaintenanceApi.js';

const loading = ref(true);
const statusKnown = ref(false);
const saving = ref(false);
const status = ref({ active: false, message: '', estimated_at: null, retry_after: 60 });
const message = ref('');
const estimatedAt = ref('');
const feedback = ref(null);
const loadError = ref(false);
const confirmOpen = ref(false);
const pendingAction = ref(null);
let refreshTimer = null;

const minimumDateTime = computed(() => {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    return now.toISOString().slice(0, 16);
});

async function loadStatus(initial = false) {
    if (saving.value) return;
    const hadLoadError = loadError.value;
    if (initial) loading.value = true;
    loadError.value = false;
    try {
        const wasActive = status.value.active;
        status.value = await getSystemMaintenanceStatus();
        statusKnown.value = true;
        if (hadLoadError && feedback.value?.type === 'error') feedback.value = null;
        if (initial || (wasActive && !status.value.active)) {
            message.value = status.value.message || '';
            estimatedAt.value = toDateTimeLocal(status.value.estimated_at);
        }
    } catch (error) {
        loadError.value = true;
        if (!feedback.value || feedback.value.type !== 'success') {
            feedback.value = { type: 'error', message: error.response?.data?.message || 'Maintenance status could not be loaded. Try again.' };
        }
    } finally {
        loading.value = false;
    }
}

function requestAction() {
    if (loading.value || saving.value || !statusKnown.value) return;
    if (!status.value.active && !message.value.trim()) {
        feedback.value = { type: 'error', message: 'Enter a visitor message before enabling maintenance.' };
        return;
    }
    feedback.value = null;
    pendingAction.value = status.value.active ? 'disable' : 'enable';
    confirmOpen.value = true;
}

async function confirmAction() {
    if (saving.value || !pendingAction.value) return;
    const action = pendingAction.value;
    saving.value = true;
    feedback.value = null;
    try {
        let result;
        if (action === 'enable') {
            result = await enableSystemMaintenance({
                message: message.value.trim(),
                estimated_at: estimatedAt.value ? new Date(estimatedAt.value).toISOString() : null,
                retry_after: status.value.retry_after || 60,
            });
        } else {
            result = await disableSystemMaintenance();
        }
        status.value = result.data;
        statusKnown.value = true;
        message.value = result.data.message || message.value;
        estimatedAt.value = toDateTimeLocal(result.data.estimated_at);
        feedback.value = { type: 'success', message: result.message };
        confirmOpen.value = false;
    } catch (error) {
        feedback.value = { type: 'error', message: error.response?.data?.message || 'The maintenance setting could not be changed. Please retry.' };
    } finally {
        saving.value = false;
        pendingAction.value = null;
    }
}

function formatEstimate(value) {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

function toDateTimeLocal(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 16);
}

onMounted(() => {
    loadStatus(true);
    refreshTimer = window.setInterval(() => loadStatus(), 20_000);
});
onBeforeUnmount(() => window.clearInterval(refreshTimer));
</script>

<style src="../../../../css/pages/admin/system-maintenance.css"></style>
