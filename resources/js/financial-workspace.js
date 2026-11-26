const config = window.KKFinancialWorkspace;
const root = document.getElementById('financial-results');
const feedback = document.getElementById('financial-feedback');
const section = config?.section;
const api = config?.api;
const csrf = config?.csrf;
let customers = [];

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
    const payload = await request('/customers?per_page=100');
    customers = payload.data || [];
    ['financial-customer', 'interest-customer'].forEach(id => {
        const select = document.getElementById(id);
        if (!select) return;
        select.innerHTML = '<option value="">ग्राहक चुनें</option>' + customers.map(c =>
            '<option value="' + esc(c.id) + '">' + esc(c.name_original || c.name_hi || c.name_en || ('Customer #' + c.id)) + '</option>'
        ).join('');
    });
};
const loadSales = async () => {
    const payload = await request('/sales' + queryString());
    renderTable(['बिक्री नंबर', 'ग्राहक', 'तारीख', 'राशि', 'स्थिति'], (payload.data || []).map(s => [
        '<strong>' + esc(s.sale_number) + '</strong>',
        esc(s.customer?.name_original || s.customer?.name_hi || '—'),
        date(s.sale_date), money(s.amount),
        '<span class="role-pill">' + esc(s.payment_status) + '</span>',
    ]));
};
const loadPayments = async () => {
    const payload = await request('/payments' + queryString());
    renderTable(['भुगतान नंबर', 'ग्राहक', 'तारीख', 'राशि', 'प्रकार'], (payload.data || []).map(p => [
        '<strong>' + esc(p.payment_number) + '</strong>',
        esc(p.customer?.name_original || p.customer?.name_hi || '—'),
        date(p.payment_date), money(p.amount),
        '<span class="role-pill">' + esc(p.payment_type) + '</span>',
    ]));
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
        const data = Object.fromEntries(new FormData(form).entries());
        const key = crypto.randomUUID();
        try {
            await request(section === 'sales' ? '/sales' : '/payments', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Idempotency-Key': key },
                body: JSON.stringify(data),
            });
            showMessage(section === 'sales' ? 'बिक्री सफलतापूर्वक पोस्ट हुई।' : 'भुगतान सफलतापूर्वक पोस्ट हुआ।');
            card.remove();
            await refresh();
        } catch (error) { showMessage(error.message, 'error'); }
    });
});
document.getElementById('financial-refresh')?.addEventListener('click', refresh);
document.getElementById('financial-customer')?.addEventListener('change', refresh);

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
