import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('Admin MFA is mandatory, QR and recovery codes stay in page memory, and disable is absent from settings', () => {
    const securityPage = source('../../resources/js/pages/admin/settings/AdminSettingsSecurity.vue');
    const api = source('../../resources/js/features/auth/admin/services/adminMfaApi.js');
    const adminLogin = source('../../resources/js/features/auth/admin/components/AdminLogin.vue');
    assert.match(securityPage, /MFA is mandatory for all administrator sign-ins/);
    assert.doesNotMatch(securityPage, /Disable MFA|setup\.secret|setup\.otpauthUri/);
    assert.match(securityPage, /onBeforeUnmount\(\(\) => \{ recoveryCodes\.value = \[\]; \}\)/);
    assert.match(adminLogin, /mfaEnrollmentRequired/);
    assert.match(adminLogin, /beginAdminMfaLoginEnrollment/);
    assert.match(adminLogin, /verifyAdminMfaLoginEnrollment/);
    assert.doesNotMatch(adminLogin, /localStorage|sessionStorage/);
    assert.match(securityPage, /recovery_codes/);
    assert.doesNotMatch(securityPage, /v-html/);
    assert.doesNotMatch(securityPage, /localStorage|sessionStorage|console\.(log|debug)/);
    assert.match(api, /fetchAdminMfaStatus/);
    assert.match(api, /regenerateAdminMfaRecoveryCodes/);
});

test('both Admin login entry points require the server MFA challenge before setting the auth user', () => {
    const store = source('../../resources/js/features/auth/stores/authStore.js');
    const login = source('../../resources/js/pages/auth/Login.vue');
    const adminLogin = source('../../resources/js/features/auth/admin/components/AdminLogin.vue');
    assert.match(store, /if \(payload\.mfa_required\) return \{ mfaRequired: true/);
    assert.match(store, /payload\.mfa_enrollment_required/);
    assert.match(login, /auth\.verifyAdminMfaLogin/);
    assert.match(adminLogin, /auth\.verifyAdminMfaLogin/);
});

test('Admin login displays safe API messages and suppresses Laravel’s generic validation envelope', () => {
    const adminLogin = source('../../resources/js/features/auth/admin/components/AdminLogin.vue');
    assert.match(adminLogin, /const responseMessage = error\.response\?\.data\?\.message/);
    assert.match(adminLogin, /responseMessage === 'The given data was invalid\.'/);
    assert.match(adminLogin, /responseMessage \|\| 'We could not verify this administrator account/);
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
