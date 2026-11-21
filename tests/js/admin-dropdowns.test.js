import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('Admin Users action items capture their row data and no longer depend on native details state', () => {
    const users = source('../../resources/js/pages/admin/users/UsersIndex.vue');

    assert.match(users, /<AdminActionDropdown :active-key="activeActionUserId" :item-key="user\.id"/);
    assert.doesNotMatch(users, /<details class="admin-users-actions"/);
    for (const action of ['openRestoreDialog', 'openViewDialog', 'openEditDialog', 'openStatusDialog', 'openDeleteDialog']) assert.match(users, new RegExp(`onSelect: \\(\\) => ${action}\\(user\\)`));
    assert.match(users, /activeActionUserId\.value = null/);
});

test('Admin Users, Categories, Tasks, and Projects share one responsive Action dropdown', () => {
    const component = source('../../resources/js/features/admin/components/AdminActionDropdown.vue');
    const tables = [
        source('../../resources/js/pages/admin/users/UsersIndex.vue'),
        source('../../resources/js/features/categories/admin/components/CategoryManagementTable.vue'),
        source('../../resources/js/features/tasks/admin/components/AdminTaskTable.vue'),
        source('../../resources/js/features/projects/admin/components/AdminProjectTable.vue'),
    ];
    for (const table of tables) assert.match(table, /<AdminActionDropdown/);
    for (const feature of ['onOutsidePointer', 'keydown', 'Escape', 'visualViewport', 'getAnchoredPopoverStyle', 'separatorBefore', 'focus-visible']) assert.match(component, new RegExp(feature));
    assert.match(component, /onBeforeUnmount\(/);
    assert.match(component, /overflow-y: auto/);
    assert.match(component, /role="menuitem"/);
});

test('Admin Reports suggestions use a teleported, keyboard-navigable listbox', () => {
    const filter = source('../../resources/js/features/reports/admin/components/ReportSearchFilter.vue');
    assert.match(filter, /<Teleport to="body">/);
    assert.match(filter, /role="listbox"/);
    assert.match(filter, /aria-activedescendant/);
    assert.match(filter, /event\.key === 'ArrowDown'/);
    assert.match(filter, /event\.key === 'Escape'/);
    assert.match(filter, /removePositionListeners\(\)/);
});

test('Backups Create menu preserves menu actions and cleans up global listeners', () => {
    const backups = source('../../resources/js/pages/admin/backups/BackupsIndex.vue');
    assert.match(backups, /role="menu"/);
    for (const type of ['full', 'database', 'files']) assert.match(backups, new RegExp(`startBackup\\('${type}'\\)`));
    assert.match(backups, /document\.removeEventListener\('pointerdown', onCreateMenuOutsidePointer\)/);
    assert.match(backups, /document\.removeEventListener\('keydown', onCreateMenuDocumentKeydown\)/);
});
