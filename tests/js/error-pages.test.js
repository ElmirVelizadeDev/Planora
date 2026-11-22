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
        assert.match(source(`../../resources/js/pages/errors/Error${status}.vue`), new RegExp(`<AppErrorPage[\\s\\S]*:code="${status}"`));
    }

    const notFound = routes.find((route) => route.name === 'not-found');
    assert.ok(notFound);
    assert.equal(notFound.path, '/:pathMatch(.*)*');
    assert.match(source('../../resources/js/pages/errors/Error404.vue'), /:code="404"/);
});

test('each status page provides distinct copy, iconography, tone, and relevant recovery actions', () => {
    const expected = {
        401: [/Sign in to continue/, /icon="key"/, /name: 'login'/],
        403: [/You do not have access/, /icon="shield"/, /dashboardRouteFor\(auth\)/],
        404: [/This page seems to have moved/, /icon="search"/, /Go back/],
        419: [/fresh session/, /icon="clock"/, /Reload page/],
        429: [/Give Planora a moment/, /icon="gauge"/, /server supplied a retry window/],
        500: [/could not complete that request/, /icon="wrench"/, /Reload page/],
        503: [/Planora will be back soon/, /icon="maintenance"/, /Check availability/],
    };
    for (const [status, patterns] of Object.entries(expected)) {
        const page = source(`../../resources/js/pages/errors/Error${status}.vue`);
        for (const pattern of patterns) assert.match(page, pattern, `Error${status}.vue should include ${pattern}`);
    }
});

test('shared error page supports configurable content/actions, safe navigation, bounded availability polling, and timer cleanup', () => {
    const page = source('../../resources/js/pages/errors/AppErrorPage.vue');
    assert.match(page, /async function signOut\(\)/);
    assert.match(page, /primaryLabel: \{ type: String/);
    assert.match(page, /primaryAction: \{ type: Function/);
    assert.match(page, /MAX_AVAILABILITY_CHECKS = 8/);
    assert.match(page, /clearInterval\(availabilityPoll\)/);
    assert.match(page, /clearInterval\(countdownTimer\)/);
    assert.match(page, /rateLimitDeadline/);
    assert.match(source('../../resources/js/shared/utils/errorDestinations.js'), /function goBackOrDashboard/);
    assert.match(page, /maintenance\/status/);
    assert.match(source('../../resources/css/pages/errors.css'), /max-width: 620px/);
    assert.match(source('../../resources/css/pages/errors.css'), /max-width: 520px/);
    assert.match(page, /blockedMaintenanceScopeFor/);
    assert.match(page, /View service status/);
    assert.match(source('../../resources/css/pages/errors.css'), /:focus-visible/);
});

test('401 intended destinations are checked as internal, known non-error routes', async () => {
    const { safeInternalRedirect } = await import('../../resources/js/shared/utils/safeRedirect.js');
    const router = { resolve: (path) => ({
        matched: path.startsWith('/missing') ? [{}] : [{}],
        name: path === '/login' ? 'login' : path === '/missing' ? 'not-found' : 'tasks.show',
        fullPath: path,
    }) };
    assert.equal(safeInternalRedirect('/tasks/42?tab=details', router), '/tasks/42?tab=details');
    assert.equal(safeInternalRedirect('https://example.com', router), null);
    assert.equal(safeInternalRedirect('//example.com', router), null);
    assert.equal(safeInternalRedirect('/\\\\example.com', router), null);
    assert.equal(safeInternalRedirect('/missing', router), null);
    assert.equal(safeInternalRedirect('/login', router), null);
    const login = source('../../resources/js/pages/auth/Login.vue');
    assert.match(login, /safeInternalRedirect\(route\.query\.redirect, router\)/);
    assert.match(login, /requestedDestination \|\| result\.redirectTo/);
});

test('dashboard recovery destinations respect the signed-in role and moderator permissions', async () => {
    const { dashboardRouteFor } = await import('../../resources/js/shared/utils/errorDestinations.js');
    const auth = (role, permissions = []) => ({
        hasRole: (candidate) => role === candidate,
        hasManagementPermission: (permission) => permissions.includes(permission),
    });
    assert.deepEqual(dashboardRouteFor(auth('user')), { name: 'dashboard' });
    assert.deepEqual(dashboardRouteFor(auth('admin')), { name: 'admin.dashboard' });
    assert.deepEqual(dashboardRouteFor(auth('moderator', ['projects.view'])), { name: 'admin.projects.index' });
    assert.deepEqual(dashboardRouteFor(auth('moderator')), { name: 'home' });
    assert.deepEqual(dashboardRouteFor(auth(null)), { name: 'home' });
});

test('maintenance recovery only offers destinations permitted by current scope state and active role', async () => {
    const { maintenanceRecoveryRouteFor } = await import('../../resources/js/shared/utils/errorDestinations.js');
    const auth = (role, permissions = [], status = 'active') => ({
        user: role ? { role, status } : null,
        hasRole: (candidate) => role === candidate,
        hasManagementPermission: (permission) => permissions.includes(permission),
    });
    assert.equal(maintenanceRecoveryRouteFor(auth(null), { public_website: true, dashboard_workspace: false }), null);
    assert.deepEqual(maintenanceRecoveryRouteFor(auth(null), { public_website: false, dashboard_workspace: true }), { name: 'home' });
    assert.deepEqual(maintenanceRecoveryRouteFor(auth('user'), { public_website: true, dashboard_workspace: false }), { name: 'dashboard' });
    assert.deepEqual(maintenanceRecoveryRouteFor(auth('user'), { public_website: false, dashboard_workspace: true }), { name: 'home' });
    assert.deepEqual(maintenanceRecoveryRouteFor(auth('admin'), { public_website: true, dashboard_workspace: true }), { name: 'admin.dashboard' });
    assert.deepEqual(maintenanceRecoveryRouteFor(auth('moderator', ['projects.view']), { public_website: true, dashboard_workspace: false }), { name: 'admin.projects.index' });
    assert.equal(maintenanceRecoveryRouteFor(auth('admin', [], 'suspended'), { public_website: true, dashboard_workspace: true }), null);
});

test('429 countdown state is recorded only when a usable Retry-After value exists', async () => {
    const { rateLimitDeadline, recordRateLimit } = await import('../../resources/js/shared/utils/rateLimitState.js');
    const originalNow = Date.now;
    try {
        Date.now = () => 1_000;
        recordRateLimit(null);
        assert.equal(rateLimitDeadline.value, null);
        recordRateLimit(-2);
        assert.equal(rateLimitDeadline.value, null);
        recordRateLimit(30);
        assert.equal(rateLimitDeadline.value, 31_000);
    } finally {
        rateLimitDeadline.value = null;
        Date.now = originalNow;
    }
});

test('registration resend throttling only starts a countdown when Laravel sends Retry-After', () => {
    const verify = source('../../resources/js/pages/auth/VerifyEmail.vue');
    assert.match(verify, /if \(error\.response\?\.status === 429\) \{\s*const retryAfter = classifyHttpError\(error\)\.retryAfterSeconds/);
    assert.match(verify, /if \(retryAfter > 0\)/);
});

test('standalone maintenance errors probe the current page with bounded safe GET retries', () => {
    const blade = source('../../resources/views/errors/page.blade.php');
    const retryScript = source('../../public/js/error-page.js');
    const scopeMiddleware = source('../../app/Http/Middleware/EnforceMaintenanceScope.php');

    assert.match(blade, /data-retry-after=/);
    assert.match(blade, /\/js\/error-page\.js/);
    assert.match(retryScript, /fetch\(window\.location\.href/);
    assert.match(retryScript, /method: 'GET'/);
    assert.match(retryScript, /maxAutomaticChecks = 8/);
    assert.doesNotMatch(retryScript, /window\.location\.assign\('\/'\)/);
    assert.match(scopeMiddleware, /'scope' => \$scope/);
    assert.match(scopeMiddleware, /'maintenanceScopes' => \$maintenance\['scopes'\]/);
    assert.match(blade, /if\(\$safeDestination\)/);
});
