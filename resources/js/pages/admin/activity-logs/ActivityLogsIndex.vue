<template>
    <main class="activity-logs" :aria-busy="logs.loading">
        <header class="al-header">
            <div><p class="al-eyebrow">WORKSPACE AUDIT</p><h1>Activity Logs</h1><p>Monitor and review important actions performed across your Planora workspace.</p><small v-if="logs.updatedAt.value">Updated {{ formatDateTime(logs.updatedAt.value) }} · {{ logs.total.value.toLocaleString() }} matching activities</small></div>
            <div class="al-actions"><button type="button" class="al-button al-button--secondary" :disabled="logs.loading.value" @click="logs.load()">↻ <span>Refresh</span></button><button class="al-button al-button--primary" type="button" :disabled="logs.loading.value || exporting || !logs.total.value" @click="exportCsv">⇩ <span>{{ exporting ? 'Preparing CSV…' : 'Export CSV' }}</span></button></div>
        </header>

        <section class="al-stats" aria-label="Activity statistics">
            <article><span class="al-stat-icon">▤</span><div><strong>{{ number(logs.stats.total_activities) }}</strong><span>Total activities</span></div></article>
            <article><span class="al-stat-icon is-blue">◷</span><div><strong>{{ number(logs.stats.today_activities) }}</strong><span>Today</span></div></article>
            <article><span class="al-stat-icon is-green">♙</span><div><strong>{{ number(logs.stats.unique_actors) }}</strong><span>Unique actors</span></div></article>
            <article><span class="al-stat-icon is-amber">⌑</span><div><strong>{{ number(logs.stats.security_events) }}</strong><span>Security events</span></div></article>
        </section>

        <section class="al-panel" aria-label="Activity log records">
            <div class="al-toolbar">
                <label class="al-search"><span class="sr-only">Search activity logs</span><span aria-hidden="true">⌕</span><input v-model="search" type="search" placeholder="Search actors, actions, targets…" @input="scheduleSearch"></label>
                <label class="al-actor-search"><span class="sr-only">Search actor options</span><input v-model="actorSearch" type="search" placeholder="Find actor…" @input="logs.searchActors(actorSearch)"></label>
                <label><span class="sr-only">Actor</span><select v-model="logs.filters.actor_id" :disabled="logs.filterLoading.value" @change="logs.applyFilters()"><option value="">All actors</option><option v-for="actor in logs.actors.value" :key="actor.id" :value="String(actor.id)">{{ actor.name }}{{ actor.deleted ? ' (deleted)' : '' }}</option></select></label>
                <label><span class="sr-only">Module</span><select v-model="logs.filters.module" @change="logs.applyFilters()"><option value="">All modules</option><option v-for="module in logs.modules.value" :key="module" :value="module">{{ module }}</option></select></label>
                <label><span class="sr-only">Entity</span><select v-model="logs.filters.subject_type" @change="logs.applyFilters()"><option value="">All entities</option><option v-for="type in logs.subjectTypes.value" :key="type" :value="type">{{ type }}</option></select></label>
                <label><span class="sr-only">Action</span><select v-model="logs.filters.event" @change="logs.applyFilters()"><option value="">All actions</option><option v-for="event in logs.events.value" :key="event" :value="event">{{ actionLabel(event) }}</option></select></label>
                <label><span class="sr-only">Result</span><select v-model="logs.filters.status" @change="logs.applyFilters()"><option value="">All results</option><option v-for="status in logs.statuses.value" :key="status" :value="status">{{ label(status) }}</option></select></label>
                <label class="al-date"><span class="sr-only">From date</span><input v-model="logs.filters.from" type="date" :max="logs.filters.to || today" @change="logs.applyFilters()"></label>
                <label class="al-date"><span class="sr-only">To date</span><input v-model="logs.filters.to" type="date" :min="logs.filters.from || undefined" :max="today" @change="logs.applyFilters()"></label>
                <button class="al-clear" type="button" :disabled="!hasFilters" @click="clearFilters">Clear filters</button>
            </div>
            <div class="al-presets" aria-label="Date presets"><button v-for="preset in presets" :key="preset.id" type="button" :class="{ active: activePreset === preset.id }" @click="setPreset(preset.id)">{{ preset.label }}</button></div>
            <p v-if="logs.error.value" class="al-error" role="alert">{{ logs.error.value }} <button type="button" @click="logs.load()">Try again</button></p>
            <p v-if="logs.loading.value && logs.rows.value.length" class="al-refresh-state" role="status">Refreshing matching activity records…</p>
            <div v-if="logs.loading.value && !logs.rows.value.length" class="al-state" role="status">Loading activity records…</div>
            <div v-else-if="!logs.loading.value && !logs.rows.value.length" class="al-state"><strong>No activity records found</strong><span>Adjust the search or filters to view matching events.</span></div>
            <div v-else class="al-table-wrap">
                <table class="al-table">
                    <thead><tr><th>Actor</th><th><button type="button" @click="logs.sortBy('event')">Action {{ sortIcon('event') }}</button></th><th><button type="button" @click="logs.sortBy('module')">Module {{ sortIcon('module') }}</button></th><th>Target</th><th>Result</th><th class="al-ip-col">IP address</th><th><button type="button" @click="logs.sortBy('created_at')">Date &amp; time {{ sortIcon('created_at') }}</button></th><th></th></tr></thead>
                    <tbody><tr v-for="record in logs.rows.value" :key="record.id">
                        <td><div class="al-actor"><span class="al-avatar">{{ initials(record.actor?.name) }}</span><span><strong>{{ record.actor?.name || 'Unavailable actor' }}</strong><small>{{ record.actor?.email || 'Historical record' }}</small></span></div></td>
                        <td><strong class="al-action">{{ actionLabel(record.action) }}</strong><small class="al-description">{{ record.description }}</small></td>
                        <td><span class="al-module">{{ record.module }}</span></td>
                        <td><span class="al-target">{{ record.target?.label || record.target?.type || '—' }}</span><small v-if="record.target?.id" class="al-target-id">{{ record.target.type }} #{{ record.target.id }}</small></td>
                        <td><span class="al-status" :class="`is-${record.status}`"><i></i>{{ label(record.status) }}</span></td>
                        <td class="al-ip-col">{{ record.request?.ip_address || '—' }}</td>
                        <td><time :datetime="record.created_at">{{ formatDateTime(record.created_at) }}</time></td>
                        <td><RouterLink class="al-details" :to="{ name: 'admin.activity-logs.show', params: { logId: record.id } }">Details <span aria-hidden="true">→</span></RouterLink></td>
                    </tr></tbody>
                </table>
            </div>
            <footer class="al-pagination"><span>Showing {{ logs.rangeStart.value }}–{{ logs.rangeEnd.value }} of {{ logs.total.value.toLocaleString() }}</span><div><label>Rows <select v-model.number="logs.filters.per_page" @change="logs.applyFilters()"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></label><button type="button" :disabled="logs.page.value <= 1 || logs.loading.value" aria-label="Previous page" @click="logs.goToPage(logs.page.value - 1)">‹</button><span>Page {{ logs.page.value }} of {{ logs.lastPage.value }}</span><button type="button" :disabled="logs.page.value >= logs.lastPage.value || logs.loading.value" aria-label="Next page" @click="logs.goToPage(logs.page.value + 1)">›</button></div></footer>
        </section>
    </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { activityLogExportUrl } from '../../../features/activity-logs/admin/services/activityLogsApi.js';
import { useActivityLogs } from '../../../features/activity-logs/admin/composables/useActivityLogs.js';

const logs = useActivityLogs();
const search = ref('');
const actorSearch = ref('');
const today = new Date().toISOString().slice(0, 10);
const exporting = ref(false);
let searchTimer;
const presets = [
    { id: 'today', label: 'Today' }, { id: 'yesterday', label: 'Yesterday' }, { id: '7d', label: 'Last 7 days' },
    { id: '30d', label: 'Last 30 days' }, { id: 'month', label: 'This month' }, { id: 'previous', label: 'Previous month' },
];
const hasFilters = computed(() => Boolean(search.value || logs.filters.actor_id || logs.filters.module || logs.filters.subject_type || logs.filters.event || logs.filters.status || logs.filters.from || logs.filters.to));
const exportUrl = computed(() => activityLogExportUrl(logs.params()));
const activePreset = computed(() => presets.find((preset) => {
    const range = presetRange(preset.id);
    return logs.filters.from === range.from && logs.filters.to === range.to;
})?.id || 'custom');

function dateString(date) { return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`; }
function presetRange(id) {
    const end = new Date(); end.setUTCHours(0, 0, 0, 0);
    const start = new Date(end);
    if (id === 'yesterday') start.setUTCDate(start.getUTCDate() - 1);
    else if (id === '7d') start.setUTCDate(start.getUTCDate() - 6);
    else if (id === '30d') start.setUTCDate(start.getUTCDate() - 29);
    else if (id === 'month') start.setUTCDate(1);
    else if (id === 'previous') { start.setUTCDate(1); start.setUTCMonth(start.getUTCMonth() - 1); end.setUTCDate(0); }
    return { from: dateString(start), to: dateString(id === 'yesterday' ? start : end) };
}
function setPreset(id) { Object.assign(logs.filters, presetRange(id)); logs.applyFilters(); }
function scheduleSearch() { clearTimeout(searchTimer); searchTimer = setTimeout(() => { logs.filters.search = search.value.trim(); logs.applyFilters(); }, 300); }
function clearFilters() { clearTimeout(searchTimer); search.value = ''; actorSearch.value = ''; logs.clearFilters(); logs.loadFilters(); }
async function exportCsv() {
    clearTimeout(searchTimer);
    const nextSearch = search.value.trim();
    if (logs.filters.search !== nextSearch) {
        logs.filters.search = nextSearch;
        await logs.load();
    }
    if (!logs.total.value || logs.loading.value) return;
    exporting.value = true;
    window.location.assign(exportUrl.value);
    window.setTimeout(() => { exporting.value = false; }, 1200);
}
function number(value) { return new Intl.NumberFormat().format(Number(value || 0)); }
function label(value) { return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }
function actionLabel(value) { return label(String(value || '').replaceAll('.', ' ').replaceAll('_', ' ')); }
function initials(value) { return String(value || '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0]?.toUpperCase()).join('') || '?'; }
function formatDateTime(value) { return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—'; }
function sortIcon(field) { return logs.filters.sort === field ? (logs.filters.direction === 'asc' ? '↑' : '↓') : '↕'; }
onMounted(() => { Object.assign(logs.filters, presetRange('30d')); logs.loadFilters(); logs.load(); });
</script>

<style src="../../../../css/features/activity-logs/admin/index.css"></style>
