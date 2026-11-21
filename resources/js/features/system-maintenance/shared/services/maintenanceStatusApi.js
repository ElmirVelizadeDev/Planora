import api from '../../../../shared/services/api.js';

// This lightweight check runs in the route guard, so it must not hold navigation indefinitely.
const STATUS_REQUEST_TIMEOUT_MS = 5_000;
let pendingStatusRequest = null;

export async function getMaintenanceStatus() {
    if (!pendingStatusRequest) {
        pendingStatusRequest = api.get('/maintenance/status', { timeout: STATUS_REQUEST_TIMEOUT_MS })
            .then((response) => response.data.data)
            .finally(() => { pendingStatusRequest = null; });
    }

    return pendingStatusRequest;
}
