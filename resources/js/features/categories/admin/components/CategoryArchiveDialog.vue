<template>
    <div v-if="category" class="ac-backdrop" @click.self="$emit('cancel')">
        <section class="ac-dialog ac-confirm" role="alertdialog" aria-modal="true" aria-labelledby="ac-archive-title">
            <p class="ac-eyebrow is-danger">Archive category</p><h2 id="ac-archive-title">Archive “{{ category.name }}”?</h2><p class="ac-dialog-copy">This category will be removed from the active list. You can restore it later.</p>
            <p v-if="error" class="ac-inline-error" role="alert">{{ error }}</p>
            <div class="ac-dialog-actions"><button class="ac-secondary" type="button" :disabled="saving" @click="$emit('cancel')">Cancel</button><button class="ac-danger-button" type="button" :disabled="saving" @click="$emit('confirm')">{{ saving ? 'Archiving…' : 'Archive category' }}</button></div>
        </section>
    </div>
</template>

<script setup>
defineProps({ category: { type: Object, default: null }, saving: Boolean, error: { type: String, default: '' } });
defineEmits(['cancel', 'confirm']);
</script>
