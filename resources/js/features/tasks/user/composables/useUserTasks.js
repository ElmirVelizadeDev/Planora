import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { deleteUserTask, getUserTasks, updateUserTaskStatus } from '../services/userTaskApi.js';
import { getUserCategories } from '../../../categories/user/services/userCategoryApi.js';

export function useUserTasks() {
    const tasks = ref([]), categories = ref([]), loading = ref(false), error = ref(''), notice = ref('');
    const page = ref(1), perPage = ref(10), total = ref(0), lastPage = ref(1);
    const statistics = ref({ total: 0, in_progress: 0, completed: 0, pending: 0, overdue: 0 });
    const searchInput = ref(''), search = ref(''), sort = ref(''), filters = reactive({ status: '', priority: '', due: '', category_id: '', start_date: '', end_date: '' });
    const pendingStatuses = reactive({});
    const hasFilters = computed(() => Boolean(search.value || Object.values(filters).some(Boolean)));
    const hasToolbarChanges = computed(() => hasFilters.value || Boolean(sort.value));
    const rangeStart = computed(() => total.value ? (page.value - 1) * perPage.value + 1 : 0);
    const rangeEnd = computed(() => Math.min(page.value * perPage.value, total.value));
    let timer, requestId = 0, noticeTimer;

    function showNotice(message) {
        notice.value = message;
        clearTimeout(noticeTimer);
        noticeTimer = setTimeout(() => { notice.value = ''; }, 3200);
    }
    async function loadCategories() { try { categories.value = (await getUserCategories({ per_page: 50, sort: 'name' })).data || []; } catch { categories.value = []; } }

    async function load() {
        const id = ++requestId;
        loading.value = true;
        error.value = '';
        const params = {
            page: page.value,
            per_page: perPage.value,
            ...(sort.value ? { sort: sort.value } : {}),
            ...(search.value ? { search: search.value } : {}),
            ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value)),
        };
        try {
            const data = await getUserTasks(params);
            if (id !== requestId) return;
            tasks.value = data.data || [];
            const meta = data.meta || {};
            total.value = Number(meta.total || 0);
            page.value = Number(meta.current_page || page.value);
            lastPage.value = Math.max(1, Number(meta.last_page || 1));
            statistics.value = { ...statistics.value, ...(meta.statistics || {}) };
            if (page.value > lastPage.value) { page.value = lastPage.value; await load(); }
        } catch {
            if (id === requestId) error.value = 'Your tasks could not be loaded. Check your connection and try again.';
        } finally {
            if (id === requestId) loading.value = false;
        }
    }

    function onSearch() {
        clearTimeout(timer);
        timer = setTimeout(() => { search.value = searchInput.value.trim(); page.value = 1; load(); }, 300);
    }
    function applyFilters() { page.value = 1; load(); }
    function clearFilters() {
        clearTimeout(timer);
        searchInput.value = '';
        search.value = '';
        Object.keys(filters).forEach(key => { filters[key] = ''; });
        sort.value = '';
        page.value = 1;
        load();
    }
    function goToPage(value) {
        if (loading.value || value < 1 || value > lastPage.value || value === page.value) return;
        page.value = value;
        load();
    }
    async function setStatus(task, status) {
        if (pendingStatuses[task.id]) return;
        const previous = task.status;
        task.status = status;
        pendingStatuses[task.id] = true;
        try {
            await updateUserTaskStatus(task.id, status);
            showNotice(status === 'completed' ? 'Task marked as completed.' : 'Task status updated.');
            await load();
        } catch {
            task.status = previous;
            error.value = 'The task status could not be updated. Please try again.';
        } finally { delete pendingStatuses[task.id]; }
    }
    async function remove(task) {
        try {
            await deleteUserTask(task.id);
            showNotice('Task deleted.');
            await load();
        } catch {
            error.value = 'The task could not be deleted. Please try again.';
        }
    }

    onBeforeUnmount(() => { clearTimeout(timer); clearTimeout(noticeTimer); });
    return { tasks, categories, loading, error, notice, page, perPage, total, lastPage, statistics, searchInput, search, sort, filters, pendingStatuses, hasFilters, hasToolbarChanges, rangeStart, rangeEnd, load, loadCategories, onSearch, applyFilters, clearFilters, goToPage, setStatus, remove, showNotice };
}
