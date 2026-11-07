import { nextTick, ref, shallowRef } from 'vue';

const isOpen = ref(false);
const returnFocusTo = shallowRef(null);

export function useLogoutConfirmation() {
    function requestLogout(event) {
        if (isOpen.value) return;
        returnFocusTo.value = event?.currentTarget ?? document.activeElement;
        isOpen.value = true;
    }

    async function closeLogoutConfirmation({ restoreFocus = true } = {}) {
        if (!isOpen.value) return;
        isOpen.value = false;

        if (restoreFocus) {
            await nextTick();
            returnFocusTo.value?.focus?.({ preventScroll: true });
        }

        returnFocusTo.value = null;
    }

    return { isOpen, requestLogout, closeLogoutConfirmation };
}
