import { computed, onBeforeUnmount, reactive, ref, toRaw } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { getSettingsGroup, resetSettingsGroup, updateSettingsGroup } from '../../services/settingsApi.js';

function copy(value) {
    return JSON.parse(JSON.stringify(value));
}

export function useAdminSettingsForm(group) {
    const values = reactive({});
    const initial = ref({});
    const loading = ref(false);
    const loaded = ref(false);
    const saving = ref(false);
    const error = ref('');
    const notice = ref('');
    const validationErrors = ref({});
    const dirty = computed(() => JSON.stringify(toRaw(values)) !== JSON.stringify(initial.value));

    function apply(next) {
        Object.keys(values).forEach((key) => delete values[key]);
        Object.assign(values, next || {});
        initial.value = copy(toRaw(values));
    }

    async function load() {
        loading.value = true;
        error.value = '';
        try {
            apply(await getSettingsGroup(group));
            loaded.value = true;
        } catch (exception) {
            error.value = exception.response?.data?.message || 'Settings could not be loaded. Try again.';
        } finally {
            loading.value = false;
        }
    }

    async function save(extra = {}) {
        saving.value = true;
        error.value = '';
        notice.value = '';
        validationErrors.value = {};
        try {
            const response = await updateSettingsGroup(group, { ...toRaw(values), ...extra });
            apply(response.data);
            notice.value = response.message || 'Settings saved.';
            if (group === 'general') window.dispatchEvent(new CustomEvent('planora:settings-updated', { detail: response.data }));
            return true;
        } catch (exception) {
            validationErrors.value = exception.response?.data?.errors || {};
            error.value = exception.response?.data?.message || 'Settings could not be saved. Review the fields and try again.';
            return false;
        } finally {
            saving.value = false;
        }
    }

    async function reset() {
        saving.value = true;
        error.value = '';
        notice.value = '';
        try {
            const response = await resetSettingsGroup(group);
            apply(response.data);
            notice.value = response.message || 'Settings restored to defaults.';
            if (group === 'general') window.dispatchEvent(new CustomEvent('planora:settings-updated', { detail: response.data }));
        } catch (exception) {
            error.value = exception.response?.data?.message || 'Settings could not be reset. Try again.';
        } finally {
            saving.value = false;
        }
    }

    function mergeSavedValues(patch) {
        Object.assign(values, patch);
        const baseline = copy(initial.value);
        Object.assign(baseline, patch);
        initial.value = baseline;
    }

    const warnBeforeLeave = (event) => {
        if (!dirty.value) return;
        event.preventDefault();
        event.returnValue = '';
    };
    window.addEventListener('beforeunload', warnBeforeLeave);
    onBeforeRouteLeave(() => !dirty.value || window.confirm('You have unsaved settings. Leave this page and discard your changes?'));
    onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeLeave));

    return reactive({ values, loading, loaded, saving, error, notice, validationErrors, dirty, load, save, reset, mergeSavedValues });
}
