import { onBeforeUnmount, ref, watch } from 'vue';
import { getUserCategory } from '../services/userCategoryApi.js';

export function useUserCategoryDetails(categoryId) {
    const category = ref(null);
    const loading = ref(true);
    const error = ref('');
    let requestSequence = 0;

    async function load() {
        const sequence = ++requestSequence;
        loading.value = true;
        error.value = '';
        category.value = null;
        try {
            const response = await getUserCategory(categoryId());
            if (sequence === requestSequence) category.value = response.category;
        } catch {
            if (sequence === requestSequence) error.value = 'This category could not be found.';
        } finally {
            if (sequence === requestSequence) loading.value = false;
        }
    }

    watch(categoryId, load, { immediate: true });
    onBeforeUnmount(() => { requestSequence += 1; });

    return { category, loading, error, load };
}
