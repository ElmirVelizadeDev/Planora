import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('Admin MFA setup keeps setup secrets in component memory and exposes status-only API separately', () => {
    const securityPage = source('../../resources/js/pages/admin/settings/AdminSettingsSecurity.vue');
    const api = source('../../resources/js/features/auth/admin/services/adminMfaApi.js');
    assert.match(securityPage, /setup\.secret/);
    assert.match(securityPage, /<img class="admin-mfa-qr" :src="setup\.qrCodeDataUri"/);
    assert.match(securityPage, /onBeforeUnmount\(\(\) => \{ Object\.assign\(setup/);
    assert.match(securityPage, /recovery_codes/);
    assert.match(securityPage, /cancelAdminMfaEnrollment/);
    assert.doesNotMatch(securityPage, /v-html/);
    assert.doesNotMatch(securityPage, /localStorage|sessionStorage|console\.(log|debug)/);
    assert.match(api, /fetchAdminMfaStatus/);
    assert.match(api, /verifyAdminMfaEnrollment/);
});

test('both Admin login entry points require the server MFA challenge before setting the auth user', () => {
    const store = source('../../resources/js/features/auth/stores/authStore.js');
    const login = source('../../resources/js/pages/auth/Login.vue');
    const adminLogin = source('../../resources/js/features/auth/admin/components/AdminLogin.vue');
    assert.match(store, /if \(payload\.mfa_required\) return \{ mfaRequired: true/);
    assert.match(login, /auth\.verifyAdminMfaLogin/);
    assert.match(adminLogin, /auth\.verifyAdminMfaLogin/);
});

test('critical operation step-up is backend prompted and submitted without browser storage', () => {
    const api = source('../../resources/js/shared/services/api.js');
    const dialog = source('../../resources/js/shared/components/AdminStepUpDialog.vue');
    assert.match(api, /step_up_required/);
    assert.match(api, /admin\/step-up\/verify/);
    assert.match(dialog, /planora:step-up-required/);
    assert.match(dialog, /current\.verify\(credentials\)/);
    assert.doesNotMatch(dialog, /localStorage|sessionStorage/);
});
