<template>
    <div class="user-categories-toolbar">
        <label class="user-categories-search">
            <span aria-hidden="true">⌕</span><span class="user-categories-visually-hidden">Search categories</span>
            <input :value="searchInput" type="search" aria-label="Search categories" placeholder="Search categories" @input="$emit('update:searchInput', $event.target.value); $emit('search')">
        </label>
        <label class="user-categories-sort">Sort by
            <select :value="sort" :disabled="loading" aria-label="Sort categories" @change="$emit('sort', $event.target.value)"><option value="name">Name</option><option value="created_at">Date added</option></select>
        </label>
        <button class="user-categories-sort-direction" type="button" :disabled="loading" :aria-label="direction === 'asc' ? 'Sort ascending' : 'Sort descending'" :title="direction === 'asc' ? 'Ascending' : 'Descending'" @click="$emit('sort', sort)">{{ direction === 'asc' ? '↑' : '↓' }}</button>
        <button class="user-categories-refresh" type="button" :disabled="loading" :aria-busy="loading" @click="$emit('refresh')"><svg :class="{ 'is-loading': loading }" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/></svg>{{ loading ? 'Refreshing…' : 'Refresh' }}</button>
    </div>
</template>

<script setup>
defineProps({ searchInput: { type: String, default: '' }, sort: { type: String, default: 'name' }, direction: { type: String, default: 'asc' }, loading: Boolean });
defineEmits(['update:searchInput', 'search', 'sort', 'refresh']);
</script>
