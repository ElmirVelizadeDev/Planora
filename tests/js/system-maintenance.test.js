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

    const sidebarMenu = source('../../resources/js/shared/components/layout/WorkspaceSidebarProfileMenu.vue');
    assert.match(sidebarMenu, /v-if="auth\.hasRole\('admin'\)"[\s\S]*?System Maintenance/);
    const headerMenu = source('../../resources/js/shared/components/ProfileDropdown.vue');
    assert.match(headerMenu, /v-if="variant === 'admin'" class="profile-menu-item" :to="\{ name: 'admin\.system-maintenance' \}"[\s\S]*?<span>System Maintenance<\/span>/);
    assert.match(headerMenu, /class="profile-menu-item" :to="\{ name: 'admin\.system-maintenance' \}"/);
    assert.doesNotMatch(source('../../resources/js/features/admin/components/AdminSidebar.vue'), /System Maintenance/);
    assert.doesNotMatch(source('../../resources/js/features/user/components/UserSidebar.vue'), /System Maintenance/);
});

test('maintenance management exposes three independent confirmed scopes and reads live server status', () => {
    const page = source('../../resources/js/pages/admin/system-maintenance/SystemMaintenanceIndex.vue');
    const api = source('../../resources/js/features/system-maintenance/admin/services/systemMaintenanceApi.js');
    assert.match(page, /AppConfirmDialog/);
    assert.match(page, /if \(saving\.value \|\| !pendingAction\.value\) return/);
    assert.match(page, /getSystemMaintenanceStatus/);
    assert.match(page, /Public Website/);
    assert.match(page, /Authentication/);
    assert.match(page, /Dashboard & Workspace/);
    assert.match(page, /setMaintenanceScope/);
    assert.match(page, /Partially Under Maintenance/);
    assert.match(page, /Save message settings/);
    assert.match(api, /\/admin\/system-maintenance/);
    assert.match(api, /scopes\/\$\{scope\}/);
    assert.match(api, /updateMaintenancePreferences/);
    assert.match(api, /\/admin\/system-maintenance\/preferences/);
});

test('scope maintenance is enforced on selected route groups without broad Admin API bypasses', () => {
    const middleware = source('../../app/Http/Middleware/PreventRequestsDuringMaintenance.php');
    const adminGuard = source('../../app/Http/Middleware/EnsureAdminMaintenanceBootstrap.php');
    const scopeGuard = source('../../app/Http/Middleware/EnforceMaintenanceScope.php');
    const webRoutes = source('../../routes/web.php');
    const apiRoutes = source('../../routes/api/v1.php');

    assert.match(scopeGuard, /dashboard_workspace.*role === 'admin'/s);
    assert.match(scopeGuard, /response\(\)->view\('errors\.page'/);
    assert.match(scopeGuard, /'scope' => \$scope/);
    assert.match(webRoutes, /maintenance\.scope:public_website/);
    assert.match(webRoutes, /maintenance\.scope:authentication/);
    assert.match(webRoutes, /maintenance\.scope:dashboard_workspace/);
    assert.match(apiRoutes, /maintenance\.scope:public_website[\s\S]*?maintenance\.scope:authentication/);
    assert.match(apiRoutes, /maintenance\.scope:dashboard_workspace/);
    assert.match(middleware, /'admin\/dashboard'/);
    assert.match(middleware, /'api\/v1\/admin\/dashboard'/);
    assert.doesNotMatch(middleware, /api\/v1\/admin\/\*/);
    assert.doesNotMatch(middleware, /api\/v1\/admin\/system-maintenance\*/);
    assert.match(adminGuard, /\$guard = \$request->is\('api\/\*'\) \? 'sanctum' : 'web'/);
    assert.match(adminGuard, /user\(\$guard\)\?->role !== 'admin'/);
    assert.match(webRoutes, /Route::view\('\/admin\/dashboard'[\s\S]*?maintenance\.scope:dashboard_workspace/);
    assert.match(apiRoutes, /Route::get\('\/admin\/dashboard'[\s\S]*?maintenance\.scope:dashboard_workspace/);
});

test('API maintenance errors route non-admin users to the branded 503 while preserving Admin dashboard routes', () => {
    const app = source('../../resources/js/App.vue');
    const guards = source('../../resources/js/router/guards.js');

    assert.match(app, /event\.detail\?\.status !== 503/);
    assert.match(app, /if \(adminAuthEntry\) return/);
    assert.match(app, /auth\.hasRole\('admin'\) && \['admin\.dashboard', 'admin\.system-maintenance'\]/);
    assert.match(app, /router\.replace\(scope[\s\S]*?name: 'error\.503'/);
    assert.match(guards, /error\.response\?\.status === 503\) \{/);
    assert.match(guards, /getMaintenanceStatus/);
    assert.match(guards, /to\.meta\.maintenanceScopes/);
});

test('the secret administrator entry is rendered from server state and absent from public Vue routes', () => {
    const routeSource = source('../../routes/web.php');
    const blade = source('../../resources/views/welcome.blade.php');
    const app = source('../../resources/js/App.vue');
    const adminLogin = source('../../resources/js/features/auth/components/AdminLogin.vue');
    const authApi = source('../../resources/js/features/auth/services/authApi.js');

    assert.match(routeSource, /config\('planora\.admin_entry_path'\)/);
    assert.match(routeSource, /Route::get\('\/'\.\$adminEntryPath/);
    assert.match(blade, /data-planora-view=/);
    assert.match(app, /dataset\.planoraView === 'admin-auth'/);
    assert.match(adminLogin, /auth\.loginAsAdministrator/);
    assert.match(authApi, /window\.location\.pathname/);
    assert.doesNotMatch(source('../../resources/js/router/routes.js'), /admin_entry_path|secret-entry|admin-login/);
});
