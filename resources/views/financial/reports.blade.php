@extends('layouts.app')

@section('title', 'वित्तीय रिपोर्ट')
@section('page-title', 'वित्तीय रिपोर्ट')

@section('content')
<div class="page-heading">
    <div>
        <span class="eyebrow">KISAN KHATA · वित्तीय रिपोर्ट</span>
        <h1>वित्तीय रिपोर्ट सेंटर</h1>
        <p>दुकान की बिक्री, जमा, ग्राहक बकाया और लेट फीस का केवल देखने हेतु सारांश।</p>
    </div>
</div>

<div class="content-card">
    <div class="p-3 border-bottom">
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label" for="report-type">रिपोर्ट</label>
                <select class="form-select" id="report-type">
                    <option value="outstanding">ग्राहक बकाया (इस तारीख तक)</option>
                    <option value="sales">बिक्री रजिस्टर</option>
                    <option value="payments">जमा रजिस्टर</option>
                    @if (auth()->user()->role === \App\Models\User::ROLE_OWNER)
                        <option value="interest">लेट फीस रिपोर्ट (Owner)</option>
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
            <div class="col-md-3">
                <label class="form-label" for="report-village">गाँव</label>
                <select class="form-select" id="report-village">
                    <option value="">सभी गाँव</option>
                    @foreach ($villages as $village)
                        <option value="{{ $village->villageCode }}">{{ collect([$village->villageNameLocal ?: $village->villageNameEnglish, $village->subdistrictNameLocal ?: $village->subdistrictNameEnglish, $village->districtNameLocal ?: $village->districtNameEnglish])->filter()->implode(', ') }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3" id="report-search-wrap">
                <label class="form-label" for="report-search">रिकॉर्ड नंबर / संदर्भ</label>
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
                    <option value="CASH">नकद</option>
                    <option value="यूपीआई">यूपीआई</option>
                    <option value="BANK">बैंक</option>
                    <option value="CHEQUE">चेक</option>
                </select>
            </div>
            <div class="col-md-3 d-none" id="report-payment-type-wrap">
                <label class="form-label" for="report-payment-type">भुगतान प्रकार</label>
                <select class="form-select" id="report-payment-type">
                    <option value="">सभी प्रकार</option>
                    <option value="NORMAL">सामान्य जमा</option>
                    <option value="REVERSAL">रिवर्सल</option>
                    <option value="SALE_DAY_AUTO">बिक्री के दिन स्वतः जमा</option>
                </select>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 p-3 border-bottom">
        <button class="btn btn-outline-success" id="report-export" type="button"
            data-api="{{ url('/api/v1/reports') }}" data-shop-name="{{ auth()->user()->shop?->name }}" data-user-role="{{ auth()->user()->role }}" disabled>
            <i class="ti ti-file-spreadsheet me-1" aria-hidden="true"></i> तालिका डाउनलोड
        </button>
        <button class="btn btn-outline-secondary" id="report-print" type="button"
            data-api="{{ url('/api/v1/reports') }}" data-shop-name="{{ auth()->user()->shop?->name }}" disabled>
            <i class="ti ti-printer me-1" aria-hidden="true"></i> प्रिंट / पीडीएफ
        </button>
    </div>
    <div id="report-summary" class="p-3 border-bottom" aria-live="polite"></div>
    <div id="report-feedback" class="px-3 pt-3" role="status" aria-live="polite"></div>
    <div class="table-responsive" id="report-table-wrap">
        <table class="table table-vcenter card-table">
            <thead id="report-head"></thead>
            <tbody id="report-body"><tr><td class="text-secondary p-4">रिपोर्ट देखने के लिए फ़िल्टर चुनें।</td></tr></tbody>
        </table>
    </div>
    <div id="report-pagination" class="financial-pagination customer-pagination-wrap d-flex flex-wrap align-items-center justify-content-between gap-3">
        <span class="customer-pagination-summary text-secondary small" id="report-page-info" aria-live="polite">कुल 0 में से 0–0 रिकॉर्ड</span>
        <div class="customer-pagination-controls d-flex flex-wrap align-items-center gap-3">
            <label class="customer-page-size d-flex align-items-center gap-2 text-secondary small">
                <span>प्रति पृष्ठ पंक्तियाँ</span>
                <select class="form-select form-select-sm" id="report-page-size" aria-label="प्रति पृष्ठ पंक्तियाँ">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
            <nav aria-label="Report pagination">
                <ul class="pagination mb-0" id="report-page-links"></ul>
            </nav>
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
    const state = { page: 1, perPage: 20, lastPage: 1, report: 'outstanding' };
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
        const query = new URLSearchParams({ page: String(state.page), per_page: String(state.perPage) });
        const report = state.report;
        if (report === 'outstanding') {
            if (!$('report-as-of').value) throw new Error('कृपया As-of तारीख चुनें।');
            query.set('as_of', $('report-as-of').value);
        } else {
            if ($('report-from').value) query.set('from', $('report-from').value);
            if ($('report-to').value) query.set('to', $('report-to').value);
        }
        if ($('report-customer').value) query.set('customer_id', $('report-customer').value);
        if ($('report-village').value) query.set('village_code', $('report-village').value);
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
                'ID: ' + customer.id,
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
            key.replaceAll('_', ' ').replace(/interest/gi, 'लेट फीस') + '</div><strong>' +
            (typeof value === 'string' && /^-?\d+\.\d{2}$/.test(value) ? money(value) : safe(value)) +
            '</strong></div>'
        ).join('') || '<span class="text-secondary">कोई सारांश उपलब्ध नहीं।</span>';
    }

    function render(payload) {
        const report = state.report;
        let columns = [];
        let rows = payload.data || [];
        if (report === 'outstanding') {
            columns = ['ग्राहक', 'स्थिति', 'मूलधन', 'लेट फीस', 'कुल'];
            $('report-head').innerHTML = '<tr>' + columns.map((x) => '<th>' + x + '</th>').join('') + '</tr>';
            $('report-body').innerHTML = rows.map((r) => '<tr>' +
                cell(r.name_hi || r.name_original || r.name_en) + cell(r.deleted_at ? 'हटाया गया' : r.status) +
                cell(money(r.principal)) + cell(money(r.interest)) + cell(money(r.total)) + '</tr>').join('') || '<tr><td colspan="5" class="p-4 text-secondary">इस तारीख तक कोई वित्तीय इतिहास नहीं मिली।</td></tr>';
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
            columns = ['ग्राहक', 'बिक्री', 'कारण', 'गणना', 'अंतिम तिथि (शामिल नहीं)', 'अवधि', 'दिन', 'दर %', 'मूलधन आधार', 'Posted लेट फीस', 'खाता-बही क्रमांक'];
            $('report-head').innerHTML = '<tr>' + columns.map((x) => '<th>' + x + '</th>').join('') + '</tr>';
            $('report-body').innerHTML = rows.map((r) => '<tr>' + cell(r.customer_name_hi || r.customer_name || r.customer_name_en) +
                cell(r.sale_number) + cell(r.trigger_type) + cell(r.run_status) + cell(r.cutoff_date_exclusive) +
                cell(r.interval_start_date + ' – ' + r.interval_end_date_exclusive) + cell(r.actual_days) +
                cell(r.monthly_rate_percent) + cell(money(r.eligible_principal_basis)) + cell(money(r.posted_interest)) + cell(r.ledger_entry_id) + '</tr>').join('') ||
                '<tr><td colspan="11" class="p-4 text-secondary">कोई completed लेट फीस calculation नहीं मिली।</td></tr>';
        }
        summaryCards(payload.summary);
        state.page = Number(payload.meta?.current_page || state.page);
        state.perPage = Number(payload.meta?.per_page || state.perPage);
        state.lastPage = Number(payload.meta?.last_page || 1);
        renderPagination(Number(payload.meta?.total || 0));
        $('report-print').disabled = false;
        $('report-export').disabled = false;
    }

    function renderPagination(total) {
        const page = state.page;
        const perPage = state.perPage;
        const lastPage = state.lastPage;
        const first = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const last = total === 0 ? 0 : Math.min(page * perPage, total);
        const links = [];

        const addPage = (number) => {
            links.push(
                '<li class="page-item' + (number === page ? ' active' : '') + '">' +
                '<a class="page-link" href="#" data-report-page="' + number + '"' +
                (number === page ? ' aria-current="page"' : '') + '>' + number + '</a></li>'
            );
        };
        const addEllipsis = () => {
            links.push('<li class="page-item disabled" aria-hidden="true"><span class="page-link">…</span></li>');
        };

        if (lastPage > 1) {
            links.push(
                '<li class="page-item' + (page <= 1 ? ' disabled' : '') + '">' +
                '<a class="page-link" href="#" data-report-page="' + (page - 1) + '"' +
                (page <= 1 ? ' aria-disabled="true" tabindex="-1"' : '') +
                ' aria-label="Previous page"><i class="ti ti-chevron-left" aria-hidden="true"></i></a></li>'
            );

            if (lastPage <= 7) {
                for (let number = 1; number <= lastPage; number++) addPage(number);
            } else {
                addPage(1);
                if (page > 3) addEllipsis();
                for (let number = Math.max(2, page - 1); number <= Math.min(lastPage - 1, page + 1); number++) {
                    addPage(number);
                }
                if (page < lastPage - 2) addEllipsis();
                addPage(lastPage);
            }

            links.push(
                '<li class="page-item' + (page >= lastPage ? ' disabled' : '') + '">' +
                '<a class="page-link" href="#" data-report-page="' + (page + 1) + '"' +
                (page >= lastPage ? ' aria-disabled="true" tabindex="-1"' : '') +
                ' aria-label="Next page"><i class="ti ti-chevron-right" aria-hidden="true"></i></a></li>'
            );
        }

        $('report-page-info').innerHTML = 'Showing <strong>' + first + '–' + last +
            '</strong> of <strong>' + total + '</strong> रिकॉर्ड';
        $('report-page-size').value = String(perPage);
        $('report-page-links').innerHTML = links.join('');
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
            $('report-page-links').innerHTML = '';
            $('report-page-info').textContent = 'कुल 0 में से 0–0 रिकॉर्ड';
            $('report-print').disabled = true;
            $('report-export').disabled = true;
        } finally {
            $('report-load').disabled = false;
        }
    }

    $('report-customer').addEventListener('change', () => { state.page = 1; });
    $('report-village').addEventListener('change', () => { state.page = 1; });
    $('report-type').addEventListener('change', () => { state.page = 1; configure(); });
    $('report-load').addEventListener('click', () => { state.page = 1; load(); });
    $('report-page-links').addEventListener('click', (event) => {
        const link = event.target.closest('[data-report-page]');
        if (!link) return;
        event.preventDefault();
        if (link.getAttribute('aria-disabled') === 'true') return;

        const requestedPage = Number(link.dataset.reportPage);
        if (!Number.isInteger(requestedPage) || requestedPage < 1 ||
            requestedPage > state.lastPage || requestedPage === state.page) return;

        state.page = requestedPage;
        load();
    });
    $('report-page-size').addEventListener('change', (event) => {
        const requestedSize = Number(event.target.value);
        if (![10, 20, 50, 100].includes(requestedSize)) return;

        state.perPage = requestedSize;
        state.page = 1;
        load();
    });
    loadCustomers().then(configure).catch((error) => {
        $('report-feedback').textContent = error.message;
        $('report-customer').innerHTML = '<option value="">ग्राहक सूची उपलब्ध नहीं</option>';
    });
})();
</script>
@endpush
