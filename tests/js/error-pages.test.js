import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { routes } from '../../resources/js/router/routes.js';

const source = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('all requested error routes share the reusable error page and include a Vue 404 catch-all', () => {
    const expected = [401, 403, 419, 429, 500, 503];
    for (const status of expected) {
        const route = routes.find((entry) => entry.name === `error.${status}`);
        assert.ok(route, `missing route for ${status}`);
        assert.equal(route.path, `/${status}`);
        assert.match(source(`../../resources/js/pages/errors/Error${status}.vue`), /AppErrorPage :code/);
    }

    assert.equal(routes.some((route) => route.name === 'not-found' && route.path === '/:pathMatch(.*)*'), true);
    assert.match(source('../../resources/js/pages/errors/Error404.vue'), /AppErrorPage :code="404"/);
});

test('shared error page provides safe retry, back, sign-in, sign-out, and automatic 503 availability actions', () => {
    const page = source('../../resources/js/pages/errors/AppErrorPage.vue');
    assert.match(page, /401:.*primaryLabel: 'Go to sign in'/);
    assert.match(page, /function goBack\(\)/);
    assert.match(page, /async function signOut\(\)/);
    assert.match(page, /fetch\('\//);
    assert.match(page, /setInterval/);
});

test('standalone maintenance errors retry the active scope instead of assuming the whole site is down', () => {
    const blade = source('../../resources/views/errors/page.blade.php');
    const retryScript = source('../../public/js/error-page.js');
    const scopeMiddleware = source('../../app/Http/Middleware/EnforceMaintenanceScope.php');

    assert.match(blade, /data-maintenance-scope=/);
    assert.match(blade, /\/js\/error-page\.js/);
    assert.match(retryScript, /api\/v1\/maintenance\/status/);
    assert.match(retryScript, /status\.data\?\.scopes\?\.\[scope\]/);
    assert.match(scopeMiddleware, /'scope' => \$scope/);
});
