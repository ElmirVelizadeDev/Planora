<template>
    <div class="admin-projects-backdrop" @click.self="$emit('close')" @keydown.esc="$emit('close')">
        <section class="admin-projects-dialog admin-projects-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-project-delete-title" tabindex="-1" @keydown.esc="$emit('close')">
            <p class="admin-projects-dialog-eyebrow">Delete project</p>
            <h2 id="admin-project-delete-title">Are you sure?</h2>
            <p class="admin-projects-dialog-copy">This will permanently delete <strong>{{ project.name }}</strong>. Tasks linked to this project will be kept as standalone tasks.</p>
            <div v-if="error" class="admin-projects-dialog-error" role="alert">{{ error }}</div>
            <div class="admin-projects-dialog-actions"><button class="admin-projects-secondary-button" type="button" :disabled="saving" @click="$emit('close')">Cancel</button><button class="admin-projects-danger-button" type="button" :disabled="saving" @click="$emit('confirm')">{{ saving ? 'Deleting…' : 'Delete project' }}</button></div>
        </section>
    </div>
</template>

<script setup>
defineProps({ project: { type: Object, required: true }, saving: { type: Boolean, default: false }, error: { type: String, default: '' } });
defineEmits(['close', 'confirm']);
</script>
