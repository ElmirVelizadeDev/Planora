<template>
    <RouterLink class="user-category-card" :to="{ name: 'categories.show', params: { categoryId: category.id } }">
        <CategoryBadge :category="category" size="card" :show-name="false" />
        <div class="user-category-card-copy"><h2>{{ category.name }}</h2><p>{{ category.description || 'No description provided.' }}</p></div>
        <span class="user-category-open" aria-hidden="true">↗</span>
    </RouterLink>
</template>

<script setup>
import CategoryBadge from '../../shared/components/CategoryBadge.vue';
defineProps({ category: { type: Object, required: true } });
</script>
