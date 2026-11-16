<template>
    <main class="system-maintenance" aria-labelledby="system-maintenance-title" :aria-busy="loading || saving">
        <header class="system-maintenance__heading">
            <div>
                <p class="system-maintenance__eyebrow">ADMINISTRATION / OPERATIONS</p>
                <h1 id="system-maintenance-title">System Maintenance</h1>
                <p>Pause selected areas independently while keeping the rest of Planora available.</p>
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
                    <p class="system-maintenance__eyebrow">OVERALL STATUS</p>
                    <h2 id="maintenance-status-heading">{{ loading ? 'Checking status…' : !statusKnown ? 'Status unavailable' : overallLabel }}</h2>
                    <p>{{ !statusKnown ? 'The live maintenance state could not be confirmed.' : status.native_active ? 'Laravel global maintenance is active and supersedes individual scope settings.' : status.active ? 'Only the enabled areas are paused. Other scopes remain available.' : 'All application areas are available.' }}</p>
                </div>
            </div>
            <span class="system-maintenance__status-badge" :class="!statusKnown ? 'is-unknown' : status.active ? 'is-active' : 'is-inactive'">
                <span aria-hidden="true"></span>{{ !statusKnown ? (loading ? 'Checking' : 'Unknown') : status.active ? 'Maintenance active' : 'Operational' }}
            </span>
        </section>

        <section v-if="status.native_active" class="system-maintenance__global-warning" role="alert">
            <div><strong>Global Laravel maintenance is active</strong><p>The CLI maintenance gate affects the entire application, regardless of these scope switches. Disable global maintenance to resume scope-based control.</p></div>
            <button type="button" :disabled="loading || saving" @click="requestDisableAll">Disable all maintenance</button>
        </section>

        <section class="system-maintenance__panel system-maintenance__preferences" aria-labelledby="scope-preferences-title">
            <div class="system-maintenance__panel-heading">
                <span class="system-maintenance__panel-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M10 3v14M3 10h14"/></svg></span>
                <div><h2 id="scope-preferences-title">Visitor message</h2><p>Shared across affected areas, with an optional estimated return time.</p></div>
            </div>
            <div class="system-maintenance__preferences-grid">
                <label class="system-maintenance__field">
                    <span class="system-maintenance__field-label">Maintenance message</span>
                    <textarea v-model="message" rows="3" maxlength="500" :disabled="loading || saving || status.native_active" aria-describedby="maintenance-message-help" />
                    <span id="maintenance-message-help" class="system-maintenance__field-help">Shown on the branded 503 page for affected areas. {{ message.length }}/500</span>
                </label>
                <label class="system-maintenance__field">
                    <span class="system-maintenance__field-label">Estimated completion <span>Optional</span></span>
                    <input v-model="estimatedAt" type="datetime-local" :min="minimumDateTime" :disabled="loading || saving || status.native_active" />
                    <span class="system-maintenance__field-help">Visitors see this estimate when configured.</span>
                </label>
            </div>
            <div class="system-maintenance__preferences-actions">
                <span v-if="hasPreferenceChanges" role="status">Unsaved message settings</span>
                <button class="system-maintenance__save-preferences" type="button" :disabled="!hasPreferenceChanges || loading || saving || status.native_active || !message.trim()" @click="saveVisitorPreferences">
                    {{ savingPreferences ? 'Saving…' : 'Save message settings' }}
                </button>
            </div>
            <p v-if="status.native_active" class="system-maintenance__field-help">Scope preferences cannot be changed while the native global maintenance gate is active.</p>
        </section>

        <section class="system-maintenance__scope-list" aria-label="Independent maintenance scopes">
            <article v-for="scope in scopes" :key="scope.key" class="system-maintenance__scope-card" :class="{ 'is-active': scopeEnabled(scope), 'is-disabled': !statusKnown || loading || saving || status.native_active }">
                <div class="system-maintenance__scope-card-copy">
                    <div class="system-maintenance__scope-card-title">
                        <span class="system-maintenance__scope-dot" :class="scopeEnabled(scope) ? 'is-active' : 'is-inactive'" aria-hidden="true"></span>
                        <h2>{{ scope.label }}</h2>
                        <span class="system-maintenance__scope-state" :class="scopeEnabled(scope) ? 'is-active' : 'is-inactive'">{{ scopeEnabled(scope) ? 'Under maintenance' : 'Available' }}</span>
                    </div>
                    <p>{{ scope.description }}</p>
                    <p class="system-maintenance__scope-routes">{{ scope.areas }}</p>
                </div>
                <button
                    class="system-maintenance__scope-toggle"
                    :class="{ 'is-on': scopeEnabled(scope) }"
                    type="button"
                    role="switch"
                    :aria-checked="scopeEnabled(scope)"
                    :aria-label="`${scopeEnabled(scope) ? 'Disable' : 'Enable'} ${scope.label} maintenance`"
                    :disabled="!statusKnown || loading || saving || status.native_active"
                    @click="requestScopeAction(scope)"
                >
                    <span aria-hidden="true"></span><span>{{ scopeEnabled(scope) ? 'Disable' : 'Enable' }}</span>
                </button>
            </article>
        </section>

        <section class="system-maintenance__summary" aria-labelledby="maintenance-summary-title">
            <div><h2 id="maintenance-summary-title">Maintenance summary</h2><p>{{ summaryText }}</p></div>
            <ul>
                <li v-for="scope in scopes" :key="scope.key"><span :class="scopeEnabled(scope) ? 'is-active' : 'is-inactive'" aria-hidden="true"></span>{{ scope.label }}: {{ scopeEnabled(scope) ? 'paused' : 'available' }}</li>
            </ul>
        </section>

        <button v-if="loadError" class="system-maintenance__retry" type="button" :disabled="loading" @click="loadStatus(true)">Retry status check</button>

        <AppConfirmDialog
            :open="confirmOpen"
            :title="confirmationTitle"
            :message="confirmationMessage"
            :confirm-label="confirmationLabel"
            :cancel-label="'Keep current state'"
            :danger="pendingAction?.enabled || pendingAction?.all"
            @cancel="confirmOpen = false"
            @confirm="confirmAction"
        />
    </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppConfirmDialog from '../../../shared/components/AppConfirmDialog.vue';
import { disableSystemMaintenance, getSystemMaintenanceStatus, setMaintenanceScope, updateMaintenancePreferences } from '../../../features/system-maintenance/admin/services/systemMaintenanceApi.js';

const scopes = [
    { key: 'public_website', route: 'public-website', label: 'Public Website', description: 'Pause public-facing pages while leaving sign-in and workspace access available.', areas: 'Home · Terms · Privacy' },
    { key: 'authentication', route: 'authentication', label: 'Authentication', description: 'Pause sign-in, registration, password recovery, and verification workflows.', areas: 'Login · Registration · Password recovery · OTP verification' },
    { key: 'dashboard_workspace', route: 'dashboard-workspace', label: 'Dashboard & Workspace', description: 'Pause User and Moderator workspaces and their APIs. Admin access remains available.', areas: 'Dashboards · Projects · Tasks · Management pages · Workspace APIs' },
];

const loading = ref(true);
const statusKnown = ref(false);
const saving = ref(false);
const status = ref({ active: false, native_active: false, overall: 'operational', scopes: {}, effective_scopes: {}, message: '', estimated_at: null, retry_after: 60 });
const message = ref('');
const estimatedAt = ref('');
const savedPreferences = ref({ message: '', estimatedAt: '' });
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
const overallLabel = computed(() => ({
    operational: 'Operational',
    partially_under_maintenance: 'Partially Under Maintenance',
    full_maintenance: 'Full Maintenance',
}[status.value.overall] || 'Status unavailable'));
const summaryText = computed(() => {
    if (status.value.native_active) return 'Global Laravel maintenance currently pauses all areas. Individual scope settings are retained but do not override the global gate.';
    const active = scopes.filter(scopeEnabled);
    return active.length === 0
        ? 'No maintenance scopes are enabled.'
        : `${active.length} of ${scopes.length} areas ${active.length === 1 ? 'is' : 'are'} currently paused.`;
});
const hasPreferenceChanges = computed(() => message.value.trim() !== savedPreferences.value.message
    || estimatedAt.value !== savedPreferences.value.estimatedAt);
const savingPreferences = computed(() => saving.value && pendingAction.value?.type === 'preferences');
const confirmationTitle = computed(() => {
    if (pendingAction.value?.all) return 'Disable all maintenance?';
    const scope = scopes.find((item) => item.key === pendingAction.value?.scope);
    return `${pendingAction.value?.enabled ? 'Enable' : 'Disable'} ${scope?.label || 'maintenance'}?`;
});
const confirmationMessage = computed(() => {
    if (pendingAction.value?.all) return 'This will clear all independent scopes and turn off Laravel global maintenance so application access can resume.';
    const scope = scopes.find((item) => item.key === pendingAction.value?.scope);
    return pendingAction.value?.enabled
        ? `${scope?.label} visitors will receive the branded 503 page. Other maintenance scopes will keep their current state.`
        : `${scope?.label} pages and APIs will become available again. Other maintenance scopes will keep their current state.`;
});
const confirmationLabel = computed(() => pendingAction.value?.all
    ? 'Disable all maintenance'
    : `${pendingAction.value?.enabled ? 'Enable' : 'Disable'} scope`);

function scopeEnabled(scope) {
    return Boolean(status.value.effective_scopes?.[scope.key] ?? status.value.scopes?.[scope.key]);
}

function syncSavedPreferences(nextStatus) {
    message.value = nextStatus.message || '';
    estimatedAt.value = toDateTimeLocal(nextStatus.estimated_at);
    savedPreferences.value = { message: message.value.trim(), estimatedAt: estimatedAt.value };
}

async function loadStatus(initial = false) {
    if (saving.value) return;
    const hadLoadError = loadError.value;
    if (initial) loading.value = true;
    loadError.value = false;
    try {
        const wasNative = status.value.native_active;
        status.value = await getSystemMaintenanceStatus();
        statusKnown.value = true;
        if (hadLoadError && feedback.value?.type === 'error') feedback.value = null;
        if (initial || wasNative !== status.value.native_active || !hasPreferenceChanges.value) {
            syncSavedPreferences(status.value);
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

function requestScopeAction(scope) {
    if (loading.value || saving.value || !statusKnown.value || status.value.native_active) return;
    const enabled = !scopeEnabled(scope);
    if (enabled && !message.value.trim()) {
        feedback.value = { type: 'error', message: 'Enter a visitor message before enabling a scope.' };
        return;
    }
    feedback.value = null;
    pendingAction.value = { scope: scope.key, route: scope.route, enabled };
    confirmOpen.value = true;
}

function requestDisableAll() {
    if (loading.value || saving.value) return;
    feedback.value = null;
    pendingAction.value = { all: true, enabled: false };
    confirmOpen.value = true;
}

async function saveVisitorPreferences() {
    if (saving.value || !hasPreferenceChanges.value || !message.value.trim() || status.value.native_active) return;
    saving.value = true;
    pendingAction.value = { type: 'preferences' };
    feedback.value = null;
    try {
        const result = await updateMaintenancePreferences({
            message: message.value.trim(),
            estimated_at: estimatedAt.value ? new Date(estimatedAt.value).toISOString() : null,
            retry_after: status.value.retry_after || 60,
        });
        status.value = result.data;
        statusKnown.value = true;
        syncSavedPreferences(result.data);
        feedback.value = { type: 'success', message: result.message };
    } catch (error) {
        feedback.value = { type: 'error', message: error.response?.data?.message || 'Maintenance message settings could not be saved. Please retry.' };
    } finally {
        saving.value = false;
        pendingAction.value = null;
    }
}

async function confirmAction() {
    if (saving.value || !pendingAction.value) return;
    const action = pendingAction.value;
    saving.value = true;
    feedback.value = null;
    try {
        const result = action.all
            ? await disableSystemMaintenance()
            : await setMaintenanceScope(action.route, {
                enabled: action.enabled,
                ...(action.enabled ? {
                    message: message.value.trim(),
                    estimated_at: estimatedAt.value ? new Date(estimatedAt.value).toISOString() : null,
                    retry_after: status.value.retry_after || 60,
                } : {}),
            });
        status.value = result.data;
        statusKnown.value = true;
        syncSavedPreferences(result.data);
        feedback.value = { type: 'success', message: result.message };
        confirmOpen.value = false;
    } catch (error) {
        feedback.value = { type: 'error', message: error.response?.data?.message || 'The maintenance setting could not be changed. Please retry.' };
    } finally {
        saving.value = false;
        pendingAction.value = null;
    }
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
