import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { getUserTaskReport } from '../../../tasks/user/services/userTaskApi.js';
import { utcDateKey } from '../../../../shared/utils/dateOnly.js';

export function useUserTaskReport() {
    const today = new Date();
    const utcToday = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate()));
    const initialStart = new Date(utcToday);
    initialStart.setUTCDate(initialStart.getUTCDate() - 29);
    const filters = reactive({ start_date: utcDateKey(initialStart), end_date: utcDateKey(utcToday), status: '', priority: '' });
    const report = ref(null), loading = ref(false), error = ref('');
    const periodLabel = computed(() => `${filters.start_date} – ${filters.end_date}`);
    let requestId = 0, controller;

    async function load() {
        const id = ++requestId;
        controller?.abort();
        controller = new AbortController();
        loading.value = true;
        error.value = '';
        try {
            const response = await getUserTaskReport({ ...filters }, controller.signal);
            if (id === requestId) report.value = response.data || null;
        } catch (cause) {
            if (id === requestId && cause.name !== 'CanceledError' && cause.code !== 'ERR_CANCELED') error.value = 'Your report could not be loaded. Please try again.';
        } finally {
            if (id === requestId) loading.value = false;
        }
    }

    function setPreset(preset) {
        const now = new Date();
        const end = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
        const start = new Date(end);
        if (preset === '7') start.setUTCDate(start.getUTCDate() - 6);
        else if (preset === '30') start.setUTCDate(start.getUTCDate() - 29);
        else if (preset === 'month') start.setUTCDate(1);
        filters.start_date = utcDateKey(start);
        filters.end_date = utcDateKey(end);
        load();
    }

    function applyFilters() { load(); }
    function resetFilters() {
        filters.status = '';
        filters.priority = '';
        setPreset('30');
    }

    onBeforeUnmount(() => { requestId += 1; controller?.abort(); });
    return { filters, report, loading, error, periodLabel, load, setPreset, applyFilters, resetFilters };
}
