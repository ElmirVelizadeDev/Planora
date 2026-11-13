<template>
    <section class="report-table-card" :aria-busy="loading">
        <header class="report-chart-card__header">
            <div><h3>{{ title }}</h3><p>{{ description }}</p></div>
            <span v-if="meta.total" class="report-table-count">{{ number(meta.total) }} records</span>
        </header>
        <div class="report-table-toolbar">
            <label class="report-table-search"><span class="sr-only">Search {{ title }}</span><input v-model="search" type="search" placeholder="Search records…" maxlength="120"></label>
            <label class="report-table-page-size"><span>Rows</span><select v-model.number="perPage" @change="changePageSize"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option></select></label>
        </div>
        <p v-if="error" class="report-table-error" role="alert">{{ error }} <button type="button" @click="fetchPage">Retry</button></p>
        <div class="report-table-wrap">
            <table>
                <thead><tr><th v-for="column in columns" :key="column.key" :aria-sort="sortAria(column)">
                    <button v-if="column.sortable !== false" type="button" class="report-table-sort" @click="sortBy(column)">{{ column.label }}<span aria-hidden="true">{{ sortIndicator(column) }}</span></button>
                    <span v-else>{{ column.label }}</span>
                </th></tr></thead>
                <tbody v-if="loading"><tr><td :colspan="columns.length" class="report-table-message" role="status">Loading records…</td></tr></tbody>
                <tbody v-else-if="error"><tr><td :colspan="columns.length" class="report-table-message">Unable to display current results. Use Retry above.</td></tr></tbody>
                <tbody v-else-if="rows.length"><tr v-for="(row, index) in rows" :key="row.id || index"><td v-for="column in columns" :key="column.key"><slot :name="column.key" :row="row" :value="row[column.key]">{{ formatValue(row[column.key], column.format) }}</slot></td></tr></tbody>
                <tbody v-else><tr><td :colspan="columns.length" class="report-table-message">{{ search ? 'No records match this search.' : emptyMessage }}</td></tr></tbody>
            </table>
        </div>
        <footer v-if="meta.total && !error" class="report-table-pagination">
            <span>{{ number(meta.from || 0) }}–{{ number(meta.to || 0) }} of {{ number(meta.total) }}</span>
            <div><button type="button" :disabled="loading || page <= 1" aria-label="Previous page" @click="goToPage(page - 1)">Previous</button><span>Page {{ number(page) }} of {{ number(meta.last_page || 1) }}</span><button type="button" :disabled="loading || page >= (meta.last_page || 1)" aria-label="Next page" @click="goToPage(page + 1)">Next</button></div>
        </footer>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { getAdminReportTable } from '../services/adminReportsApi.js';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    table: { type: String, required: true },
    filters: { type: Object, required: true },
    rows: { type: Array, default: () => [] },
    pagination: { type: Object, default: () => ({}) },
    columns: { type: Array, required: true },
    emptyMessage: { type: String, default: 'No records in this period.' },
});

const defaultSort = computed(() => ({ registrations: 'created_at', projects: 'created_at', tasks: 'created_at', overdue: 'due_date', upcoming: 'due_date', categories: 'name', unused_categories: 'name', assignees: 'tasks' }[props.table] || 'created_at'));
const defaultDirection = computed(() => ['overdue', 'upcoming', 'categories', 'unused_categories'].includes(props.table) ? 'asc' : 'desc');
const rows = ref([...props.rows]);
const meta = ref({ current_page: 1, per_page: 10, total: props.rows.length, last_page: 1, from: props.rows.length ? 1 : null, to: props.rows.length || null, ...props.pagination });
const page = ref(1);
const perPage = ref(meta.value.per_page || 10);
const search = ref('');
const sort = ref(defaultSort.value);
const direction = ref(defaultDirection.value);
const loading = ref(false);
const error = ref('');
const initialized = ref(false);
const currentFilterSignature = ref('');
let searchTimer;
let controller;
let requestSequence = 0;
let unmounted = false;

const filterSignature = computed(() => JSON.stringify({ from: props.filters.from, to: props.filters.to, user_id: props.filters.user_id, project_id: props.filters.project_id, task_status: props.filters.task_status, category_id: props.filters.category_id }));
const customTableState = computed(() => Boolean(search.value) || page.value > 1 || perPage.value !== 10 || sort.value !== defaultSort.value || direction.value !== defaultDirection.value);

function number(value) { return new Intl.NumberFormat().format(Number(value) || 0); }
function formatValue(value, format) {
    if (value == null || value === '') return '—';
    if (format === 'date') return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${String(value).slice(0, 10)}T12:00:00Z`));
    if (format === 'datetime') return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(value));
    if (format === 'number') return number(value);
    return value;
}
function sortKey(column) { return column.sortKey || column.key; }
function sortAria(column) {
    if (sort.value !== sortKey(column)) return 'none';
    return direction.value === 'asc' ? 'ascending' : 'descending';
}
function sortIndicator(column) {
    if (sort.value !== sortKey(column)) return '↕';
    return direction.value === 'asc' ? '↑' : '↓';
}
function sortBy(column) {
    const key = sortKey(column);
    direction.value = sort.value === key && direction.value === 'asc' ? 'desc' : 'asc';
    sort.value = key;
    page.value = 1;
    fetchPage();
}
function goToPage(value) {
    page.value = value;
    fetchPage();
}
function changePageSize() {
    page.value = 1;
    fetchPage();
}
async function fetchPage() {
    const sequence = ++requestSequence;
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = '';
    try {
        const params = { ...props.filters, page: page.value, per_page: perPage.value, search: search.value, sort: sort.value, direction: direction.value };
        const response = await getAdminReportTable(props.table, params, controller.signal);
        if (unmounted || sequence !== requestSequence) return;
        if (currentFilterSignature.value !== filterSignature.value) {
            fetchPage();
            return;
        }
        const result = response.data?.data;
        rows.value = result?.data ?? [];
        meta.value = result?.meta ?? meta.value;
        page.value = meta.value.current_page || 1;
        perPage.value = meta.value.per_page || perPage.value;
    } catch (requestError) {
        if (!unmounted && sequence === requestSequence && requestError.code !== 'ERR_CANCELED') {
            error.value = requestError.response?.data?.message || 'This report table could not be loaded.';
        }
    } finally {
        if (!unmounted && sequence === requestSequence) loading.value = false;
    }
}
function acceptSnapshot() {
    if (!initialized.value) {
        rows.value = [...props.rows];
        meta.value = { ...meta.value, ...props.pagination };
        page.value = meta.value.current_page || 1;
        perPage.value = meta.value.per_page || 10;
        return;
    }

    if (customTableState.value) {
        fetchPage();
        return;
    }

    rows.value = [...props.rows];
    meta.value = { ...meta.value, ...props.pagination };
    page.value = meta.value.current_page || 1;
    perPage.value = meta.value.per_page || 10;
    error.value = '';
}

watch(filterSignature, () => {
    currentFilterSignature.value = filterSignature.value;
    clearTimeout(searchTimer);
    page.value = 1;
    requestSequence += 1;
    controller?.abort();
    loading.value = false;
});
watch(() => props.rows, acceptSnapshot);
watch(() => props.pagination, (value) => { meta.value = { ...meta.value, ...value }; }, { deep: true });
watch(search, () => {
    clearTimeout(searchTimer);
    requestSequence += 1;
    controller?.abort();
    loading.value = false;
    page.value = 1;
    searchTimer = setTimeout(fetchPage, 250);
});
onMounted(() => {
    currentFilterSignature.value = filterSignature.value;
    initialized.value = true;
    if (customTableState.value) fetchPage();
});
onBeforeUnmount(() => {
    unmounted = true;
    requestSequence += 1;
    clearTimeout(searchTimer);
    controller?.abort();
});
</script>
