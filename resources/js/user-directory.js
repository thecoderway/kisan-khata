export function registerUserDirectory() {
    const form = document.querySelector('[data-user-filters]');
    const results = document.querySelector('[data-user-results]');

    if (!form || !results) return;

    const searchInput = form.querySelector('[name="q"]');
    const roleInput = form.querySelector('[name="role"]');
    const statusInput = form.querySelector('[name="status"]');
    const pageSizeInput = form.querySelector('[name="pageSize"]');
    const statusRegion = document.querySelector('[data-user-filter-status]');
    const resetLink = form.querySelector('[data-user-filter-reset]');
    let debounceTimer = null;
    let activeController = null;
    let composing = false;

    const updateResetVisibility = () => {
        if (!resetLink) return;
        resetLink.hidden = !(
            (searchInput?.value || '').trim()
            || (roleInput?.value && roleInput.value !== 'all')
            || (statusInput?.value && statusInput.value !== 'all')
        );
    };

    const syncFiltersFromUrl = (url) => {
        if (searchInput) searchInput.value = url.searchParams.get('q') || '';
        if (roleInput) roleInput.value = url.searchParams.get('role') || 'all';
        if (statusInput) statusInput.value = url.searchParams.get('status') || 'all';
        if (pageSizeInput) pageSizeInput.value = url.searchParams.get('pageSize') || '20';
        updateResetVisibility();
    };

    const buildFilterUrl = () => {
        const url = new URL(form.action, window.location.origin);

        for (const [key, value] of new FormData(form).entries()) {
            const normalized = String(value).trim();

            if (normalized === '') {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, normalized);
            }
        }

        const currentUrl = new URL(window.location.href);
        url.searchParams.set('pageSize', pageSizeInput?.value || currentUrl.searchParams.get('pageSize') || '20');
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
        if (statusRegion) statusRegion.textContent = 'यूज़र सूची अपडेट हो रही है…';

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
                throw new Error(payload.message || 'यूज़र सूची लोड नहीं हो सकी। कृपया दोबारा प्रयास करें।');
            }

            const requestedPage = Number(pageUrl.searchParams.get('page') || 1);
            const lastPage = Math.max(1, Number(payload.meta?.last_page || 1));

            if (requestedPage > lastPage) {
                pageUrl.searchParams.set('page', String(lastPage));
                return await loadResults(pageUrl, historyMode);
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
                    ? '1 यूज़र मिला।'
                    : total + ' यूज़र मिले।';
            }
        } catch (error) {
            if (error.name !== 'AbortError' && statusRegion) {
                statusRegion.textContent = error.message || 'यूज़र सूची अपडेट नहीं हो सकी।';
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

    roleInput?.addEventListener('change', () => {
        updateResetVisibility();
        applyFilters('replace');
    });

    statusInput?.addEventListener('change', () => {
        updateResetVisibility();
        applyFilters('replace');
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        applyFilters('replace');
    });

    document.addEventListener('click', (event) => {
        const reset = event.target.closest('[data-user-filter-reset]');
        if (reset && form.contains(reset)) {
            event.preventDefault();
            if (searchInput) searchInput.value = '';
            if (roleInput) roleInput.value = 'all';
            if (statusInput) statusInput.value = 'all';
            updateResetVisibility();
            applyFilters('push');
            return;
        }

        const pageLink = event.target.closest('[data-user-results] .pagination a');
        if (!pageLink) return;

        event.preventDefault();
        const targetUrl = new URL(pageLink.href, window.location.origin);
        syncFiltersFromUrl(targetUrl);
        loadResults(targetUrl, 'push');
    });

    document.addEventListener('change', (event) => {
        const pageSizeSelect = event.target.closest('[data-user-page-size]');
        if (!pageSizeSelect) return;

        const targetUrl = new URL(window.location.href);
        targetUrl.searchParams.set('pageSize', pageSizeSelect.value);
        targetUrl.searchParams.delete('page');
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
