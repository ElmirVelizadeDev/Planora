import { onBeforeRouteLeave } from 'vue-router';

export function useUnsavedChangesConfirmation({ dirty, saving, confirmation }) {
    let pendingNavigation = null;
    let confirmationBeforeNavigation = null;

    function settleNavigation(shouldLeave) {
        if (!pendingNavigation) return;
        const current = pendingNavigation;
        pendingNavigation = null;
        confirmation.open = false;
        confirmation.mode = '';
        if (!shouldLeave && confirmationBeforeNavigation) Object.assign(confirmation, confirmationBeforeNavigation);
        confirmationBeforeNavigation = null;
        current.resolve(shouldLeave);
    }

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

    return { settleNavigation };
}
