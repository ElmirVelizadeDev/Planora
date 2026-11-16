import assert from 'node:assert/strict';
import { mock, test } from 'node:test';

const api = {
    fetch: async () => ({ data: { data: [], meta: { current_page: 1, last_page: 1, total: 0, unread_count: 0 } } }),
    mark: async (id) => ({ data: { data: { ...notification(id, new Date().toISOString()) }, meta: { unread_count: 0 } } }),
    markAll: async () => ({ data: { data: { updated: 0, unread_count: 0 } } }),
};

function notification(id, readAt = null) {
    return { id, type: 'task.assigned', title: `Task ${id}`, description: 'Assigned', read_at: readAt, created_at: new Date().toISOString(), destination: null };
}

const apiModule = new URL('../../resources/js/features/notifications/services/notificationsApi.js', import.meta.url).href;
mock.module(apiModule, {
    namedExports: {
        fetchNotifications: (...args) => api.fetch(...args),
        markNotificationRead: (...args) => api.mark(...args),
        markAllNotificationsRead: (...args) => api.markAll(...args),
    },
});

const { createPinia, setActivePinia } = await import('pinia');
const { useNotificationStore } = await import('../../resources/js/features/notifications/stores/notificationStore.js');

function storeFor(id = 1) {
    setActivePinia(createPinia());
    const store = useNotificationStore();
    store.setRecipient(id);
    return store;
}

function response(items, unreadCount = items.filter((item) => !item.read_at).length) {
    return { data: { data: items, meta: { current_page: 1, last_page: 1, total: items.length, unread_count: unreadCount } } };
}

test('preview and history caches remain separate and preview refreshes after its freshness window', async () => {
    const calls = [];
    api.fetch = async (params) => {
        calls.push(params);
        return response([notification(calls.length)]);
    };
    const store = storeFor();

    await store.loadRecent();
    assert.deepEqual(store.previewItems.map((item) => item.id), [1]);
    assert.deepEqual(store.pageItems, []);
    await store.fetchPage(1, 'unread', 15);
    assert.deepEqual(store.pageItems.map((item) => item.id), [2]);
    assert.deepEqual(store.previewItems.map((item) => item.id), [1]);
    await store.loadRecent();
    assert.equal(calls.length, 2, 'a fresh preview is reused');

    store.previewLoadedAt = 0;
    await store.loadRecent();
    assert.equal(calls.length, 3, 'an expired preview is refreshed');
    assert.equal(calls[2].per_page, 8);
});

test('read mutations serialize individual and bulk operations and keep unread state non-negative', async () => {
    const store = storeFor();
    api.fetch = async () => response([notification(1), notification(2)], 2);
    await store.loadRecent();
    const events = [];
    let finishOne;
    let finishAll;
    api.mark = (id) => {
        events.push(`read:${id}`);
        return new Promise((resolve) => { finishOne = resolve; });
    };
    api.markAll = () => {
        events.push('all');
        return new Promise((resolve) => { finishAll = resolve; });
    };

    const firstRead = store.markRead(1);
    const duplicateRead = store.markRead(1);
    const markAll = store.markAllRead();
    assert.equal(await duplicateRead, null);
    await new Promise(setImmediate);
    assert.deepEqual(events, ['read:1']);

    finishOne({ data: { data: notification(1, '2026-11-15T00:00:00Z'), meta: { unread_count: 1 } } });
    await new Promise(setImmediate);
    assert.deepEqual(events, ['read:1', 'all']);
    finishAll({ data: { data: { updated: 1, unread_count: 0 } } });
    await Promise.all([firstRead, markAll]);

    assert.equal(store.unreadCount, 0);
    assert.ok(store.unreadCount >= 0);
    assert.ok(store.previewItems.every((item) => item.read_at));
});

test('account changes and logout clear recipient-specific state and ignore old responses', async () => {
    const store = storeFor('account-a');
    let finishOld;
    api.fetch = () => new Promise((resolve) => { finishOld = resolve; });
    const oldRequest = store.loadRecent();
    await new Promise(setImmediate);

    store.setRecipient('account-b');
    api.fetch = async () => response([notification(20)], 1);
    await store.loadRecent();
    finishOld(response([notification(10)], 8));
    await oldRequest;

    assert.deepEqual(store.previewItems.map((item) => item.id), [20]);
    assert.equal(store.unreadCount, 1);
    store.setRecipient(null);
    assert.deepEqual(store.previewItems, []);
    assert.deepEqual(store.pageItems, []);
    assert.equal(store.unreadCount, 0);
});

test('failed read operation reconciles server state without optimistic local corruption', async () => {
    const store = storeFor();
    api.fetch = async () => response([notification(1)], 1);
    await store.loadRecent();
    api.mark = async () => { throw new Error('network failure'); };
    api.fetch = async () => response([notification(1)], 1);

    assert.equal(await store.markRead(1), null);
    assert.equal(store.unreadCount, 1);
    assert.equal(store.previewItems[0].read_at, null);
});
