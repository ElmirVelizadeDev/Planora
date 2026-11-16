import { defineStore } from 'pinia';
import { ref } from 'vue';
import { fetchNotifications, markAllNotificationsRead, markNotificationRead } from '../services/notificationsApi.js';

const PREVIEW_TTL_MS = 30_000;

export const useNotificationStore = defineStore('notifications', () => {
    const previewItems = ref([]);
    const previewLoading = ref(false);
    const previewError = ref('');
    const previewLoadedAt = ref(0);
    const pageItems = ref([]);
    const pageLoading = ref(false);
    const pageError = ref('');
    const unreadCount = ref(0);
    const currentPage = ref(1);
    const lastPage = ref(1);
    const total = ref(0);
    const pageFilter = ref('all');
    const processingIds = ref([]);
    const markingAll = ref(false);
    const ownerId = ref(null);
    let previewSequence = 0;
    let pageSequence = 0;
    let unreadSequence = 0;
    let mutationRevision = 0;
    let previewPromise = null;
    let readQueue = Promise.resolve();

    function setRecipient(id) {
        const nextId = id == null ? null : String(id);
        if (ownerId.value === nextId) return;

        ownerId.value = nextId;
        previewSequence++;
        pageSequence++;
        unreadSequence++;
        mutationRevision++;
        previewPromise = null;
        readQueue = Promise.resolve();
        previewItems.value = [];
        previewLoading.value = false;
        previewError.value = '';
        previewLoadedAt.value = 0;
        pageItems.value = [];
        pageLoading.value = false;
        pageError.value = '';
        unreadCount.value = 0;
        currentPage.value = 1;
        lastPage.value = 1;
        total.value = 0;
        pageFilter.value = 'all';
        processingIds.value = [];
        markingAll.value = false;
    }

    function applyUnreadCount(response, expectedRevision, requestSequence) {
        const count = response?.data?.meta?.unread_count;
        if (Number.isInteger(count) && expectedRevision === mutationRevision && requestSequence === unreadSequence) {
            unreadCount.value = Math.max(0, count);
        }
    }

    async function fetchPage(page = 1, selectedFilter = pageFilter.value, perPage = 15) {
        const requestOwner = ownerId.value;
        const sequence = ++pageSequence;
        const requestRevision = mutationRevision;
        const countSequence = ++unreadSequence;
        pageLoading.value = true;
        pageError.value = '';

        try {
            const response = await fetchNotifications({ page, filter: selectedFilter, per_page: perPage });
            if (sequence !== pageSequence || requestOwner !== ownerId.value || requestRevision !== mutationRevision) return false;
            pageItems.value = response.data.data || [];
            currentPage.value = response.data.meta?.current_page || page;
            lastPage.value = response.data.meta?.last_page || 1;
            total.value = response.data.meta?.total || 0;
            pageFilter.value = selectedFilter;
            applyUnreadCount(response, requestRevision, countSequence);
            return true;
        } catch {
            if (sequence !== pageSequence || requestOwner !== ownerId.value) return false;
            pageError.value = 'Notifications could not be loaded. Please try again.';
            return false;
        } finally {
            if (sequence === pageSequence) pageLoading.value = false;
        }
    }

    async function loadRecent({ force = false } = {}) {
        if (!force && previewLoadedAt.value && Date.now() - previewLoadedAt.value < PREVIEW_TTL_MS) return true;
        if (previewPromise) return previewPromise;

        const requestOwner = ownerId.value;
        const sequence = ++previewSequence;
        const requestRevision = mutationRevision;
        const countSequence = ++unreadSequence;
        previewLoading.value = true;
        previewError.value = '';
        const promise = (async () => {
            try {
                const response = await fetchNotifications({ page: 1, filter: 'all', per_page: 8 });
                if (sequence !== previewSequence || requestOwner !== ownerId.value || requestRevision !== mutationRevision) return false;
                previewItems.value = response.data.data || [];
                previewLoadedAt.value = Date.now();
                applyUnreadCount(response, requestRevision, countSequence);
                return true;
            } catch {
                if (sequence !== previewSequence || requestOwner !== ownerId.value) return false;
                previewError.value = 'Notifications could not be loaded. Please try again.';
                return false;
            } finally {
                if (sequence === previewSequence) previewLoading.value = false;
            }
        })();
        previewPromise = promise;
        try {
            return await promise;
        } finally {
            if (previewPromise === promise) previewPromise = null;
        }
    }

    function enqueueReadOperation(operation) {
        const pending = readQueue.then(operation, operation);
        readQueue = pending.catch(() => {});
        return pending;
    }

    function updateReadItem(updated) {
        previewItems.value = previewItems.value.map((item) => item.id === updated.id ? updated : item);
        if (pageFilter.value === 'unread' && updated.read_at) {
            const existed = pageItems.value.some((item) => item.id === updated.id);
            pageItems.value = pageItems.value.filter((item) => item.id !== updated.id);
            if (existed) total.value = Math.max(0, total.value - 1);
        } else {
            pageItems.value = pageItems.value.map((item) => item.id === updated.id ? updated : item);
        }
    }

    async function reconcileAfterFailure(requestOwner) {
        await readQueue;
        if (requestOwner !== ownerId.value) return;
        const updates = [fetchPage(currentPage.value, pageFilter.value, 15)];
        if (previewLoadedAt.value) updates.push(loadRecent({ force: true }));
        await Promise.all(updates);
    }

    async function markRead(id) {
        const existing = [...previewItems.value, ...pageItems.value].find((item) => item.id === id);
        if (existing?.read_at) return existing;
        if (processingIds.value.includes(id) || markingAll.value || ownerId.value === null) return null;

        const requestOwner = ownerId.value;
        mutationRevision++;
        unreadSequence++;
        processingIds.value = [...processingIds.value, id];
        const operation = enqueueReadOperation(async () => {
            if (requestOwner !== ownerId.value) return null;
            try {
                const response = await markNotificationRead(id);
                if (requestOwner !== ownerId.value) return null;
                const updated = response.data.data;
                mutationRevision++;
                unreadSequence++;
                updateReadItem(updated);
                applyUnreadCount(response, mutationRevision, unreadSequence);
                return updated;
            } catch {
                if (requestOwner === ownerId.value) {
                    mutationRevision++;
                    unreadSequence++;
                    pageError.value = 'This notification could not be updated. Please try again.';
                    previewError.value = 'This notification could not be updated. Please try again.';
                }
                return null;
            } finally {
                if (requestOwner === ownerId.value) processingIds.value = processingIds.value.filter((itemId) => itemId !== id);
            }
        });
        const updated = await operation;
        if (!updated && requestOwner === ownerId.value) await reconcileAfterFailure(requestOwner);
        return updated;
    }

    async function markAllRead() {
        if (markingAll.value || unreadCount.value === 0 || ownerId.value === null) return true;

        const requestOwner = ownerId.value;
        mutationRevision++;
        unreadSequence++;
        markingAll.value = true;
        pageError.value = '';
        previewError.value = '';
        const operation = enqueueReadOperation(async () => {
            if (requestOwner !== ownerId.value) return false;
            try {
                await markAllNotificationsRead();
                if (requestOwner !== ownerId.value) return false;
                mutationRevision++;
                unreadSequence++;
                const readAt = new Date().toISOString();
                previewItems.value = previewItems.value.map((item) => item.read_at ? item : { ...item, read_at: readAt });
                if (pageFilter.value === 'unread') {
                    pageItems.value = [];
                    total.value = 0;
                    currentPage.value = 1;
                    lastPage.value = 1;
                } else {
                    pageItems.value = pageItems.value.map((item) => item.read_at ? item : { ...item, read_at: readAt });
                }
                unreadCount.value = 0;
                return true;
            } catch {
                if (requestOwner === ownerId.value) {
                    mutationRevision++;
                    unreadSequence++;
                    pageError.value = 'Notifications could not be updated. Please try again.';
                    previewError.value = 'Notifications could not be updated. Please try again.';
                }
                return false;
            } finally {
                if (requestOwner === ownerId.value) markingAll.value = false;
            }
        });
        const updated = await operation;
        if (!updated && requestOwner === ownerId.value) await reconcileAfterFailure(requestOwner);
        return updated;
    }

    return {
        previewItems, previewLoading, previewError, previewLoadedAt,
        pageItems, pageLoading, pageError, unreadCount, currentPage, lastPage, total, pageFilter,
        processingIds, markingAll, fetchPage, loadRecent, markRead, markAllRead, setRecipient,
    };
});
