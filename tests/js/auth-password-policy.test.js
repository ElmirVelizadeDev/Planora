import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const register = readFileSync(new URL('../../resources/js/pages/auth/Register.vue', import.meta.url), 'utf8');

test('registration client validation enforces the shared 12 to 128 character password length', () => {
    assert.match(register, /minlength="12" maxlength="128"/);
    assert.match(register, /password\.value\.length < 12 \|\| password\.value\.length > 128/);
    assert.match(register, /Password must be between 12 and 128 characters/);
});
