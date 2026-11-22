const SAFE_BASE = 'https://planora.invalid';

export function safeInternalRedirect(value, router) {
    if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || value.includes('\\')) return null;

    let target;
    try {
        const parsed = new URL(value, SAFE_BASE);
        if (parsed.origin !== SAFE_BASE) return null;
        target = `${parsed.pathname}${parsed.search}${parsed.hash}`;
    } catch {
        return null;
    }

    if (!router) return target;
    const resolved = router.resolve(target);
    if (!resolved.matched.length || resolved.name === 'login' || resolved.name === 'not-found'
        || String(resolved.name || '').startsWith('error.')) return null;
    return resolved.fullPath;
}
