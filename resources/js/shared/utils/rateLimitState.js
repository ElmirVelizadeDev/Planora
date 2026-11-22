import { ref } from 'vue';

export const rateLimitDeadline = ref(null);

export function recordRateLimit(retryAfterSeconds) {
    const seconds = Number(retryAfterSeconds);
    if (!Number.isFinite(seconds) || seconds <= 0) return;
    rateLimitDeadline.value = Date.now() + seconds * 1000;
}
