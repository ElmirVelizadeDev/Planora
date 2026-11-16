import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { routes } from '../../resources/js/router/routes.js';

const source = (path) => readFile(new URL(path, import.meta.url), 'utf8');

test('Admin and User notification histories use the shared notification page and protected routes', async () => {
    const userRoute = routes.find((route) => route.children?.some((child) => child.name === 'notifications.index'));
    const userNotifications = userRoute.children.find((child) => child.name === 'notifications.index');
    const adminRoute = routes.find((route) => route.path === '/admin');
    const adminNotifications = adminRoute.children.find((child) => child.name === 'admin.notifications.index');
    const adminPage = await source('../../resources/js/pages/admin/notifications/AdminNotificationsIndex.vue');
    const userPage = await source('../../resources/js/pages/app/notifications/NotificationsIndex.vue');

    assert.equal(userRoute.meta.requiresRole, 'user');
    assert.equal(userNotifications.path, 'notifications');
    assert.equal(adminNotifications.path, 'notifications');
    assert.equal(adminNotifications.meta.requiresRole, 'admin');
    assert.match(adminPage, /<NotificationsPage \/>/);
    assert.match(userPage, /<NotificationsPage \/>/);
});

test('shared notification dropdown supports badge, outside click, Escape, read actions, and internal navigation', async () => {
    const dropdown = await source('../../resources/js/shared/components/NotificationDropdown.vue');
    const store = await source('../../resources/js/features/notifications/stores/notificationStore.js');
    const api = await source('../../resources/js/features/notifications/services/notificationsApi.js');

    assert.match(dropdown, /store\.unreadCount > 99 \? '99\+' : store\.unreadCount/);
    assert.match(dropdown, /aria-controls="popoverId"/);
    assert.match(dropdown, /:aria-expanded="open"/);
    assert.match(dropdown, /@keydown\.esc\.stop\.prevent="close\(true\)"/);
    assert.match(dropdown, /document\.addEventListener\('pointerdown', onOutside\)/);
    assert.match(dropdown, /store\.markRead\(notification\.id\)/);
    assert.match(dropdown, /router\.push\(\{ name, params: \{ taskId \} \}\)/);
    assert.match(dropdown, /store\.markAllRead\(\)/);
    assert.match(store, /previewItems/);
    assert.match(store, /pageItems/);
    assert.match(store, /PREVIEW_TTL_MS/);
    assert.match(store, /enqueueReadOperation/);
    assert.match(store, /Math\.max\(0, count\)/);
    assert.match(api, /api\.get\('\/notifications'/);
    assert.match(api, /api\.put\(`\/notifications/);
    assert.match(api, /api\.post\('\/notifications\/read-all'/);
});

test('notification views provide filtering, pagination, loading, empty, and error states with token-based theme styling', async () => {
    const page = await source('../../resources/js/features/notifications/components/NotificationsPage.vue');
    const css = await source('../../resources/css/components/notifications.css');
    const resource = await source('../../app/Http/Resources/Api/V1/Notifications/NotificationResource.php');

    assert.match(page, />All</);
    assert.match(page, />Unread</);
    assert.match(page, /store\.lastPage > 1/);
    assert.match(page, /Loading notifications/);
    assert.match(page, /You’re all caught up/);
    assert.match(page, /role="alert"/);
    assert.match(css, /\.notification-popover \{ position: absolute; top: calc\(100% \+ 10px\); right: 0; z-index: 40; width: min\(360px, calc\(100vw - 32px\)\)/);
    assert.match(css, /var\(--color-surface\)/);
    assert.match(css, /var\(--color-selected-background\)/);
    assert.match(resource, /tasks\.show/);
    assert.match(resource, /admin\.tasks\.show/);
});

test('notification API and persistence are recipient-scoped and support idempotent task reminders', async () => {
    const controller = await source('../../app/Http/Controllers/Api/V1/Notifications/NotificationController.php');
    const service = await source('../../app/Services/Api/V1/Notifications/NotificationService.php');
    const routes = await source('../../routes/api/v1.php');
    const migration = await source('../../database/migrations/2026_11_15_000001_create_user_notifications_table.php');
    const orderIndex = await source('../../database/migrations/2026_11_15_000002_add_notification_recipient_order_index.php');
    const schedule = await source('../../app/Console/Kernel.php');
    const command = await source('../../app/Console/Commands/NotifyTasksDueSoon.php');

    assert.match(routes, /prefix\('notifications'\)[\s\S]*?auth:sanctum', 'active', 'role:user,admin/);
    assert.match(controller, /\$notifications->paginate\(\$recipient/);
    assert.match(service, /where\('recipient_id', \$recipient->id\)/);
    assert.match(service, /firstOrCreate/);
    assert.match(migration, /constrained\('users'\)->cascadeOnDelete\(\)/);
    assert.match(migration, /unique\(\)/);
    assert.match(orderIndex, /\['recipient_id', 'created_at', 'id'\]/);
    assert.ok(schedule.includes("planora:notify-tasks-due-soon')->dailyAt('08:00')"));
    assert.match(command, /where\('status', '!=', 'completed'\)/);
    assert.match(service, /Task::query\(\)->lockForUpdate\(\)->find\(\$taskId\)/);
    assert.match(service, /task-due-soon:\{\$task->id\}:\{\$recipient->id\}:\{\$dueDate\}/);
});
