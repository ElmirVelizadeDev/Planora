<template>
    <main class="user-category-detail" :aria-busy="loading">
        <RouterLink class="user-category-back" :to="{ name: 'categories.index' }">← All categories</RouterLink>
        <div v-if="loading" class="user-category-detail-state" role="status">Loading category details…</div>
        <section v-else-if="error" class="user-category-detail-state" role="alert"><strong>{{ error }}</strong><p>The category may have been archived or is no longer available.</p><button type="button" @click="$emit('retry')">Try again</button></section>
        <article v-else-if="category" class="user-category-detail-card">
            <header class="user-category-detail-header"><CategoryBadge :category="category" size="detail" :show-name="false" /><div><p class="user-categories-eyebrow">Workspace category</p><h1>{{ category.name }}</h1></div></header>
            <section class="user-category-detail-description"><h2>About this category</h2><p>{{ category.description || 'No description provided.' }}</p></section>
            <section class="user-category-usage"><h2>Your work using this category</h2><p>{{ category.projects_count ?? 0 }} projects · {{ category.tasks_count ?? 0 }} tasks</p><UserCategoryRelatedProjects :projects="category.projects" /><UserCategoryRelatedTasks :tasks="category.tasks" /></section>
            <footer v-if="category.created_at" class="user-category-detail-meta"><span>Added to the workspace</span><strong>{{ formatCategoryDate(category.created_at) }}</strong></footer>
        </article>
    </main>
</template>

<script setup>
import CategoryBadge from '../../shared/components/CategoryBadge.vue';
import { formatCategoryDate } from '../../shared/utils/categoryFormatters.js';
import UserCategoryRelatedProjects from './UserCategoryRelatedProjects.vue';
import UserCategoryRelatedTasks from './UserCategoryRelatedTasks.vue';

defineProps({ category: { type: Object, default: null }, loading: Boolean, error: { type: String, default: '' } });
defineEmits(['retry']);
</script>
