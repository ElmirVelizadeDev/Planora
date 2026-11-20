import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { routes } from '../../resources/js/router/routes.js';

const source = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('Quick Add is rendered only for Admins and removed from the User header', () => {
    const header = source('../../resources/js/shared/components/layout/AppHeader.vue');

    assert.doesNotMatch(header, /HeaderQuickActions/);
    assert.match(header, /<AdminQuickAdd v-if="variant === 'admin' && auth\.hasRole\('admin'\)"\s*\/>/);
});

test('Admin Quick Add exposes accessible task, project, and user actions with robust menu dismissal', () => {
    const quickAdd = source('../../resources/js/features/admin/components/AdminQuickAdd.vue');

    assert.match(quickAdd, /aria-haspopup="menu"/);
    assert.match(quickAdd, /role="menu"/);
    assert.match(quickAdd, /role="menuitem"/);
    assert.match(quickAdd, /New Task/);
    assert.match(quickAdd, /New Project/);
    assert.match(quickAdd, /New User/);
    assert.match(quickAdd, /admin\.tasks\.create/);
    assert.match(quickAdd, /admin\.projects\.index/);
    assert.match(quickAdd, /admin\.users\.create/);
    assert.match(quickAdd, /document\.addEventListener\('pointerdown', onOutsidePointer\)/);
    assert.match(quickAdd, /document\.addEventListener\('keydown', onDocumentKeydown\)/);
    assert.match(quickAdd, /watch\(\(\) => route\.fullPath, \(\) => closeMenu\(\)\)/);
    assert.match(quickAdd, /event\.key === 'ArrowDown'/);
    assert.match(quickAdd, /event\.key === 'ArrowUp'/);
    assert.match(quickAdd, /event\.key === 'Home'/);
    assert.match(quickAdd, /event\.key === 'End'/);
});

test('Quick Add actions resolve to existing Admin creation workflows', () => {
    const adminRoutes = routes.find((route) => route.path === '/admin').children;
    const tasks = source('../../resources/js/pages/admin/tasks/AdminTaskForm.vue');
    const projects = source('../../resources/js/pages/admin/projects/AdminProjectsIndex.vue');
    const projectForm = source('../../resources/js/features/projects/admin/components/AdminProjectForm.vue');
    const users = source('../../resources/js/pages/admin/users/UsersCreate.vue');

    assert.equal(adminRoutes.find((route) => route.name === 'admin.tasks.create').component instanceof Function, true);
    assert.equal(adminRoutes.find((route) => route.name === 'admin.users.create').component instanceof Function, true);
    assert.match(tasks, /createAdminTask\(payload\)/);
    assert.match(tasks, /if \(saving\.value\) return/);
    assert.match(tasks, /for \(const \[field, messages\] of Object\.entries\(validation\)\)/);
    assert.match(tasks, /finally \{ saving\.value = false; \}/);
    assert.match(projects, /value === 'project'.*openCreate\(\)/s);
    assert.match(projects, /\}, \{ immediate: true \}\)/);
    assert.match(projects, /function closeProjectDialog\(\) \{[\s\S]*?closeDialog\(\);\s*clearQuickAddQuery\(\);/);
    assert.match(projects, /function clearQuickAddQuery\(\) \{[\s\S]*?delete query\.quickAdd;[\s\S]*?router\.replace\(/);
    assert.match(projects, /<AdminProjectForm/);
    assert.match(projects, /saveProject\(payload, \{ refreshList: !openedFromQuickAdd \}\)/);
    assert.match(projects, /clearQuickAddQuery\(\);\s*if \(openedFromQuickAdd\) await loadProjects\(\)/);
    assert.match(projectForm, /emit\('submit'/);
    assert.match(users, /api\.post\('\/admin\/users', payload\)/);
    assert.match(users, /if \(submitting\.value \|\| !validateForm\(\)\) return/);
});

test('Quick Add defers focusout dismissal so pointer clicks can activate menu actions', () => {
    const quickAdd = source('../../resources/js/features/admin/components/AdminQuickAdd.vue');

    assert.match(quickAdd, /function onFocusout\(\)\s*\{[\s\S]*?focusoutTimer = window\.setTimeout\([\s\S]*?\}, 0\);\s*\}/);
    assert.match(quickAdd, /function closeMenu\(restoreFocus = false\)\s*\{\s*window\.clearTimeout\(focusoutTimer\)/);
    assert.match(quickAdd, /async function selectAction\(action\)\s*\{\s*closeMenu\(true\);[\s\S]*?await router\.push\(action\.to\)/);
});
