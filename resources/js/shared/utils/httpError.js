const STATUS_KINDS = new Map([
    [401, 'unauthenticated'],
    [403, 'forbidden'],
    [404, 'not-found'],
    [419, 'session-expired'],
    [422, 'validation'],
    [429, 'rate-limited'],
]);

function retryAfterSeconds(headers) {
    const value = typeof headers?.get === 'function'
        ? headers.get('Retry-After')
        : headers?.['retry-after'] ?? headers?.['Retry-After'];
    if (value == null) return null;

    const seconds = Number(value);
    if (Number.isFinite(seconds) && seconds >= 0) return seconds;

    const timestamp = Date.parse(value);
    return Number.isNaN(timestamp) ? null : Math.max(0, (timestamp - Date.now()) / 1000);
}

export function classifyHttpError(error) {
    const response = error?.response;
    if (!response) {
        return { kind: 'network', status: null, retryAfterSeconds: null };
    }

    const status = Number(response.status);
    const kind = STATUS_KINDS.get(status)
        || (status === 503 ? 'service-unavailable' : status >= 500 ? 'server-error' : 'http-error');
    return {
        kind,
        status,
        retryAfterSeconds: status === 429 || status === 503 ? retryAfterSeconds(response.headers) : null,
    };
}

export function formatRetryDelay(seconds) {
    const totalSeconds = Math.ceil(Number(seconds));
    if (!Number.isFinite(totalSeconds) || totalSeconds <= 0) return null;
    const minutes = Math.floor(totalSeconds / 60);
    const remainder = totalSeconds % 60;
    if (!minutes) return `${totalSeconds} second${totalSeconds === 1 ? '' : 's'}`;
    return remainder
        ? `${minutes} minute${minutes === 1 ? '' : 's'} ${remainder} second${remainder === 1 ? '' : 's'}`
        : `${minutes} minute${minutes === 1 ? '' : 's'}`;
}

export function rateLimitMessage(error, fallback) {
    const { kind, retryAfterSeconds } = classifyHttpError(error);
    if (kind !== 'rate-limited' || retryAfterSeconds === null || retryAfterSeconds <= 0) return fallback;
    return `${fallback} Try again in ${formatRetryDelay(retryAfterSeconds)}.`;
}
