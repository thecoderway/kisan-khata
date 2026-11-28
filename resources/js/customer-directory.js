import { attachVillageDropdown } from './village-dropdown.js';

export function registerCustomerDirectory() {
    const form = document.querySelector('[data-customer-filters]');
    const results = document.querySelector('[data-customer-results]');

    if (!form || !results) return;

    const searchInput = form.querySelector('[name="q"]');
    const statusInput = form.querySelector('[name="status"]');
    const villageInput = form.querySelector('[name="village_code"]');
    const villageSearchInput = form.querySelector('[data-village-search]');
    const villageOptions = form.querySelector('[data-village-options]');
    const villageOptionsUrl = form.dataset.villageOptionsUrl || '';
    const statusRegion = document.querySelector('[data-customer-filter-status]');
    const resetLink = form.querySelector('[data-customer-filter-reset]');
    let debounceTimer = null;
    let villageSearchTimer = null;
    let activeController = null;
    let villageSearchController = null;
    let composing = false;
    let activeVillageIndex = -1;
    const setVillageMenuOpen = attachVillageDropdown(villageSearchInput, villageOptions);
    const selectedVillageLabels = new Map();

    if (villageInput?.value && villageSearchInput?.value) {
        selectedVillageLabels.set(villageInput.value, villageSearchInput.value);
    }

    const updateResetVisibility = () => {
        if (!resetLink) return;
        resetLink.hidden = !((searchInput?.value || '').trim() || (statusInput?.value && statusInput.value !== 'active') || (villageInput?.value || ''));
    };

    const setVillageOptionsOpen = (open) => {
        if (!villageOptions || !villageSearchInput) return;
        setVillageMenuOpen(open);
        villageSearchInput.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const renderVillageOptions = (items, emptyMessage = 'कोई गाँव नहीं मिला।') => {
        if (!villageOptions) return;
        villageOptions.replaceChildren();
        activeVillageIndex = -1;

        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item text-muted small';
            empty.textContent = emptyMessage;
            villageOptions.append(empty);
            setVillageOptionsOpen(true);
            return;
        }

        items.forEach((item) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'list-group-item list-group-item-action';
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.dataset.villageOption = '';
            option.dataset.villageCode = item.code;
            option.dataset.villageLabel = item.label;
            option.textContent = item.label;
            villageOptions.append(option);
        });

        setVillageOptionsOpen(true);
    };

    const loadVillageOptions = async (term) => {
        if (!villageOptionsUrl || !villageSearchInput) return;
        if (term.trim().length < 2) {
            renderVillageOptions([], 'कम से कम 2 अक्षर लिखें।');
            return;
        }

        villageSearchController?.abort();
        const controller = new AbortController();
        villageSearchController = controller;
        const url = new URL(villageOptionsUrl, window.location.origin);
        url.searchParams.set('q', term.trim());

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !Array.isArray(payload.data)) {
                throw new Error(payload.message || 'गाँवों की सूची लोड नहीं हो सकी।');
            }
            if (villageSearchController !== controller) return;
            payload.data.forEach((item) => selectedVillageLabels.set(item.code, item.label));
            renderVillageOptions(payload.data);
        } catch (error) {
            if (error.name !== 'AbortError' && villageSearchController === controller) {
                renderVillageOptions([], error.message || 'गाँवों की सूची लोड नहीं हो सकी।');
            }
        }
    };

    const loadVillageLabel = async (code) => {
        if (!code) return '';
        if (selectedVillageLabels.has(code)) return selectedVillageLabels.get(code);
        if (!villageOptionsUrl) return '';

        const url = new URL(villageOptionsUrl, window.location.origin);
        url.searchParams.set('code', code);
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => ({}));
            const label = response.ok ? (payload.selected?.label || '') : '';
            if (label) selectedVillageLabels.set(code, label);
            return label;
        } catch {
            return '';
        }
    };

    const chooseVillage = (code, label) => {
        if (villageInput) villageInput.value = code;
        if (villageSearchInput) villageSearchInput.value = label;
        selectedVillageLabels.set(code, label);
        setVillageOptionsOpen(false);
        updateResetVisibility();
        applyFilters('replace');
    };

    const syncFiltersFromUrl = async (url) => {
        if (searchInput) searchInput.value = url.searchParams.get('q') || '';
        if (statusInput) statusInput.value = url.searchParams.get('status') || 'active';
        const villageCode = url.searchParams.get('village_code') || '';
        if (villageInput) villageInput.value = villageCode;
        if (villageSearchInput) villageSearchInput.value = await loadVillageLabel(villageCode);
        const pageSizeInput = results.querySelector('[data-customer-page-size]');
        if (pageSizeInput) pageSizeInput.value = url.searchParams.get('pageSize') || '20';
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

        const currentUrl = new URL(window.location.href);
        url.searchParams.set('pageSize', currentUrl.searchParams.get('pageSize') || '20');
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

    villageSearchInput?.addEventListener('focus', () => {
        if (!villageSearchInput.value.trim()) {
            renderVillageOptions([], 'गाँव खोजने के लिए कम से कम 2 अक्षर लिखें।');
        }
    });

    villageSearchInput?.addEventListener('input', () => {
        const hadSelectedVillage = Boolean(villageInput?.value);
        if (villageInput) villageInput.value = '';
        updateResetVisibility();
        window.clearTimeout(villageSearchTimer);

        const term = villageSearchInput.value.trim();
        if (!term) {
            setVillageOptionsOpen(false);
            if (hadSelectedVillage) applyFilters('replace');
            return;
        }

        villageSearchTimer = window.setTimeout(() => loadVillageOptions(term), 250);
    });

    villageSearchInput?.addEventListener('keydown', (event) => {
        if (!villageOptions || villageOptions.hidden) return;
        const options = [...villageOptions.querySelectorAll('[data-village-option]')];

        if (event.key === 'Escape') {
            setVillageOptionsOpen(false);
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            activeVillageIndex = Math.max(0, Math.min(options.length - 1, activeVillageIndex + direction));
            options.forEach((option, index) => {
                option.setAttribute('aria-selected', index === activeVillageIndex ? 'true' : 'false');
            });
            options[activeVillageIndex]?.scrollIntoView({ block: 'nearest' });
        }

        if (event.key === 'Enter' && activeVillageIndex >= 0) {
            event.preventDefault();
            const option = options[activeVillageIndex];
            if (option) chooseVillage(option.dataset.villageCode, option.dataset.villageLabel);
        }
    });

    villageOptions?.addEventListener('click', (event) => {
        const option = event.target.closest('[data-village-option]');
        if (!option) return;
        chooseVillage(option.dataset.villageCode, option.dataset.villageLabel);
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-village-filter]') || villageOptions?.contains(event.target)) return;
        setVillageOptionsOpen(false);
    });

    statusInput?.addEventListener('change', () => {
        updateResetVisibility();
        applyFilters('replace');
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        applyFilters('replace');
    });

    document.addEventListener('click', async (event) => {
        const resetLink = event.target.closest('[data-customer-filter-reset]');
        if (resetLink && form.contains(resetLink)) {
            event.preventDefault();
            if (searchInput) searchInput.value = '';
            if (statusInput) statusInput.value = 'active';
            if (villageInput) villageInput.value = '';
            if (villageSearchInput) villageSearchInput.value = '';
            setVillageOptionsOpen(false);
            updateResetVisibility();
            applyFilters('push');
            return;
        }

        const pageLink = event.target.closest('[data-customer-results] .pagination a');
        if (!pageLink) return;

        event.preventDefault();
        const targetUrl = new URL(pageLink.href, window.location.origin);
        await syncFiltersFromUrl(targetUrl);
        loadResults(targetUrl, 'push');
    });

    document.addEventListener('change', (event) => {
        const pageSizeSelect = event.target.closest('[data-customer-page-size]');
        if (!pageSizeSelect) return;

        const targetUrl = new URL(window.location.href);
        targetUrl.searchParams.set('pageSize', pageSizeSelect.value);
        targetUrl.searchParams.delete('page');
        loadResults(targetUrl, 'push');
    });

    window.addEventListener('popstate', async () => {
        const currentUrl = new URL(window.location.href);
        await syncFiltersFromUrl(currentUrl);
        loadResults(currentUrl, 'none');
    });

    window.addEventListener('kk-drawer:success', () => {
        loadResults(new URL(window.location.href), 'none');
    });
}
