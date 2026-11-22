import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { routes } from '../../resources/js/router/routes.js';

const readSource = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('the existing User Settings route renders the reusable account settings page', () => {
    const userWorkspace = routes.find((route) => route.children?.some((child) => child.name === 'settings.index'));
    const settingsRoute = userWorkspace?.children.find((child) => child.name === 'settings.index');
    const routerSource = readSource('../../resources/js/router/routes.js');
    const settingsPage = readSource('../../resources/js/pages/user/settings/Index.vue');
    const accountPage = readSource('../../resources/js/pages/account/AccountSettings.vue');

    assert.equal(userWorkspace?.meta?.requiresAuth, true);
    assert.equal(userWorkspace?.meta?.requiresRole, 'user');
    assert.equal(settingsRoute?.path, 'settings');
    assert.equal(typeof settingsRoute?.component, 'function');
    assert.match(routerSource, /path: 'settings', name: 'settings\.index', component: userPage\('settings', 'Index'\)/);
    assert.match(settingsPage, /import AccountSettings from '\.\.\/\.\.\/account\/AccountSettings\.vue'/);
    assert.match(settingsPage, /<AccountSettings\s*\/>/);
    assert.match(accountPage, /<ProfilePersonalInformation/);
    assert.match(accountPage, /<ProfileSecurity/);
    assert.match(accountPage, /<ProfileDangerZone\s*\/>/);
    assert.match(accountPage, /v-if="loading && !profile"/);
    assert.match(accountPage, /v-if="loadError" class="profile-message profile-message--error" role="alert"/);
    assert.match(accountPage, /Your current account details are still shown\./);
    assert.match(accountPage, /:disabled="loading" @click="loadAccount"/);
    assert.match(accountPage, /useCurrentProfile retains the last loaded profile and exposes loadError/);
    assert.match(readSource('../../resources/css/pages/account-settings.css'), /\.account-settings-retry:focus-visible/);
    assert.match(accountPage, /:to="\{ hash: `#\$\{section\.id\}` \}"/);
    assert.match(accountPage, /const activeSection = computed\(\(\) => sections\.find\(\(section\) => `#\$\{section\.id\}` === route\.hash\)/);
    assert.doesNotMatch(accountPage, /IntersectionObserver/);
    assert.match(accountPage, /await nextTick\(\);[\s\S]*document\.getElementById\(activeSection\.value\)/);
    assert.match(accountPage, /<style src="\.\.\/\.\.\/\.\.\/css\/pages\/account-settings\.css"><\/style>/);
    assert.match(readSource('../../resources/css/pages/account-settings.css'), /@import "\.\/profile\.css";/);
    assert.match(readSource('../../resources/js/router/index.js'), /scrollBehavior: \(to\) => to\.hash \? \{ el: to\.hash \}/);
});

test('legacy User routes with no implemented feature redirect to existing pages', () => {
    const userWorkspace = routes.find((route) => route.children?.some((child) => child.name === 'settings.index'));
    const children = userWorkspace.children;
    const route = (name) => children.find((child) => child.name === name);

    assert.deepEqual(route('tags.index').redirect, { name: 'categories.index' });
    assert.deepEqual(route('tags.create').redirect, { name: 'categories.index' });
    assert.deepEqual(route('tags.edit').redirect, { name: 'categories.index' });
    assert.deepEqual(route('settings.notifications').redirect, { name: 'notifications.index' });
    assert.deepEqual(route('settings.security').redirect, { name: 'account.settings' });
    assert.deepEqual(route('statistics').redirect, { name: 'productivity.reports' });
    assert.deepEqual(route('productivity').redirect, { name: 'productivity.reports' });
    assert.equal(typeof route('subtasks.create').redirect, 'function');
    assert.equal(typeof route('subtasks.edit').redirect, 'function');
});
