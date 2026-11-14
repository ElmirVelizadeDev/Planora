<template>
    <main class="admin-categories" :aria-busy="list.loading">
        <CategoryManagementHeader :can-manage="isAdmin" @create="openCreate" />

        <section class="ac-stats" aria-label="Category statistics">
            <article><span class="ac-stat-icon">◉</span><strong>{{ list.stats.total }}</strong><span>Total categories</span></article>
            <article><span class="ac-stat-icon is-green">✓</span><strong>{{ list.stats.active }}</strong><span>Active</span></article>
            <article><span class="ac-stat-icon is-muted">◷</span><strong>{{ list.stats.archived }}</strong><span>Archived</span></article>
            <article><span class="ac-stat-icon is-blue">＋</span><strong>{{ list.stats.created_this_month }}</strong><span>Added this month</span></article>
        </section>

        <section class="ac-panel" aria-label="Category directory">
            <CategoryManagementToolbar
                v-model:search-input="filters.searchInput.value"
                :loading="list.loading.value"
                @refresh="reload"
                @search="filters.scheduleSearch(() => { list.page.value = 1; reload(); })"
            >
                <template #filters>
                    <CategoryManagementFilters
                        :status="filters.filters.status"
                        @update:status="filters.filters.status = $event"
                        @change="applyFilters"
                    />
                </template>
            </CategoryManagementToolbar>
            <div v-if="list.error.value" class="ac-error" role="alert">{{ list.error.value }} <button type="button" @click="reload">Try again</button></div>
            <div v-else-if="list.loading.value && !list.categories.value.length" class="ac-state" role="status">Loading categories…</div>
            <div v-else-if="!list.categories.value.length" class="ac-state"><strong>{{ filters.searchInput.value ? 'No categories match your search' : 'No categories found' }}</strong><span>Try another search or change the status filter.</span></div>
            <CategoryManagementTable
                v-else
                :categories="list.categories.value"
                :direction="filters.filters.direction"
                :can-manage="isAdmin"
                @sort="filters.toggleSort(() => { list.page.value = 1; reload(); })"
                @view="openView"
                @edit="openEdit"
                @archive="archiveTarget = $event"
                @restore="restoreCategory"
            />
            <CategoryManagementPagination
                :range-start="list.rangeStart.value"
                :range-end="list.rangeEnd.value"
                :total="list.total.value"
                :page="list.page.value"
                :last-page="list.lastPage.value"
                :loading="list.loading.value"
                @page="list.goToPage($event, filters.params.value)"
            />
        </section>

        <CategoryForm v-if="isAdmin" :open="formOpen" :category="formCategory" @close="closeForm" @saved="onFormSaved" />
        <CategoryDetails :category="viewing" :loading="detailLoading" :error="detailError" :can-manage="isAdmin" @close="closeDetails" @edit="openEdit" />
        <CategoryArchiveDialog v-if="isAdmin" :category="archiveTarget" :saving="actions.saving.value" :error="actions.error.value" @cancel="archiveTarget = null" @confirm="archiveCategory" />
        <p v-if="notice" class="ac-toast" role="status">{{ notice }}</p>
    </main>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { computed } from 'vue';
import { useAuthStore } from '../../../features/auth/stores/authStore.js';
import { getAdminCategory } from '../../../features/categories/admin/services/adminCategoryApi.js';
import { useAdminCategories } from '../../../features/categories/admin/composables/useAdminCategories.js';
import { useAdminCategoryActions } from '../../../features/categories/admin/composables/useAdminCategoryActions.js';
import { useAdminCategoryFilters } from '../../../features/categories/admin/composables/useAdminCategoryFilters.js';
import CategoryArchiveDialog from '../../../features/categories/admin/components/CategoryArchiveDialog.vue';
import CategoryDetails from '../../../features/categories/admin/components/CategoryDetails.vue';
import CategoryForm from '../../../features/categories/admin/components/CategoryForm.vue';
import CategoryManagementHeader from '../../../features/categories/admin/components/CategoryManagementHeader.vue';
import CategoryManagementFilters from '../../../features/categories/admin/components/CategoryManagementFilters.vue';
import CategoryManagementPagination from '../../../features/categories/admin/components/CategoryManagementPagination.vue';
import CategoryManagementTable from '../../../features/categories/admin/components/CategoryManagementTable.vue';
import CategoryManagementToolbar from '../../../features/categories/admin/components/CategoryManagementToolbar.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const isAdmin = computed(() => auth.hasRole('admin'));
const list = useAdminCategories();
const filters = useAdminCategoryFilters();
const formOpen = ref(false);
const formCategory = ref(null);
const viewing = ref(null);
const detailLoading = ref(false);
const detailError = ref('');
const archiveTarget = ref(null);
const notice = ref('');
let noticeTimer;
let detailRequestSequence = 0;
const actions = useAdminCategoryActions(reload, flash);

function reload() { return list.load(filters.params.value); }
function applyFilters() { list.page.value = 1; reload(); }
function openCreate() { formCategory.value = null; formOpen.value = true; }
function openEdit(category) { viewing.value = null; formCategory.value = category; formOpen.value = true; }
function closeForm() { formOpen.value = false; }
function flash(message) { notice.value = message; clearTimeout(noticeTimer); noticeTimer = setTimeout(() => { notice.value = ''; }, 3200); }

async function loadDetails(categoryId) {
    const sequence = ++detailRequestSequence;
    viewing.value = null;
    detailError.value = '';
    detailLoading.value = true;
    try {
        const response = await getAdminCategory(categoryId);
        if (sequence === detailRequestSequence) viewing.value = response.category;
    } catch (exception) {
        if (sequence === detailRequestSequence) detailError.value = exception.response?.data?.message || 'Category details could not be loaded.';
    } finally {
        if (sequence === detailRequestSequence) detailLoading.value = false;
    }
}

function openView(category) { loadDetails(category.id); }
function closeDetails() { detailRequestSequence++; viewing.value = null; }
async function onFormSaved(editing) {
    formOpen.value = false;
    flash(editing ? 'Category updated.' : 'Category created.');
    await reload();
    if (['admin.categories.create', 'admin.categories.edit'].includes(route.name)) await router.replace({ name: 'admin.categories.index' });
}
async function archiveCategory() {
    if (await actions.archive(archiveTarget.value)) archiveTarget.value = null;
}
async function restoreCategory(category) {
    if (!await actions.restore(category)) list.error.value = actions.error.value;
}

watch(() => [route.name, route.params.categoryId], async ([name, id]) => {
    if (name === 'admin.categories.create') { openCreate(); return; }
    if (!id || !['admin.categories.show', 'admin.categories.edit'].includes(name)) return;
    if (name === 'admin.categories.show') { await loadDetails(id); return; }
    const sequence = ++detailRequestSequence;
    try {
        const response = await getAdminCategory(id);
        if (sequence === detailRequestSequence) openEdit(response.category);
    } catch (exception) {
        if (sequence === detailRequestSequence) list.error.value = exception.response?.data?.message || 'Category details could not be loaded.';
    }
}, { immediate: true });

onMounted(reload);
onBeforeUnmount(() => { detailRequestSequence++; clearTimeout(noticeTimer); });
</script>

<style src="../../../../css/features/categories/admin/index.css"></style>
