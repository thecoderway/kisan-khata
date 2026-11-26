const config = window.KKFinancialWorkspace;
const root = document.getElementById('financial-results');
const feedback = document.getElementById('financial-feedback');
const section = config?.section;
const api = config?.api;
const csrf = config?.csrf;
let customers = [];
let currentPage = 1;

const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
}[char]));
const money = (value) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(value || 0));
const date = (value) => value ? new Date(value).toLocaleDateString('en-IN') : '—';
const showMessage = (message, kind = 'success') => {
    feedback.innerHTML = '<div class="alert alert-' + (kind === 'error' ? 'danger' : 'success') + '" role="status">' + esc(message) + '</div>';
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
const queryString = () => {
    const params = new URLSearchParams();
    params.set('page', String(currentPage));
    params.set('per_page', '100');
    const from = document.getElementById('financial-from')?.value;
    const to = document.getElementById('financial-to')?.value;
    if (from) params.set('from', from);
    if (to) params.set('to', to);
    if (section === 'audit') {
        const action = document.getElementById('financial-action')?.value.trim();
        if (action) params.set('action', action);
    }
    return params.toString() ? '?' + params.toString() : '';
};
const renderTable = (headers, rows) => {
    if (!rows.length) {
        root.innerHTML = '<div class="empty-state"><i class="ti ti-inbox"></i><h3>कोई रिकॉर्ड नहीं मिला</h3><p>चुने गए filter के अनुसार कोई डेटा उपलब्ध नहीं है।</p></div>';
        return;
    }
    root.innerHTML = '<table class="table table-vcenter financial-table"><thead><tr>' + headers.map(x => '<th>' + esc(x) + '</th>').join('') + '</tr></thead><tbody>' +
        rows.map(row => '<tr>' + row.map(cell => '<td>' + cell + '</td>').join('') + '</tr>').join('') + '</tbody></table>';
};
const loadCustomers = async () => {
    const [activePayload, inactivePayload] = await Promise.all([
        request('/customers?pageSize=100'),
        request('/customers?status=inactive&pageSize=100'),
    ]);
    customers = [...(activePayload.data || []), ...(inactivePayload.data || [])];
    ['financial-customer', 'interest-customer'].forEach(id => {
        const select = document.getElementById(id);
        if (!select) return;
        select.innerHTML = '<option value="">ग्राहक चुनें</option>' + customers.map(c =>
            '<option value="' + esc(c.id) + '">' + esc(c.name_original || c.name_hi || c.name_en || ('Customer #' + c.id)) + '</option>'
        ).join('');
    });
};

const renderPagination = (meta = {}) => {
    const page = Number(meta.current_page || meta.page || 1);
    const perPage = Number(meta.per_page || 100);
    const total = Number(meta.total || 0);
    const lastPage = Number(meta.last_page || Math.max(1, Math.ceil(total / perPage)));
    if (lastPage <= 1) return;
    root.insertAdjacentHTML('beforeend',
        '<div class="financial-pagination"><span>कुल ' + total + ' रिकॉर्ड · पेज ' + page + ' / ' + lastPage + '</span><div class="d-flex gap-2">' +
        '<button class="btn btn-sm btn-outline-secondary" type="button" data-financial-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>पिछला</button>' +
        '<button class="btn btn-sm btn-outline-secondary" type="button" data-financial-page="' + (page + 1) + '"' + (page >= lastPage ? ' disabled' : '') + '>अगला</button>' +
        '</div></div>'
    );
};

const loadSales = async () => {
    const payload = await request('/sales' + queryString());
    renderTable(['बिक्री नंबर', 'ग्राहक', 'तारीख', 'राशि', 'स्थिति', 'कार्रवाई'], (payload.data || []).map(s => [
        '<strong>' + esc(s.sale_number) + '</strong>',
        esc(s.customer?.name_original || s.customer?.name_hi || '—'),
        date(s.sale_date), money(s.amount),
        '<span class="role-pill">' + esc(s.payment_status) + '</span>',
        config.role === 'owner' ? '<button class="btn btn-sm btn-outline-secondary" data-revise-sale="' + esc(s.id) + '" data-current-description="' + esc(s.description || '') + '">विवरण संशोधन</button>' : '—',
    ]));
    renderPagination(payload.meta);
};
const loadPayments = async () => {
    const payload = await request('/payments' + queryString());
    renderTable(['भुगतान नंबर', 'ग्राहक', 'तारीख', 'राशि', 'प्रकार', 'कार्रवाई'], (payload.data || []).map(p => [
        '<strong>' + esc(p.payment_number) + '</strong>',
        esc(p.customer?.name_original || p.customer?.name_hi || '—'),
        date(p.payment_date), money(p.amount),
        '<span class="role-pill">' + esc(p.payment_type) + '</span>',
        config.role === 'owner' && p.payment_type === 'NORMAL' ? '<button class="btn btn-sm btn-outline-danger" data-correct-payment="' + esc(p.id) + '" data-customer-id="' + esc(p.customer_id) + '">रिवर्स / बदलें</button>' : '—',
    ]));
    renderPagination(payload.meta);
};
const loadLedger = async () => {
    const id = document.getElementById('financial-customer')?.value;
    if (!id) { root.innerHTML = '<div class="empty-state"><i class="ti ti-user-search"></i><h3>ग्राहक चुनें</h3><p>उस ग्राहक की खाता-बही देखने के लिए ऊपर से चयन करें।</p></div>'; return; }
    const payload = await request('/customers/' + id + '/ledger' + queryString());
    const rows = (payload.data || []).map(e => [
        date(e.effective_date), esc(e.entry_kind), esc(e.component),
        money(e.debit), money(e.credit), money(e.total_balance),
    ]);
    renderTable(['तारीख', 'लेन-देन', 'खाता', 'Debit', 'Credit', 'बाकी'], rows);
    const opening = payload.meta?.opening_balance;
    if (opening) showMessage('Opening balance — Principal: ' + money(opening.principal) + ' · Interest: ' + money(opening.interest) + ' · Total: ' + money(opening.total));
    renderPagination(payload.meta);
};
const loadInterest = async () => {
    const payload = await request('/interest-policy');
    renderTable(['Version', 'लागू तारीख', 'मासिक दर', 'Grace', 'Capitalization', 'Waterfall', 'Settlement dates'],
        (payload.data || []).map(v => [
            esc(v.version_number), date(v.effective_from), esc(v.monthly_rate_percent) + '%',
            esc(v.grace_days) + ' दिन', v.capitalization_enabled ? 'चालू' : 'बंद',
            esc(v.payment_waterfall),
            esc(v.annual_settlement_month_1 + '/' + v.annual_settlement_day_1 + ' · ' + v.annual_settlement_month_2 + '/' + v.annual_settlement_day_2),
        ]));
};
const loadReconciliation = async () => {
    const id = document.getElementById('financial-customer')?.value;
    if (!id) { root.innerHTML = '<div class="empty-state"><i class="ti ti-user-search"></i><h3>ग्राहक चुनें</h3><p>Projection reconciliation के लिए Customer चुनें।</p></div>'; return; }
    const payload = await request('/reconciliation/customers/' + id);
    const result = payload.data;
    showMessage(result.consistent ? 'Ledger और projection में कोई अंतर नहीं मिला।' : result.discrepancies.length + ' discrepancy मिली।', result.consistent ? 'success' : 'error');
    renderTable(['स्थिति', 'Field / तारीख', 'Expected', 'Actual'], (result.discrepancies || []).map(d => [
        '<span class="role-pill">' + esc(d.type) + '</span>',
        esc(d.field || d.as_of_date || d.sale_id || d.payment_id || '—'),
        esc(JSON.stringify(d.expected ?? '—')), esc(JSON.stringify(d.actual ?? '—')),
    ]));
};
const loadAudit = async () => {
    const payload = await request('/financial-audit' + queryString());
    renderTable(['समय', 'User', 'Action', 'Entity', 'Reason'], (payload.data || []).map(a => [
        date(a.created_at), esc(a.user?.name || 'System'), esc(a.action),
        esc(a.entity_type + ' #' + a.entity_id), esc(a.reason || '—'),
    ]));
    renderPagination(payload.meta);
};
const loaders = { sales: loadSales, payments: loadPayments, ledger: loadLedger, interest: loadInterest, reconciliation: loadReconciliation, audit: loadAudit };
const refresh = async () => {
    root.innerHTML = '<div class="empty-state"><i class="ti ti-loader-2"></i><h3>लोड हो रहा है…</h3></div>';
    try { await loaders[section](); } catch (error) { root.innerHTML = '<div class="empty-state"><i class="ti ti-alert-circle"></i><h3>डेटा लोड नहीं हुआ</h3><p>' + esc(error.message) + '</p></div>'; }
};
const formMarkup = () => {
    if (section === 'sales') return '<form id="financial-entry-form" class="financial-form-grid p-3"><div><label class="form-label">ग्राहक</label><select class="form-select" name="customer_id" required><option value="">ग्राहक चुनें</option>' + customerOptions() + '</select></div><div><label class="form-label">बिक्री तारीख</label><input class="form-control" type="date" name="sale_date" required></div><div><label class="form-label">राशि (₹)</label><input class="form-control" type="number" min="0.01" step="0.01" name="amount" required></div><div><label class="form-label">भुगतान स्थिति</label><select class="form-select" name="payment_choice"><option value="UNPAID">उधार</option><option value="PAID">नकद / Paid</option></select></div><div class="financial-form-wide"><label class="form-label">विवरण</label><input class="form-control" name="description" maxlength="5000"></div><div class="financial-form-submit"><button class="btn btn-primary kk-primary">बिक्री पोस्ट करें</button></div></form>';
    return '<form id="financial-entry-form" class="financial-form-grid p-3"><div><label class="form-label">ग्राहक</label><select class="form-select" name="customer_id" required><option value="">ग्राहक चुनें</option>' + customerOptions() + '</select></div><div><label class="form-label">भुगतान तारीख</label><input class="form-control" type="date" name="payment_date" required></div><div><label class="form-label">राशि (₹)</label><input class="form-control" type="number" min="0.01" step="0.01" name="amount" required></div><div><label class="form-label">माध्यम</label><select class="form-select" name="payment_method"><option value="">चुनें</option><option>Cash</option><option>UPI</option><option>Bank</option><option>Cheque</option></select></div><div><label class="form-label">Reference number</label><input class="form-control" name="reference_number" maxlength="128"></div><div class="financial-form-wide"><label class="form-label">विवरण</label><input class="form-control" name="description" maxlength="5000"></div><div class="financial-form-submit"><button class="btn btn-primary kk-primary">भुगतान पोस्ट करें</button></div></form>';
};
const customerOptions = () => customers.map(c => '<option value="' + esc(c.id) + '">' + esc(c.name_original || c.name_hi || c.name_en || ('Customer #' + c.id)) + '</option>').join('');

root.addEventListener('click', (event) => {
    const revise = event.target.closest('[data-revise-sale]');
    const correct = event.target.closest('[data-correct-payment]');
    if (!revise && !correct) return;
    document.getElementById('financial-correction-card')?.remove();
    const card = document.createElement('div');
    card.id = 'financial-correction-card';
    card.className = 'content-card mb-3';
    if (revise) {
        card.innerHTML = '<div class="content-card-header"><div><h2>बिक्री विवरण संशोधन</h2><p>केवल विवरण बदलेगा; तारीख और राशि immutable हैं।</p></div></div><form class="financial-form-grid p-3" id="sale-revision-form"><div class="financial-form-wide"><label class="form-label">नया विवरण</label><textarea class="form-control" name="description" rows="2" maxlength="5000">' + esc(revise.dataset.currentDescription) + '</textarea></div><div class="financial-form-wide"><label class="form-label">संशोधन का कारण</label><input class="form-control" name="reason" required maxlength="500"></div><div class="financial-form-submit"><button class="btn btn-primary kk-primary">संशोधन दर्ज करें</button></div></form>';
        document.querySelector('.page-heading').after(card);
        card.querySelector('form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.currentTarget;
            if (form.dataset.submitting === 'true') return;
            form.dataset.submitting = 'true';
            const submit = form.querySelector('button[type="submit"]');
            if (submit) submit.disabled = true;
            const data = Object.fromEntries(new FormData(form).entries());
            const key = form.dataset.idempotencyKey || (form.dataset.idempotencyKey = crypto.randomUUID());
            try {
                await request('/sales/' + revise.dataset.reviseSale + '/revision', {
                    method: 'POST', headers: { 'Content-Type': 'application/json', 'Idempotency-Key': key }, body: JSON.stringify(data),
                });
                delete form.dataset.idempotencyKey;
                showMessage('Sale revision audit के साथ दर्ज हुई।');
                card.remove(); await refresh();
            } catch (error) { showMessage(error.message, 'error'); }
            finally { delete form.dataset.submitting; if (submit) submit.disabled = false; }
        });
    } else {
        card.innerHTML = '<div class="content-card-header"><div><h2>भुगतान सुधार</h2><p>मूल Payment reverse होगा। Replacement वैकल्पिक है।</p></div></div><form class="financial-form-grid p-3" id="payment-correction-form"><div class="financial-form-wide"><label class="form-label">कारण</label><input class="form-control" name="reason" required maxlength="500"></div><div><label class="form-label">Replacement राशि (₹) — वैकल्पिक</label><input class="form-control" type="number" min="0.01" step="0.01" name="replacement_amount"></div><div><label class="form-label">Replacement माध्यम</label><select class="form-select" name="replacement_method"><option value="">चुनें</option><option>Cash</option><option>UPI</option><option>Bank</option><option>Cheque</option></select></div><div class="financial-form-submit"><button class="btn btn-outline-danger">Payment reverse करें</button></div></form>';
        document.querySelector('.page-heading').after(card);
        card.querySelector('form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.currentTarget;
            if (form.dataset.submitting === 'true') return;
            form.dataset.submitting = 'true';
            const submit = form.querySelector('button[type="submit"]');
            if (submit) submit.disabled = true;
            const formData = Object.fromEntries(new FormData(form).entries());
            const data = { reason: formData.reason };
            const key = form.dataset.idempotencyKey || (form.dataset.idempotencyKey = crypto.randomUUID());
            if (formData.replacement_amount) {
                data.replacement = { amount: formData.replacement_amount, payment_method: formData.replacement_method || null };
            }
            try {
                await request('/customers/' + correct.dataset.customerId + '/payments/' + correct.dataset.correctPayment + '/correction', {
                    method: 'POST', headers: { 'Content-Type': 'application/json', 'Idempotency-Key': key }, body: JSON.stringify(data),
                });
                delete form.dataset.idempotencyKey;
                showMessage('Payment correction audit के साथ दर्ज हुआ।');
                card.remove(); await refresh();
            } catch (error) { showMessage(error.message, 'error'); }
            finally { delete form.dataset.submitting; if (submit) submit.disabled = false; }
        });
    }
});

document.getElementById('financial-create')?.addEventListener('click', () => {
    const existing = document.getElementById('financial-entry-card');
    if (existing) { existing.remove(); return; }
    const card = document.createElement('div');
    card.id = 'financial-entry-card';
    card.className = 'content-card mb-3';
    card.innerHTML = '<div class="content-card-header"><div><h2>' + (section === 'sales' ? 'नई बिक्री' : 'नया भुगतान') + '</h2><p>रिकॉर्ड post होने के बाद financial ledger में append-only entry बनेगी।</p></div><button class="btn btn-sm btn-outline-secondary" type="button" id="financial-entry-close">बंद करें</button></div>' + formMarkup();
    document.querySelector('.page-heading').after(card);
    card.querySelector('#financial-entry-close').addEventListener('click', () => card.remove());
    card.querySelector('form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        if (form.dataset.submitting === 'true') return;
        form.dataset.submitting = 'true';
        const submit = form.querySelector('button[type="submit"]');
        if (submit) submit.disabled = true;
        const data = Object.fromEntries(new FormData(form).entries());
        const key = form.dataset.idempotencyKey || (form.dataset.idempotencyKey = crypto.randomUUID());
        try {
            await request(section === 'sales' ? '/sales' : '/payments', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Idempotency-Key': key },
                body: JSON.stringify(data),
            });
            delete form.dataset.idempotencyKey;
            showMessage(section === 'sales' ? 'बिक्री सफलतापूर्वक पोस्ट हुई।' : 'भुगतान सफलतापूर्वक पोस्ट हुआ।');
            card.remove();
            await refresh();
        } catch (error) { showMessage(error.message, 'error'); }
        finally { delete form.dataset.submitting; if (submit) submit.disabled = false; }
    });
});
document.getElementById('financial-refresh')?.addEventListener('click', () => { currentPage = 1; refresh(); });
document.getElementById('financial-customer')?.addEventListener('change', () => { currentPage = 1; refresh(); });
document.getElementById('financial-from')?.addEventListener('change', () => { currentPage = 1; });
document.getElementById('financial-to')?.addEventListener('change', () => { currentPage = 1; });
root.addEventListener('click', (event) => {
    const pageButton = event.target.closest('[data-financial-page]');
    if (!pageButton || pageButton.disabled) return;
    currentPage = Number(pageButton.dataset.financialPage);
    refresh();
});

document.getElementById('shop-policy-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.currentTarget).entries());
    data.capitalization_enabled = data.capitalization_enabled === '1';
    try {
        await request('/interest-policy', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        showMessage('नई Shop policy version सेव हो गई।');
        event.currentTarget.reset();
        await refresh();
    } catch (error) { showMessage(error.message, 'error'); }
});
document.getElementById('customer-policy-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.currentTarget).entries());
    const id = data.customer_id;
    delete data.customer_id;
    if (data.capitalization_enabled === '') delete data.capitalization_enabled;
    else data.capitalization_enabled = data.capitalization_enabled === '1';
    if (data.monthly_rate_percent === '') delete data.monthly_rate_percent;
    try {
        await request('/customers/' + id + '/interest-policy', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        showMessage('Customer interest override सेव हो गया।');
        event.currentTarget.reset();
    } catch (error) { showMessage(error.message, 'error'); }
});

document.getElementById('interest-customer')?.addEventListener('change', async (event) => {
    const id = event.target.value;
    const target = document.getElementById('customer-policy-history');
    if (!id || !target) { if (target) target.innerHTML = ''; return; }
    target.innerHTML = '<div class="text-secondary small">Overrides लोड हो रहे हैं…</div>';
    try {
        const payload = await request('/customers/' + id + '/interest-policy');
        const versions = payload.data || [];
        target.innerHTML = versions.length
            ? '<h3 class="h4 mb-3">Customer override history</h3><div class="table-responsive"><table class="table table-vcenter financial-table"><thead><tr><th>Version</th><th>Effective from</th><th>Effective to</th><th>Rate</th><th>Capitalization</th></tr></thead><tbody>' +
                versions.map(v => '<tr><td>' + esc(v.version_number) + '</td><td>' + date(v.effective_from) + '</td><td>' + date(v.effective_to) + '</td><td>' + esc(v.monthly_rate_percent ?? 'Shop default') + '</td><td>' + (v.capitalization_enabled === null ? 'Shop default' : (v.capitalization_enabled ? 'चालू' : 'बंद')) + '</td></tr>').join('') +
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
        if (['ledger', 'reconciliation', 'interest'].includes(section)) await loadCustomers();
        await refresh();
    } catch (error) {
        root.innerHTML = '<div class="empty-state"><h3>Workspace शुरू नहीं हो सका</h3><p>' + esc(error.message) + '</p></div>';
    }
})();
