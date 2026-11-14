<template>
    <div v-if="loading || error || category" class="ac-backdrop" @click.self="$emit('close')">
        <section class="ac-dialog" role="dialog" aria-modal="true" aria-label="Category details">
            <p v-if="loading" class="ac-state" role="status">Loading category details…</p>
            <p v-else-if="error" class="ac-inline-error" role="alert">{{ error }}</p>
            <template v-else-if="category">
                <p class="ac-eyebrow">Category details</p><h2>{{ category.name }}</h2>
                <div class="ac-detail-color"><CategoryBadge :category="category" size="compact" :show-name="false" />{{ category.color }}</div>
                <dl class="ac-details"><div><dt>Description</dt><dd>{{ category.description || 'No description provided.' }}</dd></div><div><dt>Status</dt><dd><span class="ac-status" :class="`is-${category.status}`"><i></i>{{ category.status }}</span></dd></div><div><dt>Projects</dt><dd>{{ category.projects_count ?? 0 }}</dd></div><div><dt>Tasks</dt><dd>{{ category.tasks_count ?? 0 }}</dd></div><div><dt>Created</dt><dd>{{ formatCategoryDate(category.created_at) }}</dd></div><div><dt>Last updated</dt><dd>{{ formatCategoryDate(category.updated_at) }}</dd></div><div><dt>Category ID</dt><dd>#{{ category.id }}</dd></div></dl>
                <CategoryRelatedProjects :projects="category.projects" /><CategoryRelatedTasks :tasks="category.tasks" />
                <div class="ac-dialog-actions"><button class="ac-secondary" type="button" @click="$emit('close')">Close</button><button v-if="canManage && category.status === 'active'" class="ac-primary" type="button" @click="$emit('edit', category)">Edit category</button></div>
            </template>
            <div v-if="loading || error" class="ac-dialog-actions"><button class="ac-secondary" type="button" @click="$emit('close')">Close</button></div>
        </section>
    </div>
</template>

<script setup>
import CategoryBadge from '../../shared/components/CategoryBadge.vue';
import { formatCategoryDate } from '../../shared/utils/categoryFormatters.js';
import CategoryRelatedProjects from './CategoryRelatedProjects.vue';
import CategoryRelatedTasks from './CategoryRelatedTasks.vue';

defineProps({ category: { type: Object, default: null }, loading: Boolean, error: { type: String, default: '' }, canManage: { type: Boolean, default: true } });
defineEmits(['close', 'edit']);
</script>
