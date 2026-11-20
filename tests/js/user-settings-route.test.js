import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { routes } from '../../resources/js/router/routes.js';

const readSource = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('the existing User Settings route renders the reusable account settings page', () => {
    const userWorkspace = routes.find((route) => route.children?.some((child) => child.name === 'settings.index'));
    const settingsRoute = userWorkspace?.children.find((child) => child.name === 'settings.index');
    const routerSource = readSource('../../resources/js/router/routes.js');
    const settingsPage = readSource('../../resources/js/pages/app/settings/Index.vue');
    const accountPage = readSource('../../resources/js/pages/account/AccountSettings.vue');

    assert.equal(userWorkspace?.meta?.requiresAuth, true);
    assert.equal(userWorkspace?.meta?.requiresRole, 'user');
    assert.equal(settingsRoute?.path, 'settings');
    assert.equal(typeof settingsRoute?.component, 'function');
    assert.match(routerSource, /path: 'settings', name: 'settings\.index', component: appPage\('settings', 'Index'\)/);
    assert.match(settingsPage, /import AccountSettings from '\.\.\/\.\.\/account\/AccountSettings\.vue'/);
    assert.match(settingsPage, /<AccountSettings\s*\/>/);
    assert.match(accountPage, /<ProfilePersonalInformation/);
    assert.match(accountPage, /<ProfileSecurity/);
    assert.match(accountPage, /<ProfileDangerZone\s*\/>/);
    assert.match(accountPage, /:to="\{ hash: `#\$\{section\.id\}` \}"/);
    assert.match(accountPage, /const activeSection = computed\(\(\) => sections\.find\(\(section\) => `#\$\{section\.id\}` === route\.hash\)/);
    assert.doesNotMatch(accountPage, /IntersectionObserver/);
    assert.match(accountPage, /await nextTick\(\);[\s\S]*document\.getElementById\(activeSection\.value\)/);
    assert.match(accountPage, /<style src="\.\.\/\.\.\/\.\.\/css\/pages\/profile\.css"><\/style>/);
    assert.match(readSource('../../resources/js/router/index.js'), /scrollBehavior: \(to\) => to\.hash \? \{ el: to\.hash \}/);
});
