import { computed, onBeforeUnmount, ref } from 'vue';

export function useUserCategoryFilters(onChange) {
    const searchInput = ref('');
    const search = ref('');
    const sort = ref('name');
    const direction = ref('asc');
    const params = computed(() => ({ search: search.value, sort: sort.value, direction: direction.value }));
    let timer;

    function scheduleSearch() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            search.value = searchInput.value.trim();
            onChange();
        }, 300);
    }

    function setSort(value) {
        if (sort.value === value) direction.value = direction.value === 'asc' ? 'desc' : 'asc';
        else { sort.value = value; direction.value = 'asc'; }
        onChange();
    }

    onBeforeUnmount(() => clearTimeout(timer));

    return { searchInput, search, sort, direction, params, scheduleSearch, setSort };
}
