(() => {
    const scope = document.body.dataset.maintenanceScope || '';
    const retryAfter = document.body.dataset.retryAfter || '60';
    const retryNote = document.getElementById('retry-note');
    const pollInterval = Math.max(15, Number.parseInt(retryAfter, 10) || 60) * 1000;
    let requestPending = false;

    async function checkAvailability() {
        if (requestPending) return;
        requestPending = true;

        try {
            const response = scope
                ? await fetch('/api/v1/maintenance/status', {
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                })
                : await fetch('/', { method: 'GET', cache: 'no-store', credentials: 'same-origin' });

            if (scope) {
                const status = await response.json();
                if (response.ok && status.data?.scopes?.[scope] === false) window.location.assign('/');
                return;
            }

            if (response.ok) window.location.assign('/');
        } catch {
            if (retryNote) retryNote.textContent = 'We will check availability automatically.';
        } finally {
            requestPending = false;
        }
    }

    window.setInterval(checkAvailability, pollInterval);
})();
