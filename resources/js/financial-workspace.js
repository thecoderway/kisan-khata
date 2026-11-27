import { customerDisplayLabels, fetchFinancialCustomers } from './financial-customer-directory';

const config = window.KKFinancialWorkspace;
const root = document.getElementById('financial-results');
const feedback = document.getElementById('financial-feedback');
const section = config?.section;
const api = config?.api;
const csrf = config?.csrf;
const filtersForm = document.getElementById('financial-filters');
const drawerRoot = document.querySelector('#financial-action-drawer')?.closest('.kk-drawer-root');
let customers = [];
let currentShopPolicy = null;
let currentPage = 1;
let pageSize = 20;
let lastPage = 1;
let activeController = null;
let debounceTimer = null;
let composing = false;
let lastLoadedQuery = null;
let activeQuery = null;

const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
}[char]));
const money = (value) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(value || 0));
const date = (value) => value ? new Date(value).toLocaleDateString('en-IN') : '—';
const saleStatusLabel = (value) => ({
    UNPAID: 'उधार',
    PARTIALLY_PAID: 'आंशिक जमा',
    PAID: 'जमा पूर्ण',
}[value] || value);
const paymentMethodLabel = (value) => ({
    CASH: 'नकद',
    UPI: 'UPI',
    BANK: 'बैंक',
    CHEQUE: 'चेक',
}[value] || value || '—');
const customerLifecycleLabel = (customer) => {
    if (!customer) return '';
    if (customer.deleted_at) return ' <span class="badge bg-secondary-lt">हटाया गया</span>';
    if (customer.status === 'inactive') return ' <span class="badge bg-warning-lt">निष्क्रिय</span>';
    return '';
};
const paymentTypeLabel = (value) => ({
    NORMAL: 'सामान्य जमा',
    SALE_DAY_AUTO: 'बिक्री के साथ स्वतः जमा',
    REVERSAL: 'रिवर्सल',
}[value] || value);
const showMessage = (message, kind = 'success') => {
    window.dispatchEvent(new CustomEvent('kk-toast:show', {
        detail: { message, variant: kind === 'error' ? 'error' : 'success' },
    }));
};
const request = async (path, options = {}) => {
    const response = await fetch(api + path, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
            ...(options.headers || {}),
        },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const errors = Object.values(payload.errors || {}).flat().join(' ');
        throw new Error(errors || payload.message || 'अनुरोध पूरा नहीं हो सका।');
    }
    return payload;
};

const readUrlState = () => {
    const url = new URL(window.location.href);
    currentPage = Math.max(1, Number(url.searchParams.get('page') || 1));
    pageSize = [10, 20, 50, 100].includes(Number(url.searchParams.get('per_page'))) ? Number(url.searchParams.get('per_page')) : 20;
    if (filtersForm) {
        for (const name of ['q', 'from', 'to', 'payment_status', 'payment_method', 'sort']) {
            const input = filtersForm.elements.namedItem(name);
            if (input) input.value = url.searchParams.get(name) || (name === 'sort' ? 'date_desc' : '');
        }
    } else {
        for (const name of ['from', 'to', 'action']) {
            const input = document.getElementById('financial-' + name);
            if (input) input.value = url.searchParams.get(name) || '';
        }
    }
};
const buildQuery = () => {
    const params = new URLSearchParams();
    params.set('page', String(currentPage));
    params.set('per_page', String(pageSize));
    if (filtersForm) {
        for (const [key, value] of new FormData(filtersForm).entries()) {
            const normalized = String(value).trim();
            if (normalized && key !== 'per_page') params.set(key, normalized);
        }
    } else {
        for (const name of ['from', 'to', 'action']) {
            const value = document.getElementById('financial-' + name)?.value.trim();
            if (value) params.set(name, value);
        }
    }
    return params.toString();
};
const syncUrl = (mode = 'replace') => {
    const url = new URL(window.location.href);
    url.search = buildQuery();
    if (mode === 'push') window.history.pushState({}, '', url);
    else if (mode === 'replace') window.history.replaceState({}, '', url);
};
const renderTable = (headers, rows, emptyTitle = 'कोई रिकॉर्ड नहीं मिला') => {
    if (!rows.length) {
        root.innerHTML = '<div class="empty-state"><i class="ti ti-inbox" aria-hidden="true"></i><h3>' + esc(emptyTitle) + '</h3><p>चुने गए filter के अनुसार कोई डेटा उपलब्ध नहीं है।</p></div>';
        return;
    }
    root.innerHTML = '<div class="kk-data-table-wrap" role="region" aria-label="वित्तीय रिकॉर्ड" tabindex="0"><table class="table table-vcenter financial-table"><thead><tr>' +
        headers.map((header) => '<th scope="col">' + esc(header) + '</th>').join('') +
        '</tr></thead><tbody>' + rows.map((row) => '<tr>' + row.map((cell) => '<td>' + cell + '</td>').join('') + '</tr>').join('') +
        '</tbody></table></div>';
};
const renderPagination = (meta = {}) => {
    const page = Number(meta.current_page || 1);
    const perPage = Number(meta.per_page || pageSize);
    const total = Number(meta.total || 0);
    lastPage = Math.max(1, Number(meta.last_page || Math.ceil(total / perPage)));
    const first = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const last = total === 0 ? 0 : Math.min(page * perPage, total);
    const pageItems = [];
    const pageHref = (number) => {
        const url = new URL(window.location.href);
        url.searchParams.set('page', String(number));
        url.searchParams.set('per_page', String(perPage));
        return esc(url.pathname + url.search);
    };
    const addPage = (number) => pageItems.push(
        '<li class="page-item' + (number === page ? ' active' : '') + '">' +
        '<a class="page-link" href="' + pageHref(number) + '" data-financial-page="' + number + '"' +
        (number === page ? ' aria-current="page"' : '') + '>' + number + '</a></li>'
    );
    const addEllipsis = () => pageItems.push(
        '<li class="page-item disabled" aria-hidden="true"><span class="page-link">…</span></li>'
    );

    if (lastPage <= 7) {
        for (let number = 1; number <= lastPage; number += 1) addPage(number);
    } else {
        addPage(1);
        if (page > 3) addEllipsis();
        const rangeStart = Math.max(2, page - 1);
        const rangeEnd = Math.min(lastPage - 1, page + 1);
        for (let number = rangeStart; number <= rangeEnd; number += 1) addPage(number);
        if (page < lastPage - 2) addEllipsis();
        addPage(lastPage);
    }

    root.insertAdjacentHTML('beforeend',
        '<div class="financial-pagination customer-pagination-wrap d-flex flex-wrap align-items-center justify-content-between gap-3">' +
        '<span class="customer-pagination-summary text-secondary small" aria-live="polite">Showing <strong>' + first + '–' + last + '</strong> of <strong>' + total + '</strong> records</span>' +
        '<div class="customer-pagination-controls d-flex flex-wrap align-items-center gap-3">' +
        '<label class="customer-page-size d-flex align-items-center gap-2 text-secondary small"><span>Rows per page</span><select class="form-select form-select-sm" data-financial-page-size aria-label="Rows per page">' +
        [10, 20, 50, 100].map((size) => '<option value="' + size + '"' + (size === perPage ? ' selected' : '') + '>' + size + '</option>').join('') +
        '</select></label><nav aria-label="Financial records pagination"><ul class="pagination">' +
        '<li class="page-item' + (page <= 1 ? ' disabled' : '') + '"><a class="page-link" href="' + (page <= 1 ? '#' : pageHref(page - 1)) + '" data-financial-page="' + (page - 1) + '"' + (page <= 1 ? ' aria-disabled="true" tabindex="-1"' : '') + ' aria-label="Previous page"><i class="ti ti-chevron-left" aria-hidden="true"></i></a></li>' +
        pageItems.join('') +
        '<li class="page-item' + (page >= lastPage ? ' disabled' : '') + '"><a class="page-link" href="' + (page >= lastPage ? '#' : pageHref(page + 1)) + '" data-financial-page="' + (page + 1) + '"' + (page >= lastPage ? ' aria-disabled="true" tabindex="-1"' : '') + ' aria-label="Next page"><i class="ti ti-chevron-right" aria-hidden="true"></i></a></li>' +
        '</ul></nav></div></div>');
};
const loadCustomers = async () => {
    const mode = ['ledger', 'reconciliation'].includes(section)
        ? 'history'
        : section === 'interest' ? 'policy' : 'transaction';
    customers = await fetchFinancialCustomers(request, mode);
    const labels = customerDisplayLabels(customers, { includeStatus: mode !== 'transaction' });
    const selectId = ['ledger', 'reconciliation'].includes(section)
        ? 'financial-customer'
        : section === 'interest' ? 'interest-customer' : null;
    const select = selectId ? document.getElementById(selectId) : null;
    if (select) {
        select.innerHTML = '<option value="">ग्राहक चुनें</option>' + customers.map((customer, index) =>
            '<option value="' + esc(customer.id) + '">' + esc(labels[index]) + '</option>'
        ).join('');
    }
};
const customerOptions = () => {
    const labels = customerDisplayLabels(customers);
    return customers.map((customer, index) => '<option value="' + esc(customer.id) + '">' + esc(labels[index]) + '</option>').join('');
};

const loadSales = async (signal) => {
    const payload = await request('/sales?' + buildQuery(), { signal });
    renderTable(['बिक्री नंबर', 'ग्राहक', 'तारीख', 'राशि', 'स्थिति', 'कार्रवाई'], (payload.data || []).map((sale) => [
        '<strong>' + esc(sale.sale_number) + '</strong>',
        esc(sale.customer?.name_hi || sale.customer?.name_original || '—') + customerLifecycleLabel(sale.customer),
        date(sale.sale_date), money(sale.amount),
        '<span class="badge bg-' + (sale.payment_status === 'PAID' ? 'success' : 'warning') + '-lt">' + esc(saleStatusLabel(sale.payment_status)) + '</span>',
        '<div class="d-flex flex-wrap gap-1"><button class="btn btn-sm btn-outline-primary" type="button" data-financial-action="view-sale" data-id="' + esc(sale.id) + '"><i class="ti ti-eye me-1" aria-hidden="true"></i>विवरण</button>' +
            (config.role === 'owner' ? '<button class="btn btn-sm btn-outline-secondary" type="button" data-financial-action="revise-sale" data-id="' + esc(sale.id) + '" data-description="' + esc(sale.description || '') + '"><i class="ti ti-edit me-1" aria-hidden="true"></i>विवरण संशोधन</button>' : '') + '</div>',
    ]));
    renderPagination(payload.meta);
};
const loadPayments = async (signal) => {
    const payload = await request('/payments?' + buildQuery(), { signal });
    renderTable(['भुगतान नंबर', 'ग्राहक', 'तारीख', 'राशि', 'माध्यम', 'प्रकार', 'कार्रवाई'], (payload.data || []).map((payment) => [
        '<strong>' + esc(payment.payment_number) + '</strong>',
        esc(payment.customer?.name_hi || payment.customer?.name_original || '—') + customerLifecycleLabel(payment.customer),
        date(payment.payment_date), money(payment.amount),
        esc(paymentMethodLabel(payment.payment_method)),
        '<span class="badge bg-secondary-lt">' + esc(paymentTypeLabel(payment.payment_type)) + '</span>',
        config.role === 'owner' && payment.payment_type === 'NORMAL'
            ? '<button class="btn btn-sm btn-outline-danger" type="button" data-financial-action="correct-payment" data-id="' + esc(payment.id) + '" data-customer-id="' + esc(payment.customer_id) + '" data-payment-number="' + esc(payment.payment_number) + '" data-payment-amount="' + esc(payment.amount) + '" data-customer-name="' + esc(payment.customer?.name_hi || payment.customer?.name_original || 'ग्राहक') + '"><i class="ti ti-arrow-back-up me-1" aria-hidden="true"></i>रिवर्स / बदलें</button>'
            : '—',
    ]));
    renderPagination(payload.meta);
};
const loadLedger = async (signal) => {
    const id = document.getElementById('financial-customer')?.value;
    if (!id) { root.innerHTML = '<div class="empty-state"><i class="ti ti-user-search"></i><h3>ग्राहक चुनें</h3><p>उस ग्राहक की खाता-बही देखने के लिए ऊपर से चयन करें।</p></div>'; return; }
    const payload = await request('/customers/' + id + '/ledger?' + buildQuery(), { signal });
    const rows = (payload.data || []).map((entry) => [
        date(entry.effective_date), esc(entry.entry_kind), esc(entry.component),
        money(entry.debit), money(entry.credit), money(entry.total_balance),
    ]);
    renderTable(['तारीख', 'लेन-देन', 'खाता', 'Debit', 'Credit', 'बाकी'], rows);
    const opening = payload.meta?.opening_balance;
    if (opening) {
        root.insertAdjacentHTML('afterbegin', '<div class="alert alert-info mx-3 mt-3 mb-0" role="status">Opening balance — Principal: <strong>' +
            money(opening.principal) + '</strong> · Interest: <strong>' + money(opening.interest) + '</strong> · Total: <strong>' + money(opening.total) + '</strong></div>');
    }
    renderPagination(payload.meta);
};
const loadInterest = async (signal) => {
    const payload = await request('/interest-policy', { signal });
    const versions = payload.data || [];
    currentShopPolicy = versions[0] || null;
    const overrideButton = document.getElementById('open-customer-policy-drawer');
    if (overrideButton) {
        overrideButton.disabled = versions.length === 0;
        overrideButton.title = versions.length === 0 ? 'पहले Shop Interest Policy configure करें।' : '';
    }
    if (versions.length === 0) {
        root.innerHTML = '<div class="empty-state"><i class="ti ti-percentage" aria-hidden="true"></i><h3>Shop Interest Policy अभी configure नहीं है</h3><p>इस पुरानी दुकान के लिए पहले “नई Shop Interest Policy” से पहली policy बनाएँ। कोई ब्याज दर अपने-आप लागू नहीं की जाएगी।</p></div>';
        return;
    }
    renderTable(['Version', 'लागू तारीख', 'मासिक दर', 'Grace', 'Capitalization', 'Waterfall', 'Settlement dates'],
        versions.map((version) => [
            esc(version.version_number), date(version.effective_from), esc(version.monthly_rate_percent) + '%',
            esc(version.grace_days) + ' दिन', version.capitalization_enabled ? 'चालू' : 'बंद',
            esc(version.payment_waterfall),
            esc(version.annual_settlement_month_1 + '/' + version.annual_settlement_day_1 + ' · ' + version.annual_settlement_month_2 + '/' + version.annual_settlement_day_2),
        ]));
};
const loadReconciliation = async (signal) => {
    const id = document.getElementById('financial-customer')?.value;
    if (!id) { root.innerHTML = '<div class="empty-state"><i class="ti ti-user-search"></i><h3>ग्राहक चुनें</h3><p>Projection reconciliation के लिए Customer चुनें।</p></div>'; return; }
    const payload = await request('/reconciliation/customers/' + id, { signal });
    const result = payload.data;
    renderTable(['स्थिति', 'Field / तारीख', 'Expected', 'Actual'], (result.discrepancies || []).map((item) => [
        '<span class="badge bg-secondary-lt">' + esc(item.type) + '</span>',
        esc(item.field || item.as_of_date || item.sale_id || item.payment_id || '—'),
        esc(JSON.stringify(item.expected ?? '—')), esc(JSON.stringify(item.actual ?? '—')),
    ]));
    showMessage(result.consistent ? 'Ledger और projection में कोई अंतर नहीं मिला।' : result.discrepancies.length + ' discrepancy मिली।', result.consistent ? 'success' : 'error');
};
const loadAudit = async (signal) => {
    const payload = await request('/financial-audit?' + buildQuery(), { signal });
    renderTable(['समय', 'User', 'Action', 'Entity', 'Reason'], (payload.data || []).map((audit) => [
        date(audit.created_at), esc(audit.user?.name || 'System'), esc(audit.action),
        esc(audit.entity_type + ' #' + audit.entity_id), esc(audit.reason || '—'),
    ]));
    renderPagination(payload.meta);
};
const loaders = { sales: loadSales, payments: loadPayments, ledger: loadLedger, interest: loadInterest, reconciliation: loadReconciliation, audit: loadAudit };

const refresh = async ({ history = 'none', skipUnchanged = false } = {}) => {
    const queryKey = buildQuery();
    if (skipUnchanged && (queryKey === lastLoadedQuery || (activeController && queryKey === activeQuery))) {
        if (history !== 'none') syncUrl(history);
        return;
    }
    activeController?.abort();
    const controller = new AbortController();
    activeController = controller;
    activeQuery = queryKey;
    root.setAttribute('aria-busy', 'true');
    if (!root.querySelector('[data-financial-loading]')) {
        root.insertAdjacentHTML('afterbegin', '<div class="financial-loading-indicator" data-financial-loading role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> परिणाम अपडेट हो रहे हैं…</div>');
    }
    feedback.textContent = '';
    try {
        await loaders[section](controller.signal);
        lastLoadedQuery = queryKey;
        if (history !== 'none') syncUrl(history);
    } catch (error) {
        if (error.name !== 'AbortError' && activeController === controller) {
            feedback.innerHTML = '<div class="alert alert-danger d-flex align-items-center justify-content-between gap-2" role="alert"><span>' +
                esc(error.message || 'डेटा लोड नहीं हो सका।') + '</span><button type="button" class="btn btn-sm btn-outline-danger" data-financial-retry>फिर प्रयास करें</button></div>';
        }
    } finally {
        if (activeController === controller) {
            activeController = null;
            activeQuery = null;
            root.removeAttribute('aria-busy');
            root.querySelector('[data-financial-loading]')?.remove();
        }
    }
};

const formField = (label, name, control, wide = false) =>
    '<div' + (wide ? ' class="financial-form-wide"' : '') + '><label class="form-label" for="financial-' + esc(name.replace(/[^a-z0-9]+/gi, '-')) + '">' + esc(label) + '</label>' +
    control.replace('FIELD_ID', 'financial-' + esc(name.replace(/[^a-z0-9]+/gi, '-'))) + '</div>';
const formMarkup = (kind, data = {}) => {
    const formStart = '<form id="financial-drawer-form" class="financial-form-grid" method="POST" data-drawer-ajax data-idempotency action="';
    const token = '<input type="hidden" name="_token" value="' + esc(csrf) + '">';
    if (kind === 'sale') {
        return formStart + esc(api + '/sales') + '" data-confirm="क्या आप इस बिक्री को पोस्ट करना चाहते हैं?">' + token +
            formField('ग्राहक', 'customer_id', '<select id="FIELD_ID" class="form-select" name="customer_id" required><option value="">ग्राहक चुनें</option>' + customerOptions() + '</select>') +
            formField('बिक्री तारीख', 'sale_date', '<input id="FIELD_ID" class="form-control" type="date" name="sale_date" value="' + new Date().toLocaleDateString('en-CA') + '" required>') +
            formField('राशि (₹)', 'amount', '<input id="FIELD_ID" class="form-control" type="number" name="amount" min="0.01" max="999999999999.99" step="0.01" inputmode="decimal" required>') +
            formField('भुगतान स्थिति', 'payment_choice', '<select id="FIELD_ID" class="form-select" name="payment_choice" required><option value="UNPAID">उधार</option><option value="PAID">नकद / Paid</option></select>') +
            formField('विवरण', 'description', '<textarea id="FIELD_ID" class="form-control" name="description" rows="3" maxlength="5000"></textarea>', true) + '</form>';
    }
    if (kind === 'payment') {
        return formStart + esc(api + '/payments') + '" data-confirm="क्या आप यह भुगतान पोस्ट करना चाहते हैं?">' + token +
            formField('ग्राहक', 'customer_id', '<select id="FIELD_ID" class="form-select" name="customer_id" required><option value="">ग्राहक चुनें</option>' + customerOptions() + '</select>') +
            formField('भुगतान तारीख', 'payment_date', '<input id="FIELD_ID" class="form-control" type="date" name="payment_date" value="' + new Date().toLocaleDateString('en-CA') + '" required>') +
            formField('राशि (₹)', 'amount', '<input id="FIELD_ID" class="form-control" type="number" name="amount" min="0.01" max="999999999999.99" step="0.01" inputmode="decimal" required>') +
            formField('माध्यम', 'payment_method', '<select id="FIELD_ID" class="form-select" name="payment_method"><option value="">चुनें</option><option value="CASH">Cash</option><option value="UPI">UPI</option><option value="BANK">Bank</option><option value="CHEQUE">Cheque</option></select>') +
            formField('Reference number', 'reference_number', '<input id="FIELD_ID" class="form-control" name="reference_number" maxlength="128">') +
            formField('विवरण', 'description', '<textarea id="FIELD_ID" class="form-control" name="description" rows="3" maxlength="5000"></textarea>', true) + '</form>';
    }
    if (kind === 'revise-sale') {
        return formStart + esc(api + '/sales/' + data.id + '/revision') + '" data-confirm="क्या आप बिक्री का विवरण संशोधित करना चाहते हैं?">' + token +
            formField('नया विवरण', 'description', '<textarea id="FIELD_ID" class="form-control" name="description" rows="3" maxlength="5000">' + esc(data.description || '') + '</textarea>', true) +
            formField('संशोधन का कारण', 'reason', '<input id="FIELD_ID" class="form-control" name="reason" minlength="1" maxlength="500" required>', true) + '</form>';
    }
    return formStart + esc(api + '/customers/' + data.customerId + '/payments/' + data.id + '/correction') + '" data-confirm="मूल भुगतान #' + esc(data.paymentNumber || data.id) + ' (₹' + esc(data.paymentAmount || '') + ') reverse होगा। क्या आप आगे बढ़ना चाहते हैं?" data-optional-nested-amount="replacement[amount]">' + token +
        '<div class="alert alert-warning financial-form-wide" role="note">मूल भुगतान <strong>#' + esc(data.paymentNumber || data.id) + '</strong> · ग्राहक <strong>' + esc(data.customerName || '—') + '</strong> · राशि <strong>' + money(data.paymentAmount || 0) + '</strong></div>' +
        formField('कारण', 'reason', '<textarea id="FIELD_ID" class="form-control" name="reason" rows="2" minlength="1" maxlength="500" required></textarea>', true) +
        formField('Replacement राशि (₹) — वैकल्पिक', 'replacement.amount', '<input id="FIELD_ID" class="form-control" type="number" name="replacement[amount]" min="0.01" step="0.01">') +
        formField('Replacement माध्यम', 'replacement.payment_method', '<select id="FIELD_ID" class="form-select" name="replacement[payment_method]"><option value="">चुनें</option><option value="CASH">Cash</option><option value="UPI">UPI</option><option value="BANK">Bank</option><option value="CHEQUE">Cheque</option></select>');
};
const detailValue = (value) => value === null || value === undefined || value === '' ? '—' : esc(value);
const detailRow = (label, value) => '<div class="d-flex justify-content-between gap-3 py-2 border-bottom"><span class="text-secondary">' + esc(label) + '</span><strong class="text-end">' + detailValue(value) + '</strong></div>';
const renderSaleDetails = (sale) => {
    const customer = sale.customer || {};
    const automaticPayment = sale.sale_day_payment;
    const allocations = (sale.allocation_items || []).filter((item) => item.payment?.payment_type !== 'SALE_DAY_AUTO');
    const ledgerEntries = sale.ledger_entries || [];
    const revisions = sale.revisions || [];
    const transaction = sale.financial_transaction;
    const paymentRows = allocations.map((item) => {
        const payment = item.payment || {};
        return '<tr><td>' + detailValue(payment.payment_number) + '</td><td>' + date(payment.payment_date) + '</td><td>' + money(payment.amount) + '</td><td>' + detailValue(paymentMethodLabel(payment.payment_method)) + '</td><td>' + detailValue(item.component_type) + '</td><td>' + money(item.amount) + '</td></tr>';
    }).join('');
    const ledgerRows = ledgerEntries.map((entry) => '<tr><td>' + detailValue(entry.id) + '</td><td>' + date(entry.effective_date) + '</td><td>' + detailValue(entry.component) + '</td><td>' + detailValue(entry.entry_kind) + '</td><td>' + money(entry.debit_amount) + '</td><td>' + money(entry.credit_amount) + '</td></tr>').join('');
    const revisionRows = revisions.map((revision) => '<tr><td>' + detailValue(revision.revision_number) + '</td><td>' + date(revision.changed_at) + '</td><td>' + detailValue(revision.actor?.name || revision.changed_by) + '</td><td>' + detailValue(revision.correction_reason) + '</td><td>' + detailValue(revision.new_values?.description) + '</td></tr>').join('');
    return '<div class="financial-sale-details">' +
        '<section class="mb-4"><h3 class="h4 mb-2">बिक्री जानकारी</h3>' +
        detailRow('Sale Number', sale.sale_number) +
        detailRow('ग्राहक', customer.name_hi || customer.name_original || customer.name_en) +
        detailRow('ग्राहक स्थिति', customer.deleted_at ? 'हटाया गया' : customer.status) +
        detailRow('बिक्री तारीख', date(sale.sale_date)) +
        detailRow('राशि', money(sale.amount)) +
        detailRow('भुगतान स्थिति', saleStatusLabel(sale.payment_status)) +
        detailRow('Interest settlement', sale.interest_settlement_status) +
        detailRow('विवरण', sale.description) +
        detailRow('Posted at', sale.posted_at ? new Date(sale.posted_at).toLocaleString('en-IN') : '—') + '</section>' +
        '<section class="mb-4"><h3 class="h4 mb-2">स्वतः बिक्री-दिवस भुगतान</h3>' +
        (automaticPayment ? detailRow('Payment Number', automaticPayment.payment_number) +
            detailRow('तारीख / राशि', date(automaticPayment.payment_date) + ' · ' + money(automaticPayment.amount)) +
            detailRow('माध्यम', paymentMethodLabel(automaticPayment.payment_method)) +
            detailRow('प्रकार', paymentTypeLabel(automaticPayment.payment_type)) : '<p class="text-secondary small">इस बिक्री के साथ स्वतः भुगतान दर्ज नहीं हुआ।</p>') + '</section>' +
        '<section class="mb-4"><h3 class="h4 mb-2">अन्य भुगतानों का Sale-wise Allocation</h3>' +
        (paymentRows ? '<div class="kk-data-table-wrap"><table class="table table-sm financial-table"><thead><tr><th>Payment</th><th>तारीख</th><th>कुल राशि</th><th>माध्यम</th><th>Component</th><th>Allocated</th></tr></thead><tbody>' + paymentRows + '</tbody></table></div>' : '<p class="text-secondary small">इस बिक्री पर अन्य भुगतान allocation उपलब्ध नहीं है।</p>') + '</section>' +
        '<section class="mb-4"><h3 class="h4 mb-2">Financial Transaction</h3>' +
        (transaction ? detailRow('Transaction ID', transaction.id) + detailRow('प्रकार', transaction.transaction_type) + detailRow('Effective date', date(transaction.effective_date)) + detailRow('Posted at', transaction.posted_at ? new Date(transaction.posted_at).toLocaleString('en-IN') : '—') : '<p class="text-secondary small">Financial transaction उपलब्ध नहीं है।</p>') + '</section>' +
        '<section class="mb-4"><h3 class="h4 mb-2">Ledger References</h3>' +
        (ledgerRows ? '<div class="kk-data-table-wrap"><table class="table table-sm financial-table"><thead><tr><th>ID</th><th>तारीख</th><th>Component</th><th>Entry</th><th>Debit</th><th>Credit</th></tr></thead><tbody>' + ledgerRows + '</tbody></table></div>' : '<p class="text-secondary small">Ledger entries उपलब्ध नहीं हैं।</p>') + '</section>' +
        '<section><h3 class="h4 mb-2">Revision History</h3>' +
        (revisionRows ? '<div class="kk-data-table-wrap"><table class="table table-sm financial-table"><thead><tr><th>Version</th><th>तारीख</th><th>किसने बदला</th><th>कारण</th><th>नया विवरण</th></tr></thead><tbody>' + revisionRows + '</tbody></table></div>' : '<p class="text-secondary small">कोई revision दर्ज नहीं है।</p>') + '</section></div>';
};
const openSaleDetails = async (saleId) => {
    if (!drawerRoot) return;
    const body = drawerRoot.querySelector('.kk-drawer-body');
    const submit = drawerRoot.querySelector('[data-financial-drawer-submit]');
    if (submit) submit.hidden = true;
    body.innerHTML = '<div class="d-flex align-items-center gap-2 text-secondary" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> बिक्री विवरण लोड हो रहा है…</div>';
    drawerRoot.dispatchEvent(new CustomEvent('kk-drawer:open', { bubbles: true, detail: { title: 'बिक्री विवरण', width: 'wide' } }));
    try {
        const payload = await request('/sales/' + encodeURIComponent(saleId));
        body.innerHTML = renderSaleDetails(payload.data || {});
    } catch (error) {
        body.innerHTML = '<div class="alert alert-danger" role="alert">' + esc(error.message || 'बिक्री विवरण लोड नहीं हो सका।') + '</div><button type="button" class="btn btn-outline-secondary" data-sale-details-retry>फिर प्रयास करें</button>';
        body.querySelector('[data-sale-details-retry]')?.addEventListener('click', () => openSaleDetails(saleId));
    }
};
const openDrawer = (kind, data = {}) => {
    if (!drawerRoot) return;
    const body = drawerRoot.querySelector('.kk-drawer-body');
    const title = kind === 'sale' ? 'नई बिक्री' : kind === 'payment' ? 'नया भुगतान' : kind === 'revise-sale' ? 'बिक्री विवरण संशोधन' : 'भुगतान सुधार';
    const submit = drawerRoot.querySelector('[data-financial-drawer-submit]');
    body.innerHTML = formMarkup(kind, data);
    if (submit) submit.hidden = false;
    if (submit) {
        submit.textContent = kind === 'correct-payment' ? 'भुगतान reverse करें' : kind === 'revise-sale' ? 'संशोधन सेव करें' : kind === 'sale' ? 'बिक्री पोस्ट करें' : 'भुगतान पोस्ट करें';
        submit.classList.toggle('btn-outline-danger', kind === 'correct-payment');
        submit.classList.toggle('btn-primary', kind !== 'correct-payment');
    }
    drawerRoot.dispatchEvent(new CustomEvent('kk-drawer:open', {
        bubbles: true,
        detail: { title, width: 'wide' },
    }));
};

document.getElementById('financial-create')?.addEventListener('click', () => openDrawer(section === 'sales' ? 'sale' : 'payment'));
document.addEventListener('click', (event) => {
    const pageButton = event.target.closest('#financial-results [data-financial-page]');
    if (!pageButton) return;

    event.preventDefault();
    const requestedPage = Number(pageButton.dataset.financialPage);
    if (!Number.isInteger(requestedPage) || requestedPage < 1 || requestedPage > lastPage || requestedPage === currentPage) return;

    currentPage = requestedPage;
    refresh({ history: 'push' });
});

root.addEventListener('click', (event) => {
    const action = event.target.closest('[data-financial-action]');
    if (action) {
        if (action.dataset.financialAction === 'view-sale') {
            openSaleDetails(action.dataset.id);
            return;
        }
        openDrawer(action.dataset.financialAction, {
            id: action.dataset.id,
            customerId: action.dataset.customerId,
            description: action.dataset.description,
            paymentNumber: action.dataset.paymentNumber,
            paymentAmount: action.dataset.paymentAmount,
            customerName: action.dataset.customerName,
        });
        return;
    }
    if (event.target.closest('[data-financial-retry]')) refresh();
});
root.addEventListener('change', (event) => {
    const size = event.target.closest('[data-financial-page-size]');
    if (!size) return;
    pageSize = Number(size.value);
    currentPage = 1;
    refresh({ history: 'push' });
});
const applyFilters = () => {
    currentPage = 1;
    refresh({ history: 'replace', skipUnchanged: true });
};
if (filtersForm) {
    const search = filtersForm.elements.namedItem('q');
    search?.addEventListener('compositionstart', () => { composing = true; window.clearTimeout(debounceTimer); });
    search?.addEventListener('compositionend', () => { composing = false; applyFilters(); });
    search?.addEventListener('input', () => {
        if (composing) return;
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(applyFilters, 375);
    });
    filtersForm.querySelectorAll('select, input[type="date"]').forEach((input) => {
        input.addEventListener('change', applyFilters);
    });
    filtersForm.addEventListener('submit', (event) => { event.preventDefault(); applyFilters(); });
    filtersForm.querySelector('[data-financial-reset]')?.addEventListener('click', () => {
        filtersForm.reset();
        const sort = filtersForm.elements.namedItem('sort');
        if (sort) sort.value = 'date_desc';
        currentPage = 1;
        applyFilters();
    });
} else {
    ['from', 'to'].forEach((name) => {
        document.getElementById('financial-' + name)?.addEventListener('change', () => {
            currentPage = 1;
            refresh({ history: 'replace', skipUnchanged: true });
        });
    });
    const actionFilter = document.getElementById('financial-action');
    actionFilter?.addEventListener('input', () => {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => {
            currentPage = 1;
            refresh({ history: 'replace', skipUnchanged: true });
        }, 375);
    });
}
document.getElementById('financial-refresh')?.addEventListener('click', () => refresh());
document.getElementById('financial-customer')?.addEventListener('change', () => { currentPage = 1; refresh({ history: 'replace' }); });
window.addEventListener('popstate', () => { readUrlState(); refresh(); });
window.addEventListener('kk-drawer:success', () => refresh());

const openInterestDrawer = (drawerId, title) => {
    const drawer = document.getElementById(drawerId)?.closest('.kk-drawer-root');
    if (!drawer) return;
    drawer.dispatchEvent(new CustomEvent('kk-drawer:open', {
        bubbles: true,
        detail: { title },
    }));
};
document.getElementById('open-shop-policy-drawer')?.addEventListener('click', () => {
    const form = document.getElementById('shop-policy-form');
    form?.reset();
    const defaults = currentShopPolicy || {
        monthly_rate_percent: '0.00000000',
        grace_days: 30,
        capitalization_enabled: false,
        payment_waterfall: 'PRINCIPAL_FIRST',
        annual_settlement_month_1: 3,
        annual_settlement_day_1: 30,
        annual_settlement_month_2: 10,
        annual_settlement_day_2: 30,
    };
    if (form) {
        [
            'monthly_rate_percent',
            'grace_days',
            'capitalization_enabled',
            'payment_waterfall',
            'annual_settlement_month_1',
            'annual_settlement_day_1',
            'annual_settlement_month_2',
            'annual_settlement_day_2',
        ].forEach((name) => {
            const field = form.elements.namedItem(name);
            const value = defaults[name];
            if (field && value !== null && value !== undefined) field.value = String(value);
        });
        const capitalization = form.elements.namedItem('capitalization_enabled');
        if (capitalization) capitalization.value = defaults.capitalization_enabled ? '1' : '0';
    }
    openInterestDrawer('shop-interest-policy-drawer', 'नई Shop Interest Policy');
});
document.getElementById('open-customer-policy-drawer')?.addEventListener('click', () => {
    const button = document.getElementById('open-customer-policy-drawer');
    if (button?.disabled) return;
    const form = document.getElementById('customer-policy-form');
    if (form) {
        form.reset();
        form.action = api + '/customers/0/interest-policy';
    }
    const history = document.getElementById('customer-policy-history');
    if (history) history.innerHTML = '';
    openInterestDrawer('customer-interest-override-drawer', 'Customer Interest Override');
});
document.getElementById('interest-customer')?.addEventListener('change', async (event) => {
    const id = event.target.value;
    const form = document.getElementById('customer-policy-form');
    if (form && id) form.action = api + '/customers/' + encodeURIComponent(id) + '/interest-policy';
    const target = document.getElementById('customer-policy-history');
    if (!id || !target) { if (target) target.innerHTML = ''; return; }
    target.innerHTML = '<div class="text-secondary small">Overrides लोड हो रहे हैं…</div>';
    try {
        const payload = await request('/customers/' + id + '/interest-policy');
        const versions = payload.data || [];
        target.innerHTML = versions.length
            ? '<h3 class="h4 mb-3">Customer override history</h3><div class="kk-data-table-wrap" role="region" aria-label="Customer override history" tabindex="0"><table class="table table-vcenter financial-table"><thead><tr><th>Version</th><th>Effective from</th><th>Effective to</th><th>Rate</th><th>Capitalization</th></tr></thead><tbody>' +
                versions.map((version) => '<tr><td>' + esc(version.version_number) + '</td><td>' + date(version.effective_from) + '</td><td>' + date(version.effective_to) + '</td><td>' + esc(version.monthly_rate_percent ?? 'Shop default') + '</td><td>' + (version.capitalization_enabled === null ? 'Shop default' : (version.capitalization_enabled ? 'चालू' : 'बंद')) + '</td></tr>').join('') +
                '</tbody></table></div>'
            : '<p class="text-secondary small mb-0">इस Customer पर कोई override नहीं है।</p>';
    } catch (error) { target.textContent = error.message; }
});
document.getElementById('projection-repair-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const id = document.getElementById('financial-customer')?.value;
    if (!id) return showMessage('पहले Customer चुनें।', 'error');
    if (!window.confirm('Projection rebuild करने से पहले/बाद का अंतर audit log में दर्ज होगा। क्या आगे बढ़ें?')) return;
    try {
        const data = Object.fromEntries(new FormData(event.currentTarget).entries());
        const result = await request('/reconciliation/customers/' + id + '/repair', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        showMessage(result.data.consistent ? 'Projection repair के बाद reconciliation consistent है।' : 'Repair के बाद भी discrepancy मौजूद है।', result.data.consistent ? 'success' : 'error');
        await refresh();
    } catch (error) { showMessage(error.message, 'error'); }
});

(async () => {
    try {
        readUrlState();
        if (['sales', 'payments', 'ledger', 'reconciliation', 'interest'].includes(section)) await loadCustomers();
        await refresh();
    } catch (error) {
        root.innerHTML = '<div class="empty-state"><h3>Workspace शुरू नहीं हो सका</h3><p>' + esc(error.message) + '</p></div>';
    }
})();
