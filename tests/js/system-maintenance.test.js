import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { routes } from '../../resources/js/router/routes.js';

const source = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('System Maintenance is an Admin-only page and is not added to the main sidebar navigation', () => {
    const adminRoute = routes.find((route) => route.path === '/admin');
    const maintenance = adminRoute.children.find((route) => route.name === 'admin.system-maintenance');
    assert.equal(maintenance.path, 'system-maintenance');
    assert.equal(maintenance.meta.requiresRole, 'admin');

    const sidebarMenu = source('../../resources/js/shared/components/WorkspaceSidebarProfileMenu.vue');
    assert.match(sidebarMenu, /v-if="auth\.hasRole\('admin'\)"[\s\S]*?System Maintenance/);
    assert.doesNotMatch(source('../../resources/js/shared/components/AdminSidebar.vue'), /System Maintenance/);
    assert.doesNotMatch(source('../../resources/js/shared/components/UserSidebar.vue'), /System Maintenance/);
});

test('maintenance management confirms actions, prevents duplicate submissions, and reads the server status', () => {
    const page = source('../../resources/js/pages/admin/system-maintenance/SystemMaintenanceIndex.vue');
    const api = source('../../resources/js/features/system-maintenance/admin/services/systemMaintenanceApi.js');
    assert.match(page, /AppConfirmDialog/);
    assert.match(page, /if \(saving\.value \|\| !pendingAction\.value\) return/);
    assert.match(page, /getSystemMaintenanceStatus/);
    assert.match(api, /\/admin\/system-maintenance/);
});
