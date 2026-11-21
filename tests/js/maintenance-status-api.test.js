import assert from 'node:assert/strict';
import { mock, test } from 'node:test';

const calls = [];
let respond;
let rejectRequest;
const apiModule = new URL('../../resources/js/shared/services/api.js', import.meta.url).href;
mock.module(apiModule, {
    exports: {
        default: {
            get: (path, options) => {
                calls.push({ path, options });
                return new Promise((resolve, reject) => {
                    respond = resolve;
                    rejectRequest = reject;
                });
            },
        },
    },
});

const { getMaintenanceStatus } = await import('../../resources/js/features/system-maintenance/shared/services/maintenanceStatusApi.js');

test('concurrent maintenance guard checks share one bounded request and release it after failure', async () => {
    const first = getMaintenanceStatus();
    const second = getMaintenanceStatus();

    assert.equal(calls.length, 1);
    assert.equal(calls[0].path, '/maintenance/status');
    assert.equal(calls[0].options.timeout, 5_000);

    const status = { scopes: { dashboard_workspace: false } };
    respond({ data: { data: status } });
    assert.equal(await first, status);
    assert.equal(await second, status);

    const next = getMaintenanceStatus();
    assert.equal(calls.length, 2);
    respond({ data: { data: status } });
    assert.equal(await next, status);

    const failed = getMaintenanceStatus();
    rejectRequest(Object.assign(new Error('request timed out'), { code: 'ECONNABORTED' }));
    await assert.rejects(failed, /request timed out/);

    const retry = getMaintenanceStatus();
    assert.equal(calls.length, 4);
    respond({ data: { data: status } });
    assert.equal(await retry, status);
});
