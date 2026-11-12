<template>
    <label>Category name<input v-model="form.name" maxlength="120" required autofocus placeholder="e.g. Product" autocomplete="off"><small v-if="errors.name">{{ errors.name }}</small></label>
    <label>Description<textarea v-model="form.description" maxlength="2000" rows="3" placeholder="What work belongs in this category?"></textarea><small v-if="errors.description">{{ errors.description }}</small></label>
    <label class="ac-color-field">Category color<input v-model="form.color" type="color" aria-label="Category color"></label><small v-if="errors.color">{{ errors.color }}</small>
</template>

<script setup>
defineProps({ form: { type: Object, required: true }, errors: { type: Object, default: () => ({}) } });
</script>
