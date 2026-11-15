<template>
    <Teleport to="body">
        <Transition name="logout-dialog">
            <div v-if="open" class="logout-dialog-backdrop" @click.self="dismiss">
                <section ref="dialog" class="logout-dialog" role="alertdialog" aria-modal="true" aria-labelledby="app-confirm-title" aria-describedby="app-confirm-description" tabindex="-1" @keydown="onDialogKeydown">
                    <span class="logout-dialog-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 3 21 20H3L12 3Z"/><path d="M12 9v5M12 17h.01"/></svg>
                    </span>
                    <h2 id="app-confirm-title">{{ title }}</h2>
                    <p id="app-confirm-description">{{ message }}</p>
                    <div class="logout-dialog-actions">
                        <button ref="cancelButton" class="logout-dialog-cancel" type="button" @click="dismiss">{{ cancelLabel }}</button>
                        <button ref="confirmButton" class="logout-dialog-confirm" :class="{ 'is-danger': danger }" type="button" @click="emit('confirm')">{{ confirmLabel }}</button>
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
    title: { type: String, required: true },
    message: { type: String, required: true },
    cancelLabel: { type: String, default: 'Cancel' },
    confirmLabel: { type: String, default: 'Continue' },
    danger: { type: Boolean, default: false },
});
const emit = defineEmits(['cancel', 'confirm']);
const dialog = ref(null);
const cancelButton = ref(null);
let previousBodyOverflow = '';
let returnFocusTo = null;
let scrollLockActive = false;

watch(() => props.open, async (open) => {
    if (open) {
        returnFocusTo = document.activeElement;
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        scrollLockActive = true;
        await nextTick();
        cancelButton.value?.focus({ preventScroll: true });
        return;
    }
    document.body.style.overflow = previousBodyOverflow;
    scrollLockActive = false;
    await nextTick();
    returnFocusTo?.focus?.({ preventScroll: true });
    returnFocusTo = null;
});

function dismiss() { emit('cancel'); }
function onDialogKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        dismiss();
        return;
    }
    if (event.key !== 'Tab') return;
    const focusable = [...dialog.value.querySelectorAll('button:not(:disabled)')];
    const first = focusable[0];
    const last = focusable.at(-1);
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}

onBeforeUnmount(() => {
    if (scrollLockActive) document.body.style.overflow = previousBodyOverflow;
});
</script>

<style src="../../../css/components/LogoutConfirmationDialog.css"></style>
