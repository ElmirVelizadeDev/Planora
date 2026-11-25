import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { routes } from '../../resources/js/router/routes.js';
import { dashboardRouteFor } from '../../resources/js/features/auth/managementAccess.js';

const source = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const nestedRoute = (name) => routes.flatMap((route) => route.children || [route]).find((route) => route.name === name);

test('public Home CTAs go to Registration while the separate login action stays available', () => {
    for (const path of ['../../resources/js/shared/components/public/HomeHero.vue', '../../resources/js/shared/components/public/HomeCallToAction.vue']) {
        const component = source(path);
        assert.match(component, /:to="\{ name: 'register' \}"[^>]*>Get started/);
    }
    assert.match(source('../../resources/js/shared/components/public/HomeHero.vue'), /:to="\{ name: 'login' \}"[^>]*>Log in/);
    assert.equal(nestedRoute('register').meta.guestOnly, true);
});

test('signed-in Home uses the existing permission-aware landing route for each role', () => {
    const admin = { hasRole: (role) => role === 'admin', hasManagementPermission: () => false };
    const user = { hasRole: (role) => role === 'user', hasManagementPermission: () => false };
    const moderator = { hasRole: (role) => role === 'moderator', hasManagementPermission: (permission) => permission === 'projects.view' };
    const unprivilegedModerator = { hasRole: (role) => role === 'moderator', hasManagementPermission: () => false };

    assert.equal(dashboardRouteFor(admin), 'admin.dashboard');
    assert.equal(dashboardRouteFor(user), 'dashboard');
    assert.equal(dashboardRouteFor(moderator), 'admin.projects.index');
    assert.equal(dashboardRouteFor(unprivilegedModerator), 'error.403');
    assert.match(source('../../resources/js/shared/components/public/AuthenticatedHome.vue'), /dashboardRouteFor\(auth\)/);
});

test('Home feature copy describes categories without advertising Tags', () => {
    const features = source('../../resources/js/shared/components/public/FeaturesSection.vue');
    assert.match(features, /projects and categories/);
    assert.doesNotMatch(features, /\btags\b/i);
});

test('public legal pages are guest-accessible and linked in the public footer', () => {
    for (const name of ['terms-and-conditions', 'privacy-policy']) {
        const route = nestedRoute(name);
        assert.ok(route);
        assert.equal(route.meta.requiresAuth, undefined);
        assert.equal(route.meta.publicIndex, true);
        assert.match(source('../../resources/js/shared/components/public/PublicFooter.vue'), new RegExp(`name: '${name}'`));
        assert.ok(source('../../routes/web.php').includes(`Route::view('/${name}'`));
    }
});

test('registration checkbox has a native label and separate legal links', () => {
    const register = source('../../resources/js/pages/auth/Register.vue');
    assert.match(register, /id="register-terms-accepted"[^>]*aria-labelledby="register-terms-label"/);
    assert.match(register, /<label id="register-terms-label" for="register-terms-accepted">/);
    assert.match(register, /Read:[\s\S]*?name: 'terms-and-conditions'[\s\S]*?name: 'privacy-policy'/);
    assert.match(source('../../app/Http/Requests/Api/V1/Auth/RegisterRequest.php'), /'terms_accepted' => \['required', 'accepted'\]/);
});

test('public keyboard focus uses a solid theme accent and footer links remain responsive', () => {
    const styles = source('../../resources/css/pages/public/home.css');
    assert.match(styles, /:focus-visible\s*\{ outline: 3px solid var\(--color-accent-text\); outline-offset: 3px; \}/);
    assert.match(styles, /\.public-footer nav \{ display: flex; flex-wrap: wrap;/);
});

test('legal copy describes implemented workspace persistence and avoids invented operator details', () => {
    const privacy = source('../../resources/js/pages/public/PrivacyPolicy.vue');
    const terms = source('../../resources/js/pages/public/TermsAndConditions.vue');
    assert.match(privacy, /The application persists tasks, projects, and categories/);
    assert.match(privacy, /encrypted TOTP secrets/);
    assert.match(privacy, /request IP address, User-Agent/);
    assert.match(privacy, /does not define an activity-log retention period/);
    assert.doesNotMatch(privacy, /database migrations do not define task or project content tables/);
    assert.match(terms, /does not store a durable record or timestamp of this acceptance/);
    assert.match(terms, /Governing Law and Jurisdiction/);
    assert.match(terms, /PRE-PRODUCTION ITEM/);
    assert.doesNotMatch(`${privacy}\n${terms}`, /[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/);
});
