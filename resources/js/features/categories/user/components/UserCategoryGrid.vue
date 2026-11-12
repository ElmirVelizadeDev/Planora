<template>
    <div v-if="loading && !categories.length" class="user-categories-grid" role="status" aria-label="Loading categories">
        <div v-for="item in 6" :key="item" class="user-category-skeleton"><i></i><b></b><span></span><span></span></div>
    </div>
    <div v-else-if="error" class="user-categories-error" role="alert"><span>{{ error }}</span><button type="button" @click="$emit('retry')">Try again</button></div>
    <div v-else-if="!categories.length" class="user-categories-empty">
        <span class="user-categories-empty-mark" aria-hidden="true">◉</span><strong>{{ searchInput ? 'No categories match your search' : 'No categories available yet' }}</strong><p>{{ searchInput ? 'Try a different category name or description.' : 'Active workspace categories will appear here when they are available.' }}</p>
    </div>
    <div v-else class="user-categories-grid"><UserCategoryCard v-for="category in categories" :key="category.id" :category="category" /></div>
</template>

<script setup>
import UserCategoryCard from './UserCategoryCard.vue';
defineProps({ categories: { type: Array, default: () => [] }, loading: Boolean, error: { type: String, default: '' }, searchInput: { type: String, default: '' } });
defineEmits(['retry']);
</script>
