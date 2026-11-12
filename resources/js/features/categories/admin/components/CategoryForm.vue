<template>
    <div v-if="open" class="ac-backdrop" @click.self="$emit('close')">
        <section class="ac-dialog" role="dialog" aria-modal="true" aria-labelledby="ac-form-title">
            <p class="ac-eyebrow">Category details</p><h2 id="ac-form-title">{{ category ? 'Edit category' : 'Create category' }}</h2><p class="ac-dialog-copy">Set a name, description, and identifying color.</p>
            <form @submit.prevent="submit">
                <CategoryFormFields :form="form" :errors="errors" />
                <p v-if="error" class="ac-inline-error" role="alert">{{ error }}</p>
                <div class="ac-dialog-actions"><button class="ac-secondary" type="button" :disabled="saving" @click="$emit('close')">Cancel</button><button class="ac-primary" type="submit" :disabled="saving">{{ saving ? 'Saving…' : category ? 'Save changes' : 'Create category' }}</button></div>
            </form>
        </section>
    </div>
</template>

<script setup>
import { watch } from 'vue';
import { useAdminCategoryForm } from '../composables/useAdminCategoryForm.js';
import CategoryFormFields from './CategoryFormFields.vue';

const props = defineProps({ open: Boolean, category: { type: Object, default: null } });
const emit = defineEmits(['close', 'saved']);
const { form, errors, error, saving, reset, submit: save } = useAdminCategoryForm((editing) => emit('saved', editing));
watch(() => [props.open, props.category?.id], ([open]) => { if (open) reset(props.category); }, { immediate: true });

function submit() { save(); }
</script>
