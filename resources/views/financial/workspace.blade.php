@extends('layouts.app')

@section('title', $section === 'sales' ? 'बिक्री' : ($section === 'payments' ? 'जमा' : ($section === 'ledger' ? 'ग्राहक खाता' : ($section === 'interest' ? 'Late Fee (लेट फीस) सेटिंग्स' : ($section === 'reconciliation' ? 'मिलान' : 'वित्तीय ऑडिट')))))
@section('page-title', $section === 'sales' ? 'बिक्री' : ($section === 'payments' ? 'जमा' : ($section === 'ledger' ? 'ग्राहक खाता' : ($section === 'interest' ? 'Late Fee (लेट फीस) सेटिंग्स' : ($section === 'reconciliation' ? 'लेखा मिलान' : 'वित्तीय ऑडिट')))))

@section('content')
@php
    $labels = [
        'sales' => ['बिक्री रजिस्टर', 'दुकान की सभी पोस्ट की गई बिक्री देखें और नई बिक्री दर्ज करें।'],
        'payments' => ['जमा रजिस्टर', 'ग्राहकों से प्राप्त भुगतान और उनकी स्थिति देखें।'],
        'ledger' => ['ग्राहक खाता-बही', 'ग्राहक चुनकर मूलधन और Late Fee (लेट फीस) का running balance देखें।'],
        'interest' => ['Late Fee नीति', 'दुकान की effective-dated Late Fee नीति और ग्राहक overrides नियंत्रित करें।'],
        'reconciliation' => ['लेखा मिलान', 'Ledger और account projection में अंतर जाँचें; repair केवल Owner कर सकता है।'],
        'audit' => ['वित्तीय ऑडिट', 'वित्तीय नीतियों, corrections और projection repairs का immutable इतिहास।'],
    ];
@endphp
<div class="page-heading">
    <div>
        <span class="eyebrow">KISAN KHATA · FINANCIAL MANAGEMENT</span>
        <h1>{{ $labels[$section][0] }}</h1>
        <p>{{ $labels[$section][1] }}</p>
    </div>
    @if (in_array($section, ['sales', 'payments'], true))
        <button class="btn btn-primary kk-primary" type="button" id="financial-create"><i class="ti ti-plus me-1"></i> नया {{ $section === 'sales' ? 'बिक्री रिकॉर्ड' : 'भुगतान' }}</button>
    @endif
    @if ($section === 'ledger')
        <button class="btn btn-outline-primary" type="button" id="financial-ledger-print"><i class="ti ti-printer me-1" aria-hidden="true"></i> प्रिंट / PDF</button>
    @endif
</div>

<div class="content-card mb-3">
    @if ($section === 'interest')
        <div class="content-card-header interest-policy-header">
            <div>
                <h2>Late Fee नीति</h2>
                <p>दुकान की वर्तमान Late Fee नीति और ग्राहक-विशिष्ट बदलाव नियंत्रित करें।</p>
            </div>
            <div class="interest-policy-actions">
                <button type="button" class="btn btn-outline-success" id="open-shop-policy-drawer"><i class="ti ti-settings me-1" aria-hidden="true"></i> नई Shop Late Fee Policy</button>
                <button type="button" class="btn btn-primary kk-primary" id="open-customer-policy-drawer"><i class="ti ti-user-cog me-1" aria-hidden="true"></i> Customer Late Fee Override</button>
            </div>
        </div>
    @endif
    @if ($section === 'interest')
        <div class="financial-toolbar">
            <div class="financial-filter-grid">
                <div><label class="form-label" for="interest-history-customer">Late Fee Calculation History के लिए ग्राहक</label><select class="form-select" id="interest-history-customer"><option value="">ग्राहक चुनें</option></select></div>
                <div><label class="form-label" for="financial-from">से cutoff तारीख</label><input class="form-control" type="date" id="financial-from"></div>
                <div><label class="form-label" for="financial-to">तक cutoff तारीख</label><input class="form-control" type="date" id="financial-to"></div>
                <div class="financial-filter-action"><button class="btn btn-outline-secondary" id="interest-history-refresh" type="button"><i class="ti ti-refresh me-1"></i> History रिफ्रेश</button></div>
            </div>
        </div>
        <div id="interest-history-results" class="p-3" aria-live="polite"><div class="text-secondary">Late Fee calculation history देखने के लिए ग्राहक चुनें।</div></div>
    @endif
    @if (in_array($section, ['sales', 'payments'], true))
        <form id="financial-filters" class="financial-toolbar" role="search">
            <div class="financial-filter-grid">
                <div class="financial-search-field"><label class="form-label" for="financial-search">खोजें</label><div class="input-icon"><span class="input-icon-addon"><i class="ti ti-search" aria-hidden="true"></i></span><input class="form-control" type="search" id="financial-search" name="q" maxlength="100" placeholder="नंबर, ग्राहक या मोबाइल…" autocomplete="off"></div></div>
                <div><label class="form-label" for="financial-from">से तारीख</label><input class="form-control" type="date" id="financial-from" name="from"></div>
                <div><label class="form-label" for="financial-village">गाँव</label><select class="form-select" id="financial-village" name="village_code"><option value="">सभी गाँव</option>@foreach ($villages as $village)<option value="{{ $village->villageCode }}">{{ $village->villageNameLocal ?: $village->villageNameEnglish }}{{ $village->villageNameLocal && $village->villageNameEnglish ? ' · '.$village->villageNameEnglish : '' }}</option>@endforeach</select></div>
                <div><label class="form-label" for="financial-to">तक तारीख</label><input class="form-control" type="date" id="financial-to" name="to"></div>
                @if ($section === 'sales')
                    <div><label class="form-label" for="financial-status">भुगतान स्थिति</label><select class="form-select" id="financial-status" name="payment_status"><option value="">सभी स्थितियाँ</option><option value="UNPAID">उधार</option><option value="PARTIALLY_PAID">आंशिक जमा</option><option value="PAID">जमा पूर्ण</option></select></div>
                @else
                    <div><label class="form-label" for="financial-method">भुगतान माध्यम</label><select class="form-select" id="financial-method" name="payment_method"><option value="">सभी माध्यम</option><option value="CASH">Cash</option><option value="UPI">UPI</option><option value="BANK">Bank</option><option value="CHEQUE">Cheque</option></select></div>
                @endif
                <div><label class="form-label" for="financial-sort">क्रम</label><select class="form-select" id="financial-sort" name="sort"><option value="date_desc">नई तारीख पहले</option><option value="date_asc">पुरानी तारीख पहले</option><option value="amount_desc">बड़ी राशि पहले</option><option value="amount_asc">छोटी राशि पहले</option></select></div>
                <div class="financial-filter-action"><button class="btn btn-outline-secondary" type="button" data-financial-reset><i class="ti ti-x me-1"></i> साफ़ करें</button></div>
            </div>
        </form>
    @else
        <div class="financial-toolbar">
            <div class="financial-filter-grid">
                @if (in_array($section, ['ledger', 'reconciliation'], true))
                    <div><label class="form-label" for="financial-customer">ग्राहक</label><select class="form-select" id="financial-customer"><option value="">ग्राहक चुनें</option></select></div>
                @endif
                @if (in_array($section, ['ledger', 'audit'], true))
                    <div><label class="form-label" for="financial-from">से तारीख</label><input class="form-control" type="date" id="financial-from"></div>
                    <div><label class="form-label" for="financial-to">तक तारीख</label><input class="form-control" type="date" id="financial-to"></div>
                @endif
                @if ($section === 'audit')
                    <div><label class="form-label" for="financial-action">Action</label><input class="form-control" id="financial-action" placeholder="financial.*"></div>
                @endif
                <div class="financial-filter-action"><button class="btn btn-outline-secondary" id="financial-refresh" type="button"><i class="ti ti-refresh me-1"></i> रिफ्रेश</button></div>
            </div>
        </div>
    @endif
    <div id="financial-feedback" class="px-3 pt-3" aria-live="polite"></div>
    <div id="financial-results" aria-live="polite" aria-busy="true">
        <div class="empty-state"><i class="ti ti-loader-2"></i><h3>डेटा लोड हो रहा है…</h3></div>
    </div>
</div>



@if ($section === 'reconciliation')
    <div class="content-card mt-3">
        <div class="content-card-header"><div><h2>Projection repair</h2><p>केवल तब उपयोग करें जब reconciliation discrepancy पुष्टि हो। Repair से पहले और बाद का परिणाम audit log में दर्ज होगा।</p></div></div>
        <form id="projection-repair-form" class="financial-form-grid p-3">
            <div class="financial-form-wide"><label class="form-label">Repair का कारण (अनिवार्य)</label><textarea class="form-control" name="reason" minlength="10" maxlength="500" required rows="2"></textarea></div>
            <div class="financial-form-submit"><button class="btn btn-outline-danger" type="submit">Projection repair करें</button></div>
        </form>
    </div>
@endif
@endsection

@push('drawers')
    @if ($section === 'interest')
        <x-ui.action-drawer id="shop-interest-policy-drawer" title="नई Shop Late Fee Policy">
            <form id="shop-policy-form" class="financial-form-grid" method="POST" action="{{ url('/api/v1/interest-policy') }}" data-drawer-ajax>
                @csrf
                <div><label class="form-label" for="shop-policy-effective-from">लागू होने की तारीख</label><input class="form-control" id="shop-policy-effective-from" type="date" name="effective_from" min="{{ now()->toDateString() }}" required></div>
                <div><label class="form-label" for="shop-policy-rate">मासिक Late Fee दर (%)</label><input class="form-control" id="shop-policy-rate" type="number" name="monthly_rate_percent" min="0" max="100" step="0.00000001" required></div>
                <div><label class="form-label" for="shop-policy-grace">Grace days</label><input class="form-control" id="shop-policy-grace" type="number" name="grace_days" min="0" max="365" value="30" required></div>
                <div><label class="form-label" for="shop-policy-waterfall">Payment waterfall</label><select class="form-select" id="shop-policy-waterfall" name="payment_waterfall"><option value="PRINCIPAL_FIRST">Principal first</option><option value="INTEREST_FIRST">Late Fee पहले</option></select></div>
                <div><label class="form-label" for="shop-policy-capitalization">Capitalization</label><select class="form-select" id="shop-policy-capitalization" name="capitalization_enabled"><option value="0">बंद</option><option value="1">चालू</option></select></div>
                <div><label class="form-label">Settlement date 1 (महीना / दिन)</label><div class="d-flex gap-2"><input class="form-control" type="number" name="annual_settlement_month_1" min="1" max="12" value="3" aria-label="Settlement month 1" required><input class="form-control" type="number" name="annual_settlement_day_1" min="1" max="31" value="30" aria-label="Settlement day 1" required></div></div>
                <div><label class="form-label">Settlement date 2 (महीना / दिन)</label><div class="d-flex gap-2"><input class="form-control" type="number" name="annual_settlement_month_2" min="1" max="12" value="10" aria-label="Settlement month 2" required><input class="form-control" type="number" name="annual_settlement_day_2" min="1" max="31" value="30" aria-label="Settlement day 2" required></div></div>
                <div class="financial-form-wide"><label class="form-label" for="shop-policy-reason">बदलाव का कारण</label><input class="form-control" id="shop-policy-reason" name="reason" minlength="10" maxlength="500" required></div>
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-outline-secondary" data-drawer-close>रद्द करें</button>
                <button type="submit" form="shop-policy-form" class="btn btn-primary kk-primary">Policy सेव करें <i class="ti ti-check ms-1" aria-hidden="true"></i></button>
            </x-slot:footer>
        </x-ui.action-drawer>
        <x-ui.action-drawer id="customer-interest-override-drawer" title="Customer Late Fee Override">
            <form id="customer-policy-form" class="financial-form-grid" method="POST" data-drawer-ajax>
                @csrf
                <div class="financial-form-wide"><label class="form-label" for="interest-customer">ग्राहक</label><select class="form-select" name="customer_id" required id="interest-customer"><option value="">ग्राहक चुनें</option></select></div>
                <div><label class="form-label" for="customer-policy-effective-from">लागू होने की तारीख</label><input class="form-control" id="customer-policy-effective-from" type="date" name="effective_from" min="{{ now()->addDay()->toDateString() }}" required></div>
                <div><label class="form-label" for="customer-policy-rate">मासिक Late Fee दर (%)</label><input class="form-control" id="customer-policy-rate" type="number" name="monthly_rate_percent" min="0" max="100" step="0.00000001" placeholder="Shop Late Fee policy से inherit"></div>
                <div class="financial-form-wide"><label class="form-label" for="customer-policy-capitalization">Capitalization override</label><select class="form-select" id="customer-policy-capitalization" name="capitalization_enabled"><option value="">Shop policy से inherit</option><option value="0">बंद</option><option value="1">चालू</option></select></div>
                <div class="financial-form-wide"><label class="form-label" for="customer-policy-reason">बदलाव का कारण</label><input class="form-control" id="customer-policy-reason" name="reason" minlength="10" maxlength="500" required></div>
            </form>
            <div id="customer-policy-history" class="pt-3"></div>
            <x-slot:footer>
                <button type="button" class="btn btn-outline-secondary" data-drawer-close>रद्द करें</button>
                <button type="submit" form="customer-policy-form" class="btn btn-primary kk-primary">Override सेव करें <i class="ti ti-check ms-1" aria-hidden="true"></i></button>
            </x-slot:footer>
        </x-ui.action-drawer>
    @endif
    @if (in_array($section, ['sales', 'payments'], true))
        <x-ui.action-drawer id="financial-action-drawer" title="वित्तीय कार्रवाई" width="wide">
            <x-slot:footer>
                <button type="button" class="btn btn-outline-secondary" data-drawer-close>रद्द करें</button>
                <button type="submit" form="financial-drawer-form" class="btn btn-primary kk-primary" data-financial-drawer-submit>सेव करें <i class="ti ti-check ms-1" aria-hidden="true"></i></button>
            </x-slot:footer>
        </x-ui.action-drawer>
    @endif
@endpush

@push('scripts')
<script>
window.KKFinancialWorkspace = {
    section: @json($section),
    role: @json(auth()->user()->role),
    api: @json(url('/api/v1')),
    csrf: @json(csrf_token()),
    shopName: @json(app(\App\Tenancy\CurrentShop::class)->require()->name),
};
</script>

@endpush
