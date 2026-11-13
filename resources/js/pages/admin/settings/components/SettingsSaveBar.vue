<template>
    <footer class="settings-save-bar">
        <span>{{ dirty ? 'You have unsaved changes.' : 'All changes are saved.' }}</span>
        <div><button class="settings-button settings-button--secondary" type="button" :disabled="!dirty || saving" @click="$emit('reset')">Reset to defaults</button><button class="settings-button settings-button--primary" type="button" :disabled="!dirty || saving" @click="$emit('save')">{{ saving ? 'Saving…' : 'Save changes' }}</button></div>
    </footer>
</template>

<script setup>
defineProps({ dirty: Boolean, saving: Boolean });
defineEmits(['save', 'reset']);
</script>
