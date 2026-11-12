import { ref } from 'vue';
import { archiveAdminCategory, restoreAdminCategory } from '../services/adminCategoryApi.js';

export function useAdminCategoryActions(onChanged, notify) {
    const saving = ref(false);
    const error = ref('');

    async function archive(category) {
        if (!category || saving.value) return false;
        saving.value = true;
        error.value = '';
        try {
            await archiveAdminCategory(category.id);
            notify('Category archived.');
            await onChanged();
            return true;
        } catch (exception) {
            error.value = exception.response?.data?.message || 'Category could not be archived.';
            return false;
        } finally {
            saving.value = false;
        }
    }

    async function restore(category) {
        if (!category || saving.value) return false;
        saving.value = true;
        error.value = '';
        try {
            await restoreAdminCategory(category.id);
            notify('Category restored.');
            await onChanged();
            return true;
        } catch (exception) {
            error.value = exception.response?.data?.message || 'Category could not be restored.';
            return false;
        } finally {
            saving.value = false;
        }
    }

    return { saving, error, archive, restore };
}
