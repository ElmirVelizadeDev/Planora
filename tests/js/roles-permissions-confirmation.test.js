import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('Roles & Permissions uses the Settings confirmation dialog and never invokes a native confirmation', () => {
    const roles = source('../../resources/js/pages/admin/roles/RolesPermissionsIndex.vue');
    const settingsDialog = source('../../resources/js/pages/admin/settings/components/SettingsResetConfirmationDialog.vue');

    assert.match(roles, /import SettingsResetConfirmationDialog from '\.\.\/settings\/components\/SettingsResetConfirmationDialog\.vue'/);
    assert.match(roles, /:cancel-label="confirmation\.mode === 'leave' \? 'Stay on page' : 'Cancel'"/);
    assert.match(roles, /@confirm="confirmConfirmation"/);
    assert.match(roles, /@cancel="cancelConfirmation"/);
    assert.doesNotMatch(roles, /window\.(?:confirm|alert)\s*\(/);
    assert.match(roles, /api\.put\(`\/admin\/roles-permissions\/moderators\/\$\{selected\.value\.id\}`/);
    assert.match(settingsDialog, /class="logout-dialog"/);
    assert.match(settingsDialog, /class="logout-dialog-actions"/);
});

test('Settings and Roles share one unsaved-route confirmation guard', () => {
    const roles = source('../../resources/js/pages/admin/roles/RolesPermissionsIndex.vue');
    const settingsForm = source('../../resources/js/features/settings/admin/composables/useAdminSettingsForm.js');
    const guard = source('../../resources/js/shared/composables/useUnsavedChangesConfirmation.js');

    assert.equal((roles.match(/useUnsavedChangesConfirmation\(/g) || []).length, 1);
    assert.equal((settingsForm.match(/useUnsavedChangesConfirmation\(/g) || []).length, 1);
    assert.match(guard, /if \(pendingNavigation\) return pendingNavigation\.promise/);
    assert.match(guard, /if \(!shouldLeave && confirmationBeforeNavigation\) Object\.assign\(confirmation, confirmationBeforeNavigation\)/);
    assert.match(guard, /onBeforeRouteLeave\(/);
    assert.match(guard, /confirmation\.title = 'Unsaved changes'/);
});

test('Roles declares the dirty computed before registering its route guard', () => {
    const roles = source('../../resources/js/pages/admin/roles/RolesPermissionsIndex.vue');
    assert.match(roles, /const hasUnsavedChanges = computed\(\(\) => changedPermissions\.value\.length > 0\)/);
    assert.ok(roles.indexOf('const changedPermissions = computed(') < roles.indexOf('const hasUnsavedChanges = computed('));
    assert.ok(roles.indexOf('const hasUnsavedChanges = computed(') < roles.indexOf('useUnsavedChangesConfirmation({ dirty: hasUnsavedChanges'));
});
