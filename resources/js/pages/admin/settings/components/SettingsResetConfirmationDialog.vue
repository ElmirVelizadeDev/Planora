<template>
    <Teleport to="body">
        <Transition name="logout-dialog">
            <div v-if="open" class="logout-dialog-backdrop" @click.self="dismiss">
                <section
                    ref="dialog"
                    class="logout-dialog"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="settings-reset-dialog-title"
                    aria-describedby="settings-reset-dialog-description"
                    :aria-busy="loading"
                    tabindex="-1"
                    @keydown="onDialogKeydown"
                >
                    <span class="logout-dialog-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M20 7v5h-5" /><path d="M19 12a7 7 0 1 1-2-4.9L20 12" /></svg>
                    </span>
                    <h2 id="settings-reset-dialog-title">{{ title }}</h2>
                    <p id="settings-reset-dialog-description">{{ message }}</p>
                    <p v-if="error" class="logout-dialog-error" role="alert">{{ error }}</p>
                    <div class="logout-dialog-actions">
                        <button ref="cancelButton" class="logout-dialog-cancel" type="button" :disabled="loading" @click="dismiss">{{ cancelLabel }}</button>
                        <button ref="confirmButton" class="logout-dialog-confirm" type="button" :disabled="loading" @click="confirm">
                            <span v-if="loading" class="logout-dialog-spinner" aria-hidden="true" />
                            {{ loading ? loadingLabel : confirmLabel }}
                        </button>
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    title: { type: String, default: 'Reset Settings?' },
    message: { type: String, required: true },
    cancelLabel: { type: String, default: 'Cancel' },
    confirmLabel: { type: String, default: 'Reset to defaults' },
    loadingLabel: { type: String, default: 'Resetting…' },
});
const emit = defineEmits(['cancel', 'confirm']);
const dialog = ref(null);
const cancelButton = ref(null);
const confirmButton = ref(null);
let previousBodyOverflow = '';
let returnFocusTo = null;

watch(() => props.open, async (isOpen) => {
    if (isOpen) {
        returnFocusTo = document.activeElement;
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        await nextTick();
        cancelButton.value?.focus({ preventScroll: true });
        return;
    }

    document.body.style.overflow = previousBodyOverflow;
    await nextTick();
    returnFocusTo?.focus?.({ preventScroll: true });
    returnFocusTo = null;
});

watch(() => props.loading, async (isLoading) => {
    if (!props.open || !isLoading || !dialog.value) return;
    await nextTick();
    dialog.value.focus({ preventScroll: true });
});

watch(() => props.error, async (message) => {
    if (!message || !props.open) return;
    await nextTick();
    confirmButton.value?.focus({ preventScroll: true });
});

function dismiss() {
    if (!props.loading) emit('cancel');
}

function confirm() {
    if (!props.loading) emit('confirm');
}

function onDialogKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        dismiss();
        return;
    }

    if (event.key !== 'Tab') return;
    if (props.loading) {
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

onBeforeUnmount(() => {
    document.body.style.overflow = previousBodyOverflow;
});
</script>
