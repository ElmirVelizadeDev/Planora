<template>
    <main class="user-categories-page" :aria-busy="categories.loading.value">
        <header class="user-categories-header">
            <div><p class="user-categories-eyebrow">Workspace directory</p><h1>Categories</h1><p>Browse the categories available across your workspace.</p></div>
            <span class="user-categories-count">{{ categories.total.value }} {{ categories.total.value === 1 ? 'category' : 'categories' }}</span>
        </header>
        <section class="user-categories-panel" aria-label="Category directory">
            <UserCategoryToolbar v-model:search-input="filters.searchInput.value" :sort="filters.sort.value" :direction="filters.direction.value" :loading="categories.loading.value" @search="filters.scheduleSearch()" @sort="filters.setSort($event)" @refresh="load" />
            <UserCategoryGrid :categories="categories.categories.value" :loading="categories.loading.value" :error="categories.error.value" :search-input="filters.searchInput.value" @retry="load" />
            <UserCategoryPagination :last-page="categories.lastPage.value" :error="categories.error.value" :range-start="categories.rangeStart.value" :range-end="categories.rangeEnd.value" :total="categories.total.value" :page="categories.page.value" :loading="categories.loading.value" @page="categories.changePage($event, filters.params.value)" />
        </section>
    </main>
</template>

<script setup>
import { onMounted } from 'vue';
import { useUserCategories } from '../../../features/categories/user/composables/useUserCategories.js';
import { useUserCategoryFilters } from '../../../features/categories/user/composables/useUserCategoryFilters.js';
import UserCategoryGrid from '../../../features/categories/user/components/UserCategoryGrid.vue';
import UserCategoryPagination from '../../../features/categories/user/components/UserCategoryPagination.vue';
import UserCategoryToolbar from '../../../features/categories/user/components/UserCategoryToolbar.vue';

const categories = useUserCategories();
const filters = useUserCategoryFilters(() => {
    categories.page.value = 1;
    categories.load(filters.params.value);
});
function load() { categories.load(filters.params.value); }
onMounted(load);
</script>

<style src="../../../../css/features/categories/user/index.css"></style>
<style src="../../../../css/features/categories/user/shared.css"></style>
