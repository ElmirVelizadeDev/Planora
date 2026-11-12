<template>
    <span class="category-badge" :class="`is-${size}`" :style="{ '--category-color': category.color || '#5B6CFA' }" :aria-label="showName ? undefined : category.name">
        <i aria-hidden="true"></i>
        <span v-if="showName">{{ category.name }}</span>
    </span>
</template>

<script setup>
defineProps({
    category: { type: Object, required: true },
    size: { type: String, default: 'compact' },
    showName: { type: Boolean, default: true },
});
</script>

<style src="../../../../../css/features/categories/shared/category-badge.css"></style>
