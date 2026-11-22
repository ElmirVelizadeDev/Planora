import { computed, ref, watch } from 'vue';
import { getUserCalendarTasks } from '../../../tasks/user/services/userTaskApi.js';
import { addDays, addMonths, dateKey, monthGrid, mondayStart, todayDate } from '../utils/calendarDateUtils.js';

export function useCalendar() {
    const selectedDate = ref(todayDate()), view = ref('month'), tasks = ref([]), loading = ref(false), error = ref('');
    const status = ref(''), priority = ref('');
    const range = computed(() => {
        if (view.value === 'month') {
            const days = monthGrid(selectedDate.value);
            return { start_date: dateKey(days[0]), end_date: dateKey(days[41]) };
        }
        if (view.value === 'week') {
            const start = mondayStart(selectedDate.value);
            return { start_date: dateKey(start), end_date: dateKey(addDays(start, 6)) };
        }
        if (view.value === 'day') return { start_date: dateKey(selectedDate.value), end_date: dateKey(selectedDate.value) };
        const start = addDays(selectedDate.value, -30);
        return { start_date: dateKey(start), end_date: dateKey(addDays(selectedDate.value, 90)) };
    });
    let request = 0;
    async function load() {
        const id = ++request;
        loading.value = true; error.value = '';
        try {
            const response = await getUserCalendarTasks({ ...range.value, ...(status.value ? { status: status.value } : {}), ...(priority.value ? { priority: priority.value } : {}) });
            if (id === request) tasks.value = response.data || [];
        } catch {
            if (id === request) error.value = 'Calendar tasks could not be loaded. Please try again.';
        } finally { if (id === request) loading.value = false; }
    }
    watch([range, status, priority], load, { immediate: true });
    const datedTasks = computed(() => tasks.value.filter(task => task.due_date));
    function move(amount) {
        selectedDate.value = view.value === 'month'
            ? addMonths(selectedDate.value, amount)
            : addDays(selectedDate.value, amount * (view.value === 'week' ? 7 : 1));
    }
    return { selectedDate, view, tasks: datedTasks, loading, error, status, priority, range, load, move };
}
