import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { routes } from '../../resources/js/router/routes.js';

const readSource = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('profile has one authenticated shared route and keeps the legacy edit path pointed at it', () => {
    const profileRoutes = routes.filter((route) => route.path === '/profile');

    assert.equal(profileRoutes.length, 1);
    assert.equal(profileRoutes[0].meta.requiresAuth, true);
    assert.equal(profileRoutes[0].meta.requiresRole, undefined);
    assert.equal(profileRoutes[0].children.filter((route) => route.name === 'profile.show').length, 1);
    assert.equal(profileRoutes[0].children.find((route) => route.name === 'profile.edit').redirect.name, 'profile.show');
});

test('account settings is a dedicated authenticated shared route with a canonical legacy redirect', () => {
    const accountRoutes = routes.filter((route) => route.path === '/account/settings');
    const legacyAccountSettings = routes.find((route) => route.children?.some((child) => child.name === 'settings.account'));

    assert.equal(accountRoutes.length, 1);
    assert.equal(accountRoutes[0].meta.requiresAuth, true);
    assert.equal(accountRoutes[0].meta.requiresRole, undefined);
    assert.equal(accountRoutes[0].children[0].name, 'account.settings');
    assert.equal(accountRoutes[0].children[0].meta?.requiresRole, undefined);
    assert.equal(legacyAccountSettings.children.find((child) => child.name === 'settings.account').redirect.name, 'account.settings');
});

test('account settings is available in the shared profile menus and header, not main sidebar navigation', () => {
    const adminSidebar = readSource('../../resources/js/shared/components/AdminSidebar.vue');
    const userSidebar = readSource('../../resources/js/shared/components/UserSidebar.vue');
    const workspaceSidebar = readSource('../../resources/js/shared/components/WorkspaceSidebar.vue');
    const profileMenu = readSource('../../resources/js/shared/components/WorkspaceSidebarProfileMenu.vue');
    const headerProfileDropdown = readSource('../../resources/js/shared/components/ProfileDropdown.vue');

    assert.doesNotMatch(adminSidebar, /Account Settings|account\.settings/);
    assert.doesNotMatch(userSidebar, /Account Settings|account\.settings/);
    assert.match(adminSidebar, /label: 'Settings'.*name: 'admin\.settings\.index'/);
    assert.match(userSidebar, /label: 'Settings'.*name: 'settings\.index'/);
    assert.equal((workspaceSidebar.match(/<WorkspaceSidebarProfileMenu/g) || []).length, 2);
    assert.match(profileMenu, /to="\{ name: 'account\.settings' \}"[\s\S]*?<span>Account Settings<\/span>/);
    assert.match(profileMenu, /<span>My Profile<\/span>/);
    assert.match(profileMenu, /<span>Appearance<\/span>/);
    assert.match(profileMenu, /<span>Sign out<\/span>/);
    assert.match(headerProfileDropdown, /to="\{ name: 'account\.settings' \}"[\s\S]*?<span>Account Settings<\/span>/);
    assert.equal(routes.some((route) => route.path === '/account/settings'), true);
});

test('My Profile is presentation-only while Account Settings owns personal and security management', () => {
    const profilePage = readSource('../../resources/js/pages/profile/Show.vue');
    const accountSettingsPage = readSource('../../resources/js/pages/account/AccountSettings.vue');
    const overview = readSource('../../resources/js/features/profile/components/ProfileOverview.vue');

    assert.match(profilePage, /<ProfileOverview :user="profile" :editable="false"\s*\/>/);
    assert.match(profilePage, /<ProfileAccountInformation :user="profile"\s*\/>/);
    assert.doesNotMatch(profilePage, /ProfilePersonalInformation|ProfileSecurity|@upload=|@remove=|@save=/);
    assert.match(overview, /v-if="editable" class="profile-overview__photo"/);
    assert.match(overview, /v-if="!editable" class="profile-button profile-button--secondary profile-overview__manage-link"/);
    assert.match(accountSettingsPage, /<ProfileOverview :user="profile" :busy="avatarBusy"/);
    assert.match(accountSettingsPage, /<ProfilePersonalInformation :user="profile"/);
    assert.match(accountSettingsPage, /<ProfileSecurity :errors="fieldErrors"/);
    assert.match(accountSettingsPage, /<ProfileDangerZone \/>/);
    assert.match(accountSettingsPage, /loadAppearance\(user\.id\)/);
});

test('My Profile shows current-account work statistics with independent loading, empty, and error states', () => {
    const profilePage = readSource('../../resources/js/pages/profile/Show.vue');
    const api = readSource('../../resources/js/features/profile/services/profileApi.js');
    const overview = readSource('../../resources/js/features/profile/components/ProfileAccountOverview.vue');
    const routeApi = readFileSync(new URL('../../routes/api/v1.php', import.meta.url), 'utf8');

    assert.match(routeApi, /get\('\/user\/overview', \[CurrentUserController::class, 'overview'\]\)/);
    assert.match(api, /getCurrentProfileOverview = \(\) => api\.get\('\/user\/overview'\)/);
    assert.match(profilePage, /<ProfileAccountOverview :overview="overview" :loading="overviewLoading" :error="overviewError"/);
    assert.match(overview, /No projects or tasks are linked to your account yet\./);
    assert.match(overview, /<article class="profile-work-metric">[\s\S]*Total projects[\s\S]*overview\.projects\.total/);
    assert.match(overview, /Completed[\s\S]*overview\.projects\.completed/);
    assert.match(overview, /Tasks you own or are assigned/);
    assert.match(overview, /overview\.tasks\.total[\s\S]*overview\.tasks\.completed[\s\S]*overview\.tasks\.pending/);
    assert.match(overview, /v-if="loading"/);
    assert.match(overview, /v-else-if="error"/);
    assert.match(overview, /profile-work-overview__empty/);
    assert.match(readSource('../../resources/css/pages/profile.css'), /--color-success/);
    assert.match(readSource('../../resources/css/pages/profile.css'), /--color-warning/);
});
