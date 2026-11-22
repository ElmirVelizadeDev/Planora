import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { classifyHttpError, formatRetryDelay, rateLimitMessage } from '../../resources/js/shared/utils/httpError.js';

const source = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');

test('classifies supported HTTP errors without treating network failures as server errors', () => {
    const cases = [
        [401, 'unauthenticated'], [403, 'forbidden'], [404, 'not-found'],
        [419, 'session-expired'], [422, 'validation'], [429, 'rate-limited'],
        [500, 'server-error'], [503, 'service-unavailable'], [400, 'http-error'],
    ];

    for (const [status, kind] of cases) {
        assert.deepEqual(classifyHttpError({ response: { status, headers: {} } }), {
            kind,
            status,
            retryAfterSeconds: null,
        });
    }

    assert.deepEqual(classifyHttpError({ message: 'Network Error' }), {
        kind: 'network',
        status: null,
        retryAfterSeconds: null,
    });
});

test('reads Retry-After only for rate-limit and service-unavailable responses', () => {
    assert.equal(classifyHttpError({ response: { status: 429, headers: { 'retry-after': '45' } } }).retryAfterSeconds, 45);
    assert.equal(classifyHttpError({ response: { status: 503, headers: { get: () => '20' } } }).retryAfterSeconds, 20);
    assert.equal(classifyHttpError({ response: { status: 500, headers: { 'retry-after': '60' } } }).retryAfterSeconds, null);
});

test('formats only server-provided retry guidance for rate-limited forms', () => {
    assert.equal(formatRetryDelay(1), '1 second');
    assert.equal(formatRetryDelay(91), '1 minute 31 seconds');
    assert.equal(rateLimitMessage({ response: { status: 429, headers: { 'retry-after': '30' } } }, 'Too many attempts.'), 'Too many attempts. Try again in 30 seconds.');
    assert.equal(rateLimitMessage({ response: { status: 429, headers: {} } }, 'Too many attempts.'), 'Too many attempts.');
});

test('keeps validation payload available to form-level handling and classifies it separately', () => {
    const error = { response: { status: 422, data: { message: 'Validation failed', errors: { email: ['Invalid'] } } } };
    assert.equal(classifyHttpError(error).kind, 'validation');
    assert.deepEqual(error.response.data.errors, { email: ['Invalid'] });
});

test('routes expired authenticated API sessions to sign-in and provides a safe runtime fallback', () => {
    const app = source('../../resources/js/App.vue');
    const entry = source('../../resources/js/app.js');
    assert.match(app, /event\.detail\?\.kind !== 'unauthenticated'/);
    assert.match(app, /auth\.clearUser\(\)/);
    assert.match(app, /name: 'login', query: \{ redirect:/);
    assert.match(app, /<Error500 v-if="hasRuntimeError"/);
    assert.match(entry, /app\.config\.errorHandler/);
    assert.match(entry, /addEventListener\('unhandledrejection'/);
});
