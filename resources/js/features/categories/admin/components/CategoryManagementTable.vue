<template>
    <div class="ac-table-wrap">
        <table class="ac-table">
            <thead><tr><th><button class="ac-sort" type="button" @click="$emit('sort')">Category {{ direction === 'asc' ? '↑' : '↓' }}</button></th><th>Description</th><th>Projects</th><th>Tasks</th><th>Status</th><th>Created</th><th class="ac-actions-heading">Actions</th></tr></thead>
            <tbody>
                <tr v-for="category in categories" :key="category.id">
                    <td data-label="Category"><CategoryBadge :category="category" /></td>
                    <td data-label="Description" class="ac-description-cell">{{ category.description || '—' }}</td>
                    <td data-label="Projects">{{ category.projects_count ?? 0 }}</td><td data-label="Tasks">{{ category.tasks_count ?? 0 }}</td>
                    <td data-label="Status"><span class="ac-status" :class="`is-${category.status}`"><i></i>{{ category.status }}</span></td>
                    <td data-label="Created">{{ formatCategoryDate(category.created_at) }}</td>
                    <td data-label="Actions"><div class="ac-actions"><button type="button" @click="$emit('view', category)">View</button><button type="button" :disabled="category.status === 'archived'" @click="$emit('edit', category)">Edit</button><button v-if="category.status === 'active'" class="is-danger" type="button" @click="$emit('archive', category)">Archive</button><button v-else type="button" @click="$emit('restore', category)">Restore</button></div></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import CategoryBadge from '../../shared/components/CategoryBadge.vue';
import { formatCategoryDate } from '../../shared/utils/categoryFormatters.js';

defineProps({ categories: { type: Array, default: () => [] }, direction: { type: String, default: 'asc' } });
defineEmits(['sort', 'view', 'edit', 'archive', 'restore']);
</script>
