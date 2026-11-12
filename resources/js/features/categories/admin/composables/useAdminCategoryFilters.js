import { computed, onBeforeUnmount, reactive, ref } from 'vue';

export function useAdminCategoryFilters() {
    const filters = reactive({ status: 'active', sort: 'name', direction: 'asc' });
    const searchInput = ref('');
    const search = ref('');
    let searchTimer;

    const params = computed(() => ({ search: search.value, ...filters }));

    function scheduleSearch(onApply) {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            search.value = searchInput.value.trim();
            onApply();
        }, 300);
    }

    function toggleSort(onApply) {
        filters.direction = filters.direction === 'asc' ? 'desc' : 'asc';
        onApply();
    }

    onBeforeUnmount(() => clearTimeout(searchTimer));

    return { filters, searchInput, search, params, scheduleSearch, toggleSort };
}
