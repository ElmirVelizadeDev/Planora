<template>
    <header class="ac-header">
        <div><p class="ac-eyebrow">Administration</p><h1>Categories</h1><p class="ac-description">Organize work with a clear, consistent category system.</p></div>
        <button v-if="canManage" class="ac-primary" type="button" @click="$emit('create')">＋ Create category</button>
    </header>
</template>

<script setup>
defineProps({ canManage: { type: Boolean, default: true } });
defineEmits(['create']);
</script>
