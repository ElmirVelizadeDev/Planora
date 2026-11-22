(() => {
    const retryLink = document.getElementById('error-retry');
    const retryNote = document.getElementById('retry-note');
    const retryAfter = Number.parseInt(document.body.dataset.retryAfter || '', 10);
    const intervalMs = Math.max(15_000, (Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : 60) * 1000);
    const maxAutomaticChecks = 8;
    let automaticChecks = 0;
    let requestPending = false;
    let automaticTimer = null;

    async function checkCurrentPage(automatic = false) {
        if (requestPending || (automatic && automaticChecks >= maxAutomaticChecks)) return;
        requestPending = true;
        if (automatic) automaticChecks += 1;
        if (retryLink) retryLink.setAttribute('aria-disabled', 'true');
        if (retryNote) retryNote.textContent = 'Checking whether this page is available…';

        try {
            const response = await fetch(window.location.href, {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { Accept: 'text/html' },
            });

            if (response.ok) {
                // Navigate with GET so a page reached after a form submission is never resubmitted.
                window.location.assign(window.location.href);
                return;
            }

            if (retryNote) {
                retryNote.textContent = automatic && automaticChecks >= maxAutomaticChecks
                    ? 'Automatic checks are paused. Select Check again whenever you are ready.'
                    : 'This page is still unavailable. We will check again later.';
            }
        } catch {
            if (retryNote) {
                retryNote.textContent = automatic && automaticChecks >= maxAutomaticChecks
                    ? 'Automatic checks are paused. Select Check again whenever you are ready.'
                    : 'The availability check could not connect. We will try again later.';
            }
        } finally {
            requestPending = false;
            if (retryLink) retryLink.removeAttribute('aria-disabled');
            if (automaticChecks >= maxAutomaticChecks) window.clearInterval(automaticTimer);
        }
    }

    retryLink?.addEventListener('click', (event) => {
        event.preventDefault();
        checkCurrentPage(false);
    });

    automaticTimer = window.setInterval(() => checkCurrentPage(true), intervalMs);
})();
