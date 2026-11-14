import { computed, onBeforeUnmount, reactive, ref, toRaw } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { getSettingsGroup, resetSettingsGroup, updateSettingsGroup } from '../../services/settingsApi.js';
import { settingsAreDirty } from './settingsState.js';

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
    const dirty = computed(() => settingsAreDirty(values, initial.value));
    const confirmation = reactive({ open: false, mode: '', title: '', message: '', confirmLabel: '', loadingLabel: '', error: '' });
    let pendingNavigation = null;
    let confirmationBeforeNavigation = null;
    let afterAction = null;

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
        if (saving.value || loading.value || !loaded.value || !dirty.value) return false;
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
        if (saving.value || loading.value || !loaded.value) return false;
        saving.value = true;
        error.value = '';
        notice.value = '';
        try {
            const response = await resetSettingsGroup(group);
            apply(response.data);
            notice.value = response.message || 'Settings restored to defaults.';
            if (group === 'general') window.dispatchEvent(new CustomEvent('planora:settings-updated', { detail: response.data }));
            return true;
        } catch (exception) {
            error.value = exception.response?.data?.message || 'Settings could not be reset. Try again.';
            return false;
        } finally {
            saving.value = false;
        }
    }

    function openConfirmation(mode, title, message, confirmLabel, loadingLabel) {
        if (saving.value || loading.value || !loaded.value) return;
        confirmation.open = true;
        confirmation.mode = mode;
        confirmation.title = title;
        confirmation.message = message;
        confirmation.confirmLabel = confirmLabel;
        confirmation.loadingLabel = loadingLabel;
        confirmation.error = '';
    }

    function openSaveConfirmation(message, onSuccess = null) {
        if (!dirty.value) return;
        afterAction = onSuccess;
        openConfirmation('save', 'Save changes?', message, 'Save changes', 'Saving…');
    }

    function openResetConfirmation(message, onSuccess = null) {
        afterAction = onSuccess;
        openConfirmation('reset', 'Reset Settings?', message, 'Reset to defaults', 'Resetting…');
    }

    function settleNavigation(shouldLeave) {
        if (!pendingNavigation) return;
        const current = pendingNavigation;
        pendingNavigation = null;
        confirmation.open = false;
        confirmation.mode = '';
        if (!shouldLeave && confirmationBeforeNavigation) {
            Object.assign(confirmation, confirmationBeforeNavigation);
        }
        confirmationBeforeNavigation = null;
        current.resolve(shouldLeave);
    }

    function cancelConfirmation() {
        if (confirmation.mode === 'leave') {
            settleNavigation(false);
            return;
        }
        confirmation.open = false;
        confirmation.mode = '';
        confirmation.error = '';
        afterAction = null;
    }

    async function confirmConfirmation() {
        if (saving.value || !confirmation.open) return;
        if (confirmation.mode === 'leave') {
            settleNavigation(true);
            return;
        }

        const mode = confirmation.mode;
        const succeeded = mode === 'save' ? await save() : await reset();
        if (!succeeded) {
            confirmation.error = error.value;
            return;
        }

        const callback = afterAction;
        afterAction = null;
        callback?.();
        confirmation.open = false;
        confirmation.mode = '';
        confirmation.error = '';
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
    onBeforeRouteLeave(() => {
        if (saving.value) return false;
        if (!dirty.value) return true;
        if (pendingNavigation) return pendingNavigation.promise;

        confirmationBeforeNavigation = confirmation.open ? { ...confirmation } : null;
        confirmation.open = true;
        confirmation.mode = 'leave';
        confirmation.title = 'Unsaved changes';
        confirmation.message = 'You have unsaved changes. If you leave this page, those changes will be discarded.';
        confirmation.confirmLabel = 'Leave page';
        confirmation.loadingLabel = '';
        confirmation.error = '';

        const promise = new Promise((resolve) => { pendingNavigation = { promise: null, resolve }; });
        pendingNavigation.promise = promise;
        return promise;
    });
    onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeLeave));

    return reactive({ values, loading, loaded, saving, error, notice, validationErrors, dirty, confirmation, load, save, reset, mergeSavedValues, openSaveConfirmation, openResetConfirmation, cancelConfirmation, confirmConfirmation });
}
