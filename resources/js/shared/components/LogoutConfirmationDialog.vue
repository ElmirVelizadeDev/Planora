<template>
    <Teleport to="body">
        <Transition name="logout-dialog">
            <div v-if="isOpen" class="logout-dialog-backdrop" @click.self="dismiss">
                <section
                    ref="dialog"
                    class="logout-dialog"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="logout-dialog-title"
                    aria-describedby="logout-dialog-description"
                    :aria-busy="loading"
                    tabindex="-1"
                    @keydown="onDialogKeydown"
                >
                    <span class="logout-dialog-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg>
                    </span>
                    <h2 id="logout-dialog-title">Sign out?</h2>
                    <p id="logout-dialog-description">Are you sure you want to sign out of your Planora account?</p>
                    <p v-if="error" class="logout-dialog-error" role="alert">{{ error }}</p>
                    <div class="logout-dialog-actions">
                        <button ref="cancelButton" class="logout-dialog-cancel" type="button" :disabled="loading" @click="dismiss">Cancel</button>
                        <button ref="confirmButton" class="logout-dialog-confirm" type="button" :disabled="loading" @click="confirmLogout">
                            <span v-if="loading" class="logout-dialog-spinner" aria-hidden="true" />
                            {{ loading ? 'Logging out…' : 'Log out' }}
                        </button>
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useSignOut } from '../../features/auth/composables/useSignOut.js';
import { useLogoutConfirmation } from '../../features/auth/composables/useLogoutConfirmation.js';

const { isOpen, closeLogoutConfirmation } = useLogoutConfirmation();
const { loading, error, signOut } = useSignOut();
const dialog = ref(null);
const cancelButton = ref(null);
const confirmButton = ref(null);
let previousBodyOverflow = '';

watch(isOpen, async (open) => {
    if (open) {
        error.value = '';
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        await nextTick();
        cancelButton.value?.focus({ preventScroll: true });
        return;
    }

    document.body.style.overflow = previousBodyOverflow;
});

watch(loading, async (isLoading) => {
    if (!isOpen.value || !isLoading || !dialog.value) return;
    await nextTick();
    dialog.value.focus({ preventScroll: true });
});

watch(error, async (message) => {
    if (!message || !isOpen.value) return;
    await nextTick();
    confirmButton.value?.focus({ preventScroll: true });
});

function dismiss() {
    if (!loading.value) closeLogoutConfirmation();
}

function onDialogKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        dismiss();
        return;
    }

    if (event.key !== 'Tab') return;

    if (loading.value) {
        event.preventDefault();
        dialog.value?.focus();
        return;
    }

    const focusable = [...dialog.value.querySelectorAll('button:not(:disabled)')];
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (!first || !last) return;
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

async function confirmLogout() {
    if (loading.value) return;
    const succeeded = await signOut();
    if (succeeded) closeLogoutConfirmation({ restoreFocus: false });
}

onBeforeUnmount(() => {
    document.body.style.overflow = previousBodyOverflow;
});
</script>

<style src="../../../css/components/LogoutConfirmationDialog.css"></style>
