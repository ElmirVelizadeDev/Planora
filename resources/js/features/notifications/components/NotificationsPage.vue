<template>
    <main class="notifications-page">
        <header class="notifications-page-header">
            <div>
                <span class="notifications-eyebrow">Your updates</span>
                <h1>Notifications</h1>
                <p>Stay up to date with important activity in Planora.</p>
            </div>
            <div class="notifications-page-actions">
                <span class="notifications-unread-count">{{ store.unreadCount }} unread</span>
                <button type="button" :disabled="store.unreadCount === 0 || store.markingAll" @click="store.markAllRead()">
                    {{ store.markingAll ? 'Marking…' : 'Mark all as read' }}
                </button>
            </div>
        </header>

        <div class="notifications-filter" role="group" aria-label="Filter notifications">
            <button type="button" :class="{ 'is-selected': selectedFilter === 'all' }" :aria-pressed="selectedFilter === 'all'" @click="selectFilter('all')">All</button>
            <button type="button" :class="{ 'is-selected': selectedFilter === 'unread' }" :aria-pressed="selectedFilter === 'unread'" @click="selectFilter('unread')">Unread</button>
        </div>

        <p v-if="store.pageError" class="notifications-error" role="alert">
            {{ store.pageError }}
            <button type="button" @click="loadPage(store.currentPage)">Try again</button>
        </p>
        <div v-else-if="store.pageLoading" class="notifications-empty" role="status">Loading notifications…</div>
        <div v-else-if="!store.pageItems.length" class="notifications-empty">
            <span class="notifications-empty-icon" aria-hidden="true">✓</span>
            <strong>{{ selectedFilter === 'unread' ? 'No unread notifications' : 'You’re all caught up' }}</strong>
            <span>{{ selectedFilter === 'unread' ? 'New updates will appear here.' : 'Important updates will appear here.' }}</span>
        </div>
        <section v-else class="notifications-list" aria-label="Notification history">
            <NotificationItem
                v-for="item in store.pageItems"
                :key="item.id"
                :notification="item"
                :busy="store.markingAll || store.processingIds.includes(item.id)"
                @activate="openNotification"
            />
        </section>

        <nav v-if="store.lastPage > 1" class="notifications-pagination" aria-label="Notification pages">
            <span>Page {{ store.currentPage }} of {{ store.lastPage }}</span>
            <div>
                <button type="button" :disabled="store.currentPage <= 1 || store.pageLoading" @click="loadPage(store.currentPage - 1)">Previous</button>
                <button type="button" :disabled="store.currentPage >= store.lastPage || store.pageLoading" @click="loadPage(store.currentPage + 1)">Next</button>
            </div>
        </nav>
    </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../auth/stores/authStore.js';
import { useNotificationStore } from '../stores/notificationStore.js';
import NotificationItem from './NotificationItem.vue';

const router = useRouter();
const auth = useAuthStore();
const store = useNotificationStore();
const selectedFilter = ref(store.pageFilter);

async function loadPage(page = 1, filter = selectedFilter.value) {
    const loaded = await store.fetchPage(page, filter, 15);
    if (!loaded) selectedFilter.value = store.pageFilter;
    return loaded;
}
function selectFilter(filter) {
    selectedFilter.value = filter;
    return loadPage(1, filter);
}
async function openNotification(notification) {
    const updated = await store.markRead(notification.id);
    if (!updated?.destination) return;
    const { name, params } = updated.destination;
    const taskId = Number(params?.taskId);
    if (!Number.isSafeInteger(taskId) || taskId < 1) return;
    if (name === 'admin.tasks.show' && !auth.hasRole('admin')) return;
    if (name === 'tasks.show' && !auth.hasRole('user')) return;
    if (!['tasks.show', 'admin.tasks.show'].includes(name)) return;
    await router.push({ name, params: { taskId } });
}

onMounted(() => {
    store.setRecipient(auth.user?.id);
    selectedFilter.value = store.pageFilter;
    loadPage(1);
});
</script>

