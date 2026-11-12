import { computed, onBeforeUnmount, ref } from 'vue';
import { getUserCategories } from '../services/userCategoryApi.js';

export function useUserCategories() {
    const categories = ref([]);
    const loading = ref(false);
    const error = ref('');
    const page = ref(1);
    const total = ref(0);
    const lastPage = ref(1);
    const perPage = 9;
    const rangeStart = computed(() => total.value ? ((page.value - 1) * perPage) + 1 : 0);
    const rangeEnd = computed(() => Math.min(page.value * perPage, total.value));
    let requestSequence = 0;

    async function load(params = {}) {
        const sequence = ++requestSequence;
        loading.value = true;
        error.value = '';
        try {
            const response = await getUserCategories({ page: page.value, per_page: perPage, ...params });
            if (sequence !== requestSequence) return;
            categories.value = response.data || [];
            page.value = Number(response.meta?.current_page || 1);
            total.value = Number(response.meta?.total || 0);
            lastPage.value = Math.max(1, Number(response.meta?.last_page || 1));
        } catch (exception) {
            if (sequence !== requestSequence) return;
            error.value = exception.response?.status === 401
                ? 'Your session has expired. Please sign in again.'
                : exception.response?.status === 403
                    ? 'You do not have permission to view categories.'
                    : 'Categories could not be loaded. Please try again.';
        } finally {
            if (sequence === requestSequence) loading.value = false;
        }
    }

    function changePage(value, params) {
        if (value < 1 || value > lastPage.value || loading.value) return;
        page.value = value;
        load(params);
    }

    onBeforeUnmount(() => { requestSequence += 1; });

    return { categories, loading, error, page, total, lastPage, perPage, rangeStart, rangeEnd, load, changePage };
}
