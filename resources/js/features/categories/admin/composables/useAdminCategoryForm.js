import { reactive, ref } from 'vue';
import { createAdminCategory, updateAdminCategory } from '../services/adminCategoryApi.js';

export function useAdminCategoryForm(onSaved) {
    const categoryId = ref(null);
    const form = reactive({ name: '', description: '', color: '#5B6CFA' });
    const errors = reactive({});
    const error = ref('');
    const saving = ref(false);

    function reset(category = null) {
        categoryId.value = category?.id ?? null;
        Object.assign(form, {
            name: category?.name || '',
            description: category?.description || '',
            color: category?.color || '#5B6CFA',
        });
        Object.keys(errors).forEach((key) => delete errors[key]);
        error.value = '';
    }

    async function submit() {
        if (saving.value) return;
        saving.value = true;
        Object.keys(errors).forEach((key) => delete errors[key]);
        error.value = '';
        const editing = categoryId.value !== null;
        try {
            const payload = { ...form };
            if (editing) await updateAdminCategory(categoryId.value, payload);
            else await createAdminCategory(payload);
            await onSaved?.(editing);
        } catch (exception) {
            const data = exception.response?.data;
            if (data?.errors) Object.entries(data.errors).forEach(([key, messages]) => { errors[key] = messages[0]; });
            else error.value = data?.message || 'Category could not be saved. Please try again.';
        } finally {
            saving.value = false;
        }
    }

    return { categoryId, form, errors, error, saving, reset, submit };
}
