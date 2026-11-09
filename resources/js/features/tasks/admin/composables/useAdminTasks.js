import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { deleteAdminTask, getAdminTasks, getTaskAssignees } from '../services/adminTaskApi.js';

export function useAdminTasks() {
    const tasks = ref([]), assignees = ref([]), loading = ref(false), error = ref('');
    const searchInput = ref(''), search = ref(''), page = ref(1), perPage = ref(10), total = ref(0), lastPage = ref(1), timer = ref(null);
    const filters = reactive({ status: '', priority: '', assigned_user_id: '', due_after: '' });
    const statistics = ref({ total: 0, in_progress: 0, completed: 0, pending: 0, overdue: 0 });
    const hasFilters = computed(() => Boolean(search.value || Object.values(filters).some(Boolean)));
    const rangeStart = computed(() => total.value ? (page.value - 1) * perPage.value + 1 : 0);
    const rangeEnd = computed(() => Math.min(page.value * perPage.value, total.value));
    let requestSequence = 0;

    async function loadTasks() {
        const sequence = ++requestSequence;
        loading.value = true;
        error.value = '';
        const params = {
            page: page.value,
            per_page: perPage.value,
            ...(search.value ? { search: search.value } : {}),
            ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value)),
        };

        try {
            const data = await getAdminTasks(params);
            if (sequence !== requestSequence) return;
            tasks.value = data.data || [];
            const meta = data.meta || {};
            total.value = Number(meta.total || 0);
            page.value = Number(meta.current_page || page.value);
            lastPage.value = Math.max(1, Number(meta.last_page || 1));
            if (page.value > lastPage.value) {
                page.value = lastPage.value;
                await loadTasks();
                return;
            }
            statistics.value = { ...statistics.value, ...(meta.statistics || {}) };
        } catch {
            if (sequence === requestSequence) {
                tasks.value = [];
                error.value = 'Task information could not be loaded. Please try again.';
            }
        } finally {
            if (sequence === requestSequence) loading.value = false;
        }
    }

    async function loadAssignees() {
        try {
            const data = await getTaskAssignees();
            assignees.value = data.data || [];
        } catch {
            assignees.value = [];
        }
    }

    function refreshTasks() {
        if (!loading.value) loadTasks();
    }

    function onSearch() {
        clearTimeout(timer.value);
        timer.value = setTimeout(() => {
            search.value = searchInput.value.trim();
            page.value = 1;
            loadTasks();
        }, 300);
    }

    function applyFilters() {
        page.value = 1;
        loadTasks();
    }

    function updateFilter(name, value) {
        if (!Object.prototype.hasOwnProperty.call(filters, name)) return;
        filters[name] = value;
    }

    function clearFilters() {
        clearTimeout(timer.value);
        searchInput.value = '';
        search.value = '';
        Object.keys(filters).forEach(key => { filters[key] = ''; });
        page.value = 1;
        loadTasks();
    }

    function goToPage(nextPage) {
        if (nextPage < 1 || nextPage > lastPage.value || loading.value) return;
        page.value = nextPage;
        loadTasks();
    }

    function changePerPage(size) {
        perPage.value = size;
        page.value = 1;
        loadTasks();
    }

    async function removeTask(task) {
        try {
            await deleteAdminTask(task.id);
            await loadTasks();
            return true;
        } catch {
            error.value = 'The task could not be deleted. Please try again.';
            return false;
        }
    }

    onMounted(() => {
        loadTasks();
        loadAssignees();
    });
    onBeforeUnmount(() => clearTimeout(timer.value));

    return {
        tasks, assignees, loading, error, searchInput, page, perPage, total, lastPage,
        filters, statistics, hasFilters, rangeStart, rangeEnd, loadTasks, refreshTasks,
        onSearch, applyFilters, updateFilter, clearFilters, goToPage, changePerPage, removeTask,
    };
}
