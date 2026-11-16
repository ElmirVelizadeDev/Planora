<template>
    <div v-if="canReceiveNotifications" class="header-menu notification-menu" ref="menu" @keydown.esc.stop.prevent="close(true)">
        <button
            ref="trigger"
            class="header-action-trigger header-icon-trigger notification-trigger"
            type="button"
            :aria-expanded="open"
            :aria-controls="popoverId"
            aria-haspopup="dialog"
            :aria-label="unreadLabel"
            @click="toggle"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
            <span v-if="store.unreadCount" class="notification-badge" aria-hidden="true">{{ badgeCount }}</span>
        </button>
        <section :id="popoverId" ref="popover" v-show="open" class="header-popover notification-popover" role="dialog" aria-label="Notifications" :aria-hidden="!open">
            <div class="notification-popover-heading">
                <strong>Notifications</strong>
                <span v-if="store.unreadCount" class="notification-heading-count">{{ store.unreadCount }} unread</span>
            </div>
            <button v-if="store.unreadCount" class="notification-mark-all" type="button" :disabled="store.markingAll" @click="store.markAllRead()">
                {{ store.markingAll ? 'Marking…' : 'Mark all as read' }}
            </button>
            <div v-if="store.previewLoading && !store.previewItems.length" class="notification-state" role="status">Loading notifications…</div>
            <div v-else-if="store.previewError" class="notification-state notification-state-error" role="alert">
                <span>{{ store.previewError }}</span>
                <button type="button" @click="store.loadRecent({ force: true })">Try again</button>
            </div>
            <div v-else-if="!store.previewItems.length" class="notification-state">You're all caught up.</div>
            <div v-else class="notification-items" aria-label="Recent notifications">
                <NotificationItem
                    v-for="item in store.previewItems"
                    :key="item.id"
                    :notification="item"
                    :busy="store.markingAll || store.processingIds.includes(item.id)"
                    @activate="openNotification"
                />
            </div>
            <RouterLink class="notification-view-all" :to="viewAllRoute" @click="close">View all notifications</RouterLink>
        </section>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../../features/auth/stores/authStore.js';
import { useNotificationStore } from '../../features/notifications/stores/notificationStore.js';
import NotificationItem from '../../features/notifications/components/NotificationItem.vue';

const props = defineProps({ variant: { type: String, required: true } });
const auth = useAuthStore();
const store = useNotificationStore();
const router = useRouter();
const open = ref(false);
const menu = ref(null);
const trigger = ref(null);
const popover = ref(null);
const popoverId = `notification-popover-${useId()}`;
const canReceiveNotifications = computed(() => auth.hasRole('user') || auth.hasRole('admin'));
const badgeCount = computed(() => store.unreadCount > 99 ? '99+' : store.unreadCount);
const unreadLabel = computed(() => store.unreadCount ? `Notifications, ${store.unreadCount} unread` : 'Notifications');
const viewAllRoute = computed(() => ({ name: props.variant === 'admin' ? 'admin.notifications.index' : 'notifications.index' }));

watch(() => auth.user?.id, (id) => {
    store.setRecipient(canReceiveNotifications.value ? id : null);
    if (id && canReceiveNotifications.value) store.loadRecent();
}, { immediate: true });

function close(restoreFocus = false) {
    const focusWasInside = popover.value?.contains(document.activeElement) ?? false;
    open.value = false;
    if (restoreFocus && focusWasInside) nextTick(() => trigger.value?.focus());
}
async function toggle() {
    if (open.value) {
        close();
        return;
    }

    open.value = true;
    store.loadRecent();
    await nextTick();
    popover.value?.querySelector('button:not(:disabled), a[href]')?.focus();
}
function onOutside(event) {
    if (!menu.value?.contains(event.target)) close();
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
    close();
    await router.push({ name, params: { taskId } });
}

onMounted(() => {
    document.addEventListener('pointerdown', onOutside);
});
onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside));
</script>

<style src="../../../css/components/notifications.css"></style>
