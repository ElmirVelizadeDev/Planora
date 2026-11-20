import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { getUserTaskReport } from '../../../tasks/user/services/userTaskApi.js';

const dateKey = (date) => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');

export function useUserTaskReport() {
    const today = new Date();
    const filters = reactive({ start_date: dateKey(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 29)), end_date: dateKey(today), status: '', priority: '' });
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
        const end = new Date();
        const start = new Date(end.getFullYear(), end.getMonth(), end.getDate());
        if (preset === '7') start.setDate(start.getDate() - 6);
        else if (preset === '30') start.setDate(start.getDate() - 29);
        else if (preset === 'month') start.setDate(1);
        filters.start_date = dateKey(start);
        filters.end_date = dateKey(end);
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
