import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { getActivityLogs, getActivityLogFilters } from '../services/activityLogsApi.js';

export function useActivityLogs() {
    const rows = ref([]);
    const actors = ref([]);
    const modules = ref([]);
    const events = ref([]);
    const subjectTypes = ref([]);
    const statuses = ref([]);
    const loading = ref(false);
    const filterLoading = ref(false);
    const error = ref('');
    const page = ref(1);
    const lastPage = ref(1);
    const total = ref(0);
    const updatedAt = ref(null);
    const stats = reactive({ total_activities: 0, today_activities: 0, unique_actors: 0, security_events: 0 });
    const filters = reactive({ search: '', actor_id: '', module: '', subject_type: '', event: '', status: '', from: '', to: '', sort: 'created_at', direction: 'desc', per_page: 25 });
    const rangeStart = computed(() => total.value ? ((page.value - 1) * Number(filters.per_page)) + 1 : 0);
    const rangeEnd = computed(() => Math.min(page.value * Number(filters.per_page), total.value));
    let listSequence = 0;
    let filterSequence = 0;
    let debounceTimer;
    let listController;

    function params() {
        return { ...filters, page: page.value };
    }

    async function load() {
        const sequence = ++listSequence;
        listController?.abort();
        listController = new AbortController();
        loading.value = true;
        error.value = '';
        try {
            const response = await getActivityLogs(params(), { signal: listController.signal });
            if (sequence !== listSequence) return;
            rows.value = response.data || [];
            page.value = Number(response.meta?.current_page || 1);
            lastPage.value = Math.max(1, Number(response.meta?.last_page || 1));
            total.value = Number(response.meta?.total || 0);
            Object.assign(stats, response.meta?.statistics || {});
            updatedAt.value = new Date().toISOString();
        } catch (exception) {
            if (sequence === listSequence && exception.name !== 'CanceledError' && exception.code !== 'ERR_CANCELED') {
                error.value = exception.response?.data?.message || 'Activity logs could not be loaded. Please try again.';
            }
        } finally {
            if (sequence === listSequence) loading.value = false;
        }
    }

    async function loadFilters(actorSearch = '') {
        const sequence = ++filterSequence;
        filterLoading.value = true;
        try {
            const response = await getActivityLogFilters(actorSearch ? { actor_search: actorSearch } : {});
            if (sequence !== filterSequence) return;
            modules.value = response.data?.modules || [];
            events.value = response.data?.events || [];
            subjectTypes.value = response.data?.subject_types || [];
            statuses.value = response.data?.statuses || [];
            actors.value = response.data?.actors || [];
        } catch (exception) {
            if (sequence === filterSequence) error.value = exception.response?.data?.message || 'Filter options could not be loaded.';
        } finally {
            if (sequence === filterSequence) filterLoading.value = false;
        }
    }

    function searchActors(value) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadFilters(value), 250);
    }

    function applyFilters() {
        clearTimeout(debounceTimer);
        page.value = 1;
        load();
    }

    function clearFilters() {
        Object.assign(filters, { search: '', actor_id: '', module: '', subject_type: '', event: '', status: '', from: '', to: '', sort: 'created_at', direction: 'desc', per_page: 25 });
        applyFilters();
    }

    function sortBy(field) {
        if (filters.sort === field) filters.direction = filters.direction === 'asc' ? 'desc' : 'asc';
        else { filters.sort = field; filters.direction = field === 'created_at' ? 'desc' : 'asc'; }
        applyFilters();
    }

    function goToPage(nextPage) {
        if (nextPage < 1 || nextPage > lastPage.value || loading.value) return;
        page.value = nextPage;
        load();
    }

    onBeforeUnmount(() => {
        clearTimeout(debounceTimer);
        listSequence++;
        filterSequence++;
        listController?.abort();
    });

    return { rows, actors, modules, events, subjectTypes, statuses, loading, filterLoading, error, page, lastPage, total, updatedAt, stats, filters, rangeStart, rangeEnd, params, load, loadFilters, searchActors, applyFilters, clearFilters, sortBy, goToPage };
}
