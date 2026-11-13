<template>
    <div class="al-modal-backdrop" @click.self="close" @keydown.esc="close" tabindex="-1">
        <section class="al-detail-modal activity-logs" role="dialog" aria-modal="true" aria-labelledby="activity-log-detail-title" :aria-busy="loading">
            <header class="al-modal-heading">
                <div><p class="al-eyebrow">ACTIVITY LOG DETAILS</p><h1 id="activity-log-detail-title">{{ record ? actionLabel(record.action) : 'Activity details' }}</h1></div>
                <button ref="closeButton" class="al-modal-close" type="button" aria-label="Close activity details" @click="close">×</button>
            </header>
            <section v-if="loading" class="al-detail-state" role="status">Loading activity details…</section>
            <section v-else-if="error" class="al-detail-state al-error" role="alert"><strong>Activity details could not be loaded.</strong><p>{{ error }}</p><button class="al-button al-button--secondary" type="button" @click="load">Try again</button></section>
            <template v-else-if="record">
                <header class="al-header al-detail-header"><div><p class="al-eyebrow">{{ record.module }} · {{ formatDateTime(record.created_at) }}</p><p>{{ record.description }}</p></div><span class="al-status" :class="`is-${record.status}`"><i></i>{{ label(record.status) }}</span></header>
                <section class="al-detail-grid">
                    <article class="al-detail-card"><h2>Event overview</h2><dl><div><dt>Event ID</dt><dd class="al-code">{{ record.event_id || `#${record.id}` }}</dd></div><div><dt>Action</dt><dd>{{ actionLabel(record.action) }}</dd></div><div><dt>Module</dt><dd>{{ record.module }}</dd></div><div><dt>Result</dt><dd>{{ label(record.status) }}</dd></div><div><dt>Timestamp</dt><dd>{{ formatDateTime(record.created_at) }}</dd></div></dl></article>
                    <article class="al-detail-card"><h2>Actor</h2><div class="al-detail-actor"><span class="al-avatar">{{ initials(record.actor?.name) }}</span><div><strong>{{ record.actor?.name || 'Unavailable actor' }}</strong><span>{{ record.actor?.email || 'No historical email available' }}</span></div></div><dl><div><dt>Role</dt><dd>{{ label(record.actor?.role) || '—' }}</dd></div><div><dt>Actor ID</dt><dd>{{ record.actor?.id || 'Unavailable' }}</dd></div></dl></article>
                    <article class="al-detail-card"><h2>Target</h2><dl><div><dt>Type</dt><dd>{{ record.target?.type || '—' }}</dd></div><div><dt>Name</dt><dd>{{ record.target?.label || 'Historical label unavailable' }}</dd></div><div><dt>Record ID</dt><dd>{{ record.target?.id || '—' }}</dd></div></dl></article>
                    <article class="al-detail-card al-change-card"><h2>Changes</h2><template v-if="changeRows.length"><div class="al-change-table"><div class="al-change-head"><span>Field</span><span>Previous value</span><span>New value</span></div><div v-for="change in changeRows" :key="change.key" class="al-change-row"><strong>{{ label(change.key) }}</strong><span>{{ displayValue(change.old) }}</span><span>{{ displayValue(change.next) }}</span></div></div></template><p v-else class="al-muted">No field-level changes were recorded for this event.</p></article>
                    <article v-if="propertyRows.length" class="al-detail-card"><h2>Additional context</h2><dl><div v-for="property in propertyRows" :key="property.key"><dt>{{ label(property.key) }}</dt><dd>{{ displayValue(property.value) }}</dd></div></dl></article>
                    <article class="al-detail-card al-request-card"><h2>Request metadata</h2><dl><div><dt>IP address</dt><dd>{{ record.request?.ip_address || '—' }}</dd></div><div><dt>HTTP method</dt><dd>{{ record.request?.method || '—' }}</dd></div><div><dt>Path</dt><dd class="al-code">{{ record.request?.path || '—' }}</dd></div><div><dt>Request ID</dt><dd class="al-code">{{ record.request?.request_id || '—' }}</dd></div><div class="al-user-agent"><dt>User agent</dt><dd>{{ record.request?.user_agent || '—' }}</dd></div></dl></article>
                </section>
            </template>
            <footer class="al-modal-footer"><RouterLink class="al-button al-button--secondary" :to="{ name: 'admin.activity-logs.index' }">Back to Activity Logs</RouterLink></footer>
        </section>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { getActivityLog } from '../../../features/activity-logs/admin/services/activityLogsApi.js';

const route = useRoute();
const router = useRouter();
const record = ref(null);
const loading = ref(false);
const error = ref('');
const closeButton = ref(null);
let requestSequence = 0;
let controller;
const changeRows = computed(() => {
    const oldValues = record.value?.old_values || {};
    const newValues = record.value?.new_values || {};
    const keys = [...new Set([...Object.keys(oldValues), ...Object.keys(newValues)])];
    return keys.filter((key) => JSON.stringify(oldValues[key]) !== JSON.stringify(newValues[key]))
        .map((key) => ({ key, old: oldValues[key], next: newValues[key] }));
});
const propertyRows = computed(() => Object.entries(record.value?.properties || {}).map(([key, value]) => ({ key, value })));
async function load() {
    const sequence = ++requestSequence;
    controller?.abort(); controller = new AbortController();
    loading.value = true; error.value = '';
    try { const response = await getActivityLog(route.params.logId, { signal: controller.signal }); if (sequence === requestSequence) record.value = response.data; }
    catch (exception) { if (sequence === requestSequence && exception.code !== 'ERR_CANCELED') error.value = exception.response?.data?.message || 'Please retry in a moment.'; }
    finally { if (sequence === requestSequence) loading.value = false; }
}
function label(value) { return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }
function actionLabel(value) { return label(String(value || '').replaceAll('.', ' ').replaceAll('_', ' ')); }
function initials(value) { return String(value || '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('') || '?'; }
function formatDateTime(value) { return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'full', timeStyle: 'medium' }).format(new Date(value)) : '—'; }
function displayValue(value) { if (value === null || value === undefined || value === '') return '—'; if (typeof value === 'boolean') return value ? 'Yes' : 'No'; return typeof value === 'object' ? JSON.stringify(value) : String(value); }
function close() { router.replace({ name: 'admin.activity-logs.index' }); }
watch(() => route.params.logId, load, { immediate: true });
onMounted(() => nextTick(() => closeButton.value?.focus()));
onBeforeUnmount(() => { requestSequence++; controller?.abort(); });
</script>

<style src="../../../../css/features/activity-logs/admin/index.css"></style>
