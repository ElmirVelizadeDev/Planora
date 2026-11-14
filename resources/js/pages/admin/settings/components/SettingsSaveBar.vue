<template>
    <footer class="settings-save-bar">
        <span aria-live="polite">{{ settingsSaveStatus(dirty, saving) }}</span>
        <div><button class="settings-button settings-button--secondary" type="button" :disabled="!dirty || saving" @click="$emit('reset')">Reset to defaults</button><button class="settings-button settings-button--primary" type="button" :disabled="!dirty || saving" @click="$emit('save')">{{ saving ? 'Saving…' : 'Save changes' }}</button></div>
        <SettingsResetConfirmationDialog
            :open="confirmation?.open || false"
            :loading="saving"
            :error="confirmation?.error || ''"
            :title="confirmation?.title || ''"
            :message="confirmation?.message || ''"
            :confirm-label="confirmation?.confirmLabel || ''"
            :loading-label="confirmation?.loadingLabel || ''"
            :cancel-label="confirmation?.mode === 'leave' ? 'Stay on page' : 'Cancel'"
            @cancel="$emit('cancel')"
            @confirm="$emit('confirm')"
        />
    </footer>
</template>

<script setup>
import { settingsSaveStatus } from '../../../../features/settings/admin/composables/settingsState.js';
import SettingsResetConfirmationDialog from './SettingsResetConfirmationDialog.vue';

defineProps({ dirty: Boolean, saving: Boolean, confirmation: { type: Object, default: null } });
defineEmits(['save', 'reset', 'confirm', 'cancel']);
</script>
