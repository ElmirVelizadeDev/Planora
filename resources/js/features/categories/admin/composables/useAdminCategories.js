import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { getAdminCategories } from '../services/adminCategoryApi.js';

export function useAdminCategories() {
    const categories = ref([]);
    const loading = ref(false);
    const error = ref('');
    const page = ref(1);
    const lastPage = ref(1);
    const total = ref(0);
    const stats = reactive({ total: 0, active: 0, archived: 0, created_this_month: 0 });
    const perPage = 10;
    const rangeStart = computed(() => total.value ? (page.value - 1) * perPage + 1 : 0);
    const rangeEnd = computed(() => Math.min(page.value * perPage, total.value));
    let requestSequence = 0;

    async function load(params = {}) {
        const sequence = ++requestSequence;
        loading.value = true;
        error.value = '';
        try {
            const response = await getAdminCategories({ page: page.value, per_page: perPage, ...params });
            if (sequence !== requestSequence) return;
            categories.value = response.data || [];
            page.value = Number(response.meta?.current_page || 1);
            lastPage.value = Math.max(1, Number(response.meta?.last_page || 1));
            total.value = Number(response.meta?.total || 0);
            Object.assign(stats, response.meta?.statistics || {});
        } catch (exception) {
            if (sequence === requestSequence) {
                error.value = exception.response?.data?.message || 'Categories could not be loaded. Please try again.';
            }
        } finally {
            if (sequence === requestSequence) loading.value = false;
        }
    }

    function goToPage(value, params) {
        if (value < 1 || value > lastPage.value || loading.value) return;
        page.value = value;
        load(params);
    }

    onBeforeUnmount(() => { requestSequence += 1; });

    return { categories, loading, error, page, lastPage, total, stats, perPage, rangeStart, rangeEnd, load, goToPage };
}
