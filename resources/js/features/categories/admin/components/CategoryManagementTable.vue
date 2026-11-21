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
                    <td data-label="Actions"><AdminActionDropdown :active-key="activeActionKey" :item-key="category.id" :label="`Actions for ${category.name}`" :items="actionItems(category)" @update:active-key="activeActionKey = $event" /></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import AdminActionDropdown from '../../../admin/components/AdminActionDropdown.vue';
import CategoryBadge from '../../shared/components/CategoryBadge.vue';
import { formatCategoryDate } from '../../shared/utils/categoryFormatters.js';

const props = defineProps({ categories: { type: Array, default: () => [] }, direction: { type: String, default: 'asc' }, canManage: { type: Boolean, default: true } });
const emit = defineEmits(['sort', 'view', 'edit', 'archive', 'restore']);
const activeActionKey = ref(null);

function actionItems(category) {
    const items = [{ key: 'view', label: 'View', icon: 'view', onSelect: () => emit('view', category) }];
    if (props.canManage) {
        items.push({ key: 'edit', label: 'Edit', icon: 'edit', disabled: category.status === 'archived', onSelect: () => emit('edit', category) });
        items.push(category.status === 'active'
            ? { key: 'archive', label: 'Archive', icon: 'archive', danger: true, separatorBefore: true, onSelect: () => emit('archive', category) }
            : { key: 'restore', label: 'Restore', icon: 'restore', onSelect: () => emit('restore', category) });
    }
    return items;
}

watch(() => props.categories.map(category => String(category.id)), (ids) => {
    if (activeActionKey.value !== null && !ids.includes(String(activeActionKey.value))) activeActionKey.value = null;
});
</script>
