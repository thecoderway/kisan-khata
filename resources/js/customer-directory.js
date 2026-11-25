export function registerCustomerDirectory() {
    const form = document.querySelector('[data-customer-filters]');
    const results = document.querySelector('[data-customer-results]');

    if (!form || !results) return;

    const searchInput = form.querySelector('[name="q"]');
    const statusInput = form.querySelector('[name="status"]');
    const statusRegion = document.querySelector('[data-customer-filter-status]');
    const resetLink = form.querySelector('[data-customer-filter-reset]');
    let debounceTimer = null;
    let activeController = null;
    let composing = false;

    const updateResetVisibility = () => {
        if (!resetLink) return;
        resetLink.hidden = !((searchInput?.value || '').trim() || (statusInput?.value && statusInput.value !== 'active'));
    };

    const syncFiltersFromUrl = (url) => {
        if (searchInput) searchInput.value = url.searchParams.get('q') || '';
        if (statusInput) statusInput.value = url.searchParams.get('status') || 'active';
        updateResetVisibility();
    };

    const buildFilterUrl = () => {
        const url = new URL(form.action, window.location.origin);
        const values = new FormData(form);

        for (const [key, value] of values.entries()) {
            const normalized = String(value).trim();

            if (normalized === '') {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, normalized);
            }
        }

        url.searchParams.delete('page');

        return url;
    };

    const loadResults = async (targetUrl, historyMode = 'replace') => {
        const pageUrl = new URL(targetUrl, window.location.origin);
        const requestUrl = new URL(pageUrl);
        requestUrl.searchParams.set('fragment', '1');

        activeController?.abort();
        const controller = new AbortController();
        activeController = controller;

        results.setAttribute('aria-busy', 'true');
        if (statusRegion) statusRegion.textContent = 'परिणाम अपडेट हो रहे हैं…';

        try {
            const response = await fetch(requestUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok || typeof payload.html !== 'string') {
                throw new Error(payload.message || 'परिणाम लोड नहीं हो सके। कृपया दोबारा प्रयास करें।');
            }

            results.innerHTML = payload.html;

            if (historyMode === 'replace') {
                window.history.replaceState({}, '', pageUrl);
            } else if (historyMode === 'push') {
                window.history.pushState({}, '', pageUrl);
            }

            if (statusRegion) {
                const total = Number(payload.meta?.total ?? 0);
                statusRegion.textContent = total === 1
                    ? '1 ग्राहक मिला।'
                    : total + ' ग्राहक मिले।';
            }
        } catch (error) {
            if (error.name !== 'AbortError' && statusRegion) {
                statusRegion.textContent = error.message || 'परिणाम अपडेट नहीं हो सके।';
            }
        } finally {
            if (activeController === controller) {
                activeController = null;
                results.removeAttribute('aria-busy');
            }
        }
    };

    const applyFilters = (historyMode = 'replace') => {
        window.clearTimeout(debounceTimer);
        loadResults(buildFilterUrl(), historyMode);
    };

    searchInput?.addEventListener('compositionstart', () => {
        composing = true;
        window.clearTimeout(debounceTimer);
    });

    searchInput?.addEventListener('compositionend', () => {
        composing = false;
        applyFilters('replace');
    });

    searchInput?.addEventListener('input', () => {
        updateResetVisibility();
        if (composing) return;

        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => applyFilters('replace'), 375);
    });

    statusInput?.addEventListener('change', () => {
        if (feedbackRegion) feedbackRegion.hidden = true;
        updateResetVisibility();
        applyFilters('replace');
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        applyFilters('replace');
    });

    document.addEventListener('click', (event) => {
        const resetLink = event.target.closest('[data-customer-filter-reset]');
        if (resetLink && form.contains(resetLink)) {
            event.preventDefault();
            if (searchInput) searchInput.value = '';
            if (statusInput) statusInput.value = 'active';
            updateResetVisibility();
            applyFilters('push');
            return;
        }

        const pageLink = event.target.closest('[data-customer-results] .pagination a');
        if (!pageLink) return;

        event.preventDefault();
        const targetUrl = new URL(pageLink.href, window.location.origin);
        syncFiltersFromUrl(targetUrl);
        loadResults(targetUrl, 'push');
    });

    window.addEventListener('popstate', () => {
        const currentUrl = new URL(window.location.href);
        syncFiltersFromUrl(currentUrl);
        loadResults(currentUrl, 'none');
    });

    window.addEventListener('kk-drawer:success', () => {
        loadResults(new URL(window.location.href), 'none');
    });
}
