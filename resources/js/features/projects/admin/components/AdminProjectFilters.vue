<template>
    <div class="admin-projects-filters">
        <label class="admin-projects-search">
            <span aria-hidden="true">⌕</span><span class="sr-only">Search projects</span>
            <input :value="searchInput" type="search" placeholder="Search projects or owners" @input="$emit('update:searchInput', $event.target.value); $emit('search')">
        </label>
        <label><span class="sr-only">Filter by status</span><select :value="status" @change="$emit('update:status', $event.target.value); $emit('apply')"><option value="">All statuses</option><option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
        <button class="admin-projects-secondary-button admin-projects-refresh" type="button" :disabled="loading" :aria-busy="loading" @click="$emit('refresh')"><svg class="admin-projects-refresh-icon" :class="{ 'is-loading': loading }" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9A7 7 0 0 1 18 6l2 6M4 12l2 6a7 7 0 0 0 12.4-3"/></svg>{{ loading ? 'Refreshing…' : 'Refresh' }}</button>
        <button v-if="hasFilters" class="admin-projects-clear" type="button" @click="$emit('clear')">Clear filters</button>
    </div>
</template>

<script setup>
import { ADMIN_PROJECT_STATUSES } from '../composables/useAdminProjects.js';

defineProps({ searchInput: { type: String, default: '' }, status: { type: String, default: '' }, loading: { type: Boolean, default: false }, hasFilters: { type: Boolean, default: false } });
defineEmits(['update:searchInput', 'update:status', 'search', 'apply', 'refresh', 'clear']);
const statuses = ADMIN_PROJECT_STATUSES;
</script>
