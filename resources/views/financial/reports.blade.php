@extends('layouts.app')

@section('title', 'वित्तीय रिपोर्ट')
@section('page-title', 'वित्तीय रिपोर्ट')

@section('content')
<div class="page-heading">
    <div>
        <span class="eyebrow">KISAN KHATA · FINANCIAL REPORTS</span>
        <h1>वित्तीय रिपोर्ट सेंटर</h1>
        <p>दुकान की बिक्री, जमा, ग्राहक बकाया और ब्याज का read-only सारांश।</p>
    </div>
</div>

<div class="content-card">
    <div class="p-3 border-bottom">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label" for="report-type">रिपोर्ट</label>
                <select class="form-select" id="report-type">
                    <option value="outstanding">ग्राहक बकाया (As of)</option>
                    <option value="sales">बिक्री रजिस्टर</option>
                    <option value="payments">जमा रजिस्टर</option>
                    @if (auth()->user()->role === \App\Models\User::ROLE_OWNER)
                        <option value="interest">ब्याज रिपोर्ट (Owner)</option>
                    @endif
                </select>
            </div>
            <div class="col-md-2" id="report-as-of-wrap">
                <label class="form-label" for="report-as-of">किस तारीख तक</label>
                <input class="form-control" type="date" id="report-as-of">
            </div>
            <div class="col-md-2 d-none" id="report-from-wrap">
                <label class="form-label" for="report-from">से तारीख</label>
                <input class="form-control" type="date" id="report-from">
            </div>
            <div class="col-md-2 d-none" id="report-to-wrap">
                <label class="form-label" for="report-to">तक तारीख</label>
                <input class="form-control" type="date" id="report-to">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="report-customer">ग्राहक चुनें</label>
                <select class="form-select" id="report-customer" disabled>
                    <option value="">सभी ग्राहक</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100" id="report-load" type="button">देखें</button>
            </div>
        </div>
        <div class="row g-3 mt-1" id="report-extra-filters">
            <div class="col-md-3" id="report-search-wrap">
                <label class="form-label" for="report-search">रिकॉर्ड नंबर / Reference</label>
                <input class="form-control" id="report-search" maxlength="100" placeholder="बिक्री या जमा नंबर">
            </div>
            <div class="col-md-3 d-none" id="report-status-wrap">
                <label class="form-label" for="report-status">बिक्री स्थिति</label>
                <select class="form-select" id="report-status">
                    <option value="">सभी स्थितियाँ</option>
                    <option value="UNPAID">उधार</option>
                    <option value="PARTIALLY_PAID">आंशिक जमा</option>
                    <option value="PAID">जमा पूर्ण</option>
                </select>
            </div>
            <div class="col-md-3 d-none" id="report-method-wrap">
                <label class="form-label" for="report-method">भुगतान माध्यम</label>
                <select class="form-select" id="report-method">
                    <option value="">सभी माध्यम</option>
                    <option value="CASH">Cash</option>
                    <option value="UPI">UPI</option>
                    <option value="BANK">Bank</option>
                    <option value="CHEQUE">Cheque</option>
                </select>
            </div>
            <div class="col-md-3 d-none" id="report-payment-type-wrap">
                <label class="form-label" for="report-payment-type">भुगतान प्रकार</label>
                <select class="form-select" id="report-payment-type">
                    <option value="">सभी प्रकार</option>
                    <option value="NORMAL">सामान्य जमा</option>
                    <option value="REVERSAL">रिवर्सल</option>
                    <option value="SALE_DAY_AUTO">Sale-day auto</option>
                </select>
            </div>
        </div>
    </div>
    <div id="report-summary" class="p-3 border-bottom" aria-live="polite"></div>
    <div id="report-feedback" class="px-3 pt-3" role="status" aria-live="polite"></div>
    <div class="table-responsive" id="report-table-wrap">
        <table class="table table-vcenter card-table">
            <thead id="report-head"></thead>
            <tbody id="report-body"><tr><td class="text-secondary p-4">रिपोर्ट देखने के लिए फ़िल्टर चुनें।</td></tr></tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center p-3 border-top">
        <span class="text-secondary small" id="report-page-info"></span>
        <div class="btn-list">
            <button class="btn btn-outline-secondary btn-sm" id="report-prev" type="button" disabled>पिछला</button>
            <button class="btn btn-outline-secondary btn-sm" id="report-next" type="button" disabled>अगला</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const api = @json(url('/api/v1/reports'));
    const customersApi = @json(url('/api/v1/customers'));
    const role = @json(auth()->user()->role);
    const $ = (id) => document.getElementById(id);
    const state = { page: 1, lastPage: 1, report: 'outstanding' };
    const money = (value) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(value || 0));
    const safe = (value) => String(value ?? '—');
    const cell = (value) => '<td>' + safe(value).replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch])) + '</td>';
    const show = (id, yes) => $(id).classList.toggle('d-none', !yes);

    function configure() {
        const report = $('report-type').value;
        state.report = report;
        show('report-as-of-wrap', report === 'outstanding');
        show('report-from-wrap', report !== 'outstanding');
        show('report-to-wrap', report !== 'outstanding');
        show('report-status-wrap', report === 'sales');
        show('report-method-wrap', report === 'payments');
        show('report-payment-type-wrap', report === 'payments');
        if (report === 'interest' && role !== 'owner') $('report-type').value = 'outstanding';
    }

    function params() {
        const query = new URLSearchParams({ page: String(state.page), per_page: '20' });
        const report = state.report;
        if (report === 'outstanding') {
            if (!$('report-as-of').value) throw new Error('कृपया As-of तारीख चुनें।');
            query.set('as_of', $('report-as-of').value);
        } else {
            if ($('report-from').value) query.set('from', $('report-from').value);
            if ($('report-to').value) query.set('to', $('report-to').value);
        }
        if ($('report-customer').value) query.set('customer_id', $('report-customer').value);
        if ($('report-search').value.trim() && report !== 'outstanding') query.set('q', $('report-search').value.trim());
        if (report === 'sales' && $('report-status').value) query.set('payment_status', $('report-status').value);
        if (report === 'payments') {
            if ($('report-method').value) query.set('payment_method', $('report-method').value);
            if ($('report-payment-type').value) query.set('payment_type', $('report-payment-type').value);
        }
        return query;
    }

    async function loadCustomers() {
        const select = $('report-customer');
        select.disabled = true;
        select.innerHTML = '<option value="">ग्राहक सूची लोड हो रही है…</option>';

        const customers = [];
        let page = 1;
        let lastPage = 1;

        do {
            const response = await fetch(customersApi + '?status=financial-history&pageSize=100&page=' + page, {
                headers: { Accept: 'application/json' }, credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || 'ग्राहक सूची लोड नहीं हो सकी।');
            customers.push(...(payload.data || []));
            lastPage = Number(payload.meta?.last_page || 1);
            page++;
        } while (page <= lastPage);

        select.innerHTML = '<option value="">सभी ग्राहक</option>';
        customers.forEach((customer) => {
            const name = customer.name_hi || customer.name_original || customer.name_en || 'ग्राहक';
            const details = [
                customer.customer_code ? 'Code: ' + customer.customer_code : null,
                customer.mobile ? 'मोबाइल: ' + customer.mobile : null,
                !customer.customer_code && !customer.mobile ? 'ID: ' + customer.id : null,
            ].filter(Boolean);
            const option = new Option(name + ' — ' + details.join(' · '), String(customer.id));
            select.add(option);
        });
        select.disabled = false;
    }

    function summaryCards(summary) {
        const entries = Object.entries(summary || {});
        $('report-summary').innerHTML = entries.map(([key, value]) =>
            '<div class="d-inline-block me-4 mb-2"><div class="text-secondary small">' +
            key.replaceAll('_', ' ') + '</div><strong>' +
            (typeof value === 'string' && /^-?\d+\.\d{2}$/.test(value) ? money(value) : safe(value)) +
            '</strong></div>'
        ).join('') || '<span class="text-secondary">कोई सारांश उपलब्ध नहीं।</span>';
    }

    function render(payload) {
        const report = state.report;
        let columns = [];
        let rows = payload.data || [];
        if (report === 'outstanding') {
            columns = ['ग्राहक', 'स्थिति', 'Principal', 'Interest', 'कुल'];
            $('report-head').innerHTML = '<tr>' + columns.map((x) => '<th>' + x + '</th>').join('') + '</tr>';
            $('report-body').innerHTML = rows.map((r) => '<tr>' +
                cell(r.name_hi || r.name_original || r.name_en) + cell(r.deleted_at ? 'हटाया गया' : r.status) +
                cell(money(r.principal)) + cell(money(r.interest)) + cell(money(r.total)) + '</tr>').join('') || '<tr><td colspan="5" class="p-4 text-secondary">इस तारीख तक कोई financial history नहीं मिली।</td></tr>';
        } else if (report === 'sales') {
            columns = ['बिक्री नंबर', 'तारीख', 'ग्राहक', 'स्थिति', 'राशि'];
            $('report-head').innerHTML = '<tr>' + columns.map((x) => '<th>' + x + '</th>').join('') + '</tr>';
            $('report-body').innerHTML = rows.map((r) => '<tr>' + cell(r.sale_number) + cell(r.sale_date) +
                cell(r.customer_name_hi || r.customer_name || r.customer_name_en) + cell(r.payment_status) + cell(money(r.amount)) + '</tr>').join('') || '<tr><td colspan="5" class="p-4 text-secondary">कोई बिक्री रिकॉर्ड नहीं मिला।</td></tr>';
        } else if (report === 'payments') {
            columns = ['जमा नंबर', 'तारीख', 'ग्राहक', 'प्रकार', 'माध्यम', 'राशि', 'लिंक'];
            $('report-head').innerHTML = '<tr>' + columns.map((x) => '<th>' + x + '</th>').join('') + '</tr>';
            $('report-body').innerHTML = rows.map((r) => '<tr>' + cell(r.payment_number) + cell(r.payment_date) +
                cell(r.customer_name_hi || r.customer_name || r.customer_name_en) + cell(r.payment_type) +
                cell(r.payment_method) + cell(money(r.amount)) + cell(r.reversed_payment_number || r.replacement_for_payment_number) + '</tr>').join('') || '<tr><td colspan="7" class="p-4 text-secondary">कोई जमा रिकॉर्ड नहीं मिला।</td></tr>';
        } else {
            columns = ['ग्राहक', 'बिक्री', 'Trigger', 'Run', 'Cutoff (exclusive)', 'Interval', 'Days', 'Rate %', 'Principal basis', 'Posted interest', 'Ledger ID'];
            $('report-head').innerHTML = '<tr>' + columns.map((x) => '<th>' + x + '</th>').join('') + '</tr>';
            $('report-body').innerHTML = rows.map((r) => '<tr>' + cell(r.customer_name_hi || r.customer_name || r.customer_name_en) +
                cell(r.sale_number) + cell(r.trigger_type) + cell(r.run_status) + cell(r.cutoff_date_exclusive) +
                cell(r.interval_start_date + ' – ' + r.interval_end_date_exclusive) + cell(r.actual_days) +
                cell(r.monthly_rate_percent) + cell(money(r.eligible_principal_basis)) + cell(money(r.posted_interest)) + cell(r.ledger_entry_id) + '</tr>').join('') ||
                '<tr><td colspan="11" class="p-4 text-secondary">कोई completed interest calculation नहीं मिली।</td></tr>';
        }
        summaryCards(payload.summary);
        state.lastPage = payload.meta?.last_page || 1;
        $('report-page-info').textContent = 'कुल रिकॉर्ड: ' + (payload.meta?.total || 0) + ' · पेज ' + (payload.meta?.current_page || 1) + ' / ' + state.lastPage;
        $('report-prev').disabled = state.page <= 1;
        $('report-next').disabled = state.page >= state.lastPage;
    }

    async function load() {
        configure();
        $('report-feedback').textContent = 'रिपोर्ट लोड हो रही है…';
        $('report-load').disabled = true;
        try {
            const query = params();
            const response = await fetch(api + '/' + state.report + '?' + query.toString(), {
                headers: { Accept: 'application/json' }, credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || 'रिपोर्ट लोड नहीं हो सकी।');
            render(payload);
            $('report-feedback').textContent = '';
        } catch (error) {
            $('report-feedback').textContent = error.message;
            $('report-body').innerHTML = '';
            $('report-summary').innerHTML = '';
        } finally {
            $('report-load').disabled = false;
        }
    }

    $('report-customer').addEventListener('change', () => { state.page = 1; });
    $('report-type').addEventListener('change', () => { state.page = 1; configure(); });
    $('report-load').addEventListener('click', () => { state.page = 1; load(); });
    $('report-prev').addEventListener('click', () => { if (state.page > 1) { state.page--; load(); } });
    $('report-next').addEventListener('click', () => { if (state.page < state.lastPage) { state.page++; load(); } });
    loadCustomers().then(configure).catch((error) => {
        $('report-feedback').textContent = error.message;
        $('report-customer').innerHTML = '<option value="">ग्राहक सूची उपलब्ध नहीं</option>';
    });
})();
</script>
@endpush
