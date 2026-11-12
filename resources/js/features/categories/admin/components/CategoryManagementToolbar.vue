<template>
    <div class="ac-toolbar">
        <div><h2>All categories</h2><p>Search and manage your workspace categories</p></div>
        <div class="ac-filters">
            <label class="ac-search"><span aria-hidden="true">⌕</span><span class="sr-only">Search</span><input :value="searchInput" type="search" aria-label="Search" @input="$emit('update:searchInput', $event.target.value); $emit('search')"></label>
            <slot name="filters" />
            <button class="ac-secondary ac-refresh" type="button" :disabled="loading" :aria-busy="loading" @click="$emit('refresh')"><svg class="ac-refresh-icon" :class="{ 'is-loading': loading }" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/></svg>{{ loading ? 'Refreshing…' : 'Refresh' }}</button>
        </div>
    </div>
</template>

<script setup>
defineProps({ searchInput: { type: String, default: '' }, loading: Boolean });
defineEmits(['refresh', 'search', 'update:searchInput']);
</script>
