<template>
    <div class="admin-task-delete-backdrop" @click.self="$emit('close')" @keydown.esc="$emit('close')">
        <section class="admin-task-delete-dialog" role="dialog" aria-modal="true" tabindex="-1" :aria-labelledby="titleId" @keydown.esc="$emit('close')">
            <p class="admin-task-delete-eyebrow">Remove task</p>
            <h2 :id="titleId">Delete this task?</h2>
            <div class="admin-task-delete-identity">
                <span class="admin-task-delete-icon" aria-hidden="true">✓</span>
                <div><strong>{{ task.title || 'Untitled task' }}</strong><span>Task #{{ task.id }}</span></div>
            </div>
            <div class="admin-task-delete-warning" role="note">
                <strong>This task will be removed from the active task list.</strong>
                <span>The task will be soft-deleted and can be restored later by an administrator.</span>
            </div>
            <label class="admin-task-delete-confirm-field" :for="inputId">Type <strong>DELETE</strong> to confirm
                <input :id="inputId" ref="confirmationInput" v-model="confirmation" type="text" maxlength="6" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="DELETE" :aria-invalid="confirmation.length > 0 && confirmation !== 'DELETE'" @input="$emit('clear-error')">
                <small>The confirmation is case-sensitive.</small>
            </label>
            <p v-if="error" class="admin-task-delete-error" role="alert">{{ error }}</p>
            <div class="admin-task-delete-actions">
                <button class="admin-task-delete-cancel" type="button" :disabled="saving" @click="$emit('close')">Cancel</button>
                <button class="admin-users-danger-button" type="button" :disabled="saving || confirmation !== 'DELETE'" @click="$emit('confirm')">
                    <span v-if="saving" class="admin-task-delete-spinner" aria-hidden="true"></span>
                    {{ saving ? 'Deleting…' : 'Delete task' }}
                </button>
            </div>
        </section>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    task: { type: Object, required: true },
    saving: { type: Boolean, default: false },
    error: { type: String, default: '' },
});
defineEmits(['close', 'confirm', 'clear-error']);

const titleId = `admin-task-delete-title-${props.task.id}`;
const inputId = `admin-task-delete-confirmation-${props.task.id}`;
const confirmation = ref('');
const confirmationInput = ref(null);

onMounted(() => confirmationInput.value?.focus());
</script>

<style scoped>
.admin-task-delete-backdrop { position: fixed; inset: 0; z-index: 80; display: grid; place-items: center; padding: 18px; background: rgb(28 35 52 / 40%); backdrop-filter: blur(2px); }
.admin-task-delete-dialog { width: min(100%, 420px); max-height: min(82vh, 720px); overflow-y: auto; overscroll-behavior: contain; padding: 24px; border: 1px solid var(--color-border); border-radius: 14px; color: var(--color-text); background: var(--color-surface); box-shadow: 0 16px 50px rgb(32 40 58 / 18%); }
.admin-task-delete-eyebrow { margin: 0 0 7px; color: #a9535a; font-size: 10px; font-weight: 650; text-transform: uppercase; letter-spacing: .06em; }
.admin-task-delete-dialog h2 { margin: 0; color: var(--color-text); font-size: 17px; font-weight: 680; letter-spacing: -.02em; }
.admin-task-delete-identity { display: grid; grid-template-columns: 42px minmax(0, 1fr); align-items: center; gap: 11px; margin-top: 15px; }
.admin-task-delete-icon { display: grid; width: 42px; height: 42px; place-items: center; border-radius: 50%; color: #a9535a; background: #fbefef; font-size: 17px; font-weight: 700; }
.admin-task-delete-identity > div { display: grid; min-width: 0; gap: 3px; }
.admin-task-delete-identity strong { overflow: hidden; color: var(--color-text); font-size: 12px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
.admin-task-delete-identity > div > span { color: var(--color-text-secondary); font-size: 10px; }
.admin-task-delete-warning { display: grid; gap: 4px; margin-top: 16px; padding: 11px 12px; border: 1px solid rgb(185 87 96 / 22%); border-radius: 9px; color: #8e454d; background: #fbefef; font-size: 10px; line-height: 1.45; }
.admin-task-delete-warning > strong { font-size: 10px; font-weight: 680; }
.admin-task-delete-confirm-field { display: grid; gap: 6px; margin-top: 16px; color: var(--color-text); font-size: 10px; font-weight: 620; }
.admin-task-delete-confirm-field > strong { color: #a84952; font-weight: 750; letter-spacing: .04em; }
.admin-task-delete-confirm-field input { width: 100%; min-height: 38px; padding: 9px 10px; border: 1px solid var(--color-border); border-radius: 9px; outline: 0; color: var(--color-text); background: var(--color-surface); font: inherit; font-size: 11px; }
.admin-task-delete-confirm-field input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgb(91 108 250 / 10%); }
.admin-task-delete-confirm-field input[aria-invalid="true"] { border-color: #c76b72; }
.admin-task-delete-confirm-field small { color: var(--color-text-secondary); font-size: 9px; font-weight: 400; }
.admin-task-delete-error { margin: 14px 0 0; padding: 10px 12px; border: 1px solid rgb(190 87 94 / 25%); border-radius: 9px; color: #a9535a; background: #fbefef; font-size: 10px; }
.admin-task-delete-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 23px; }
.admin-task-delete-cancel, .admin-users-danger-button { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: 7px; padding: 0 14px; border: 1px solid transparent; border-radius: 9px; font: inherit; font-size: 11px; font-weight: 650; text-decoration: none; cursor: pointer; transition: background 140ms ease, border-color 140ms ease, transform 140ms ease; }
.admin-task-delete-cancel { border-color: var(--color-border); color: var(--color-text); background: var(--color-surface); }
.admin-task-delete-cancel:hover { border-color: var(--color-primary); color: var(--color-primary); }
.admin-users-danger-button { color: #fff; background: #b95760; }
.admin-users-danger-button:hover { background: #a84952; }
.admin-task-delete-cancel:disabled, .admin-users-danger-button:disabled { cursor: not-allowed; opacity: .55; transform: none; }
.admin-task-delete-spinner { width: 13px; height: 13px; border: 2px solid rgb(255 255 255 / 40%); border-top-color: #fff; border-radius: 50%; animation: admin-task-delete-spin 700ms linear infinite; }
@keyframes admin-task-delete-spin { to { transform: rotate(360deg); } }
@media (max-width: 600px) { .admin-task-delete-dialog { max-height: 86vh; padding: 20px 17px; } }
</style>
