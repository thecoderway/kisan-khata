@extends('layouts.app')

@section('title', $section === 'sales' ? 'बिक्री' : ($section === 'payments' ? 'जमा' : ($section === 'ledger' ? 'ग्राहक खाता' : ($section === 'interest' ? 'ब्याज सेटिंग्स' : ($section === 'reconciliation' ? 'मिलान' : 'वित्तीय ऑडिट')))))
@section('page-title', $section === 'sales' ? 'बिक्री' : ($section === 'payments' ? 'जमा' : ($section === 'ledger' ? 'ग्राहक खाता' : ($section === 'interest' ? 'ब्याज सेटिंग्स' : ($section === 'reconciliation' ? 'लेखा मिलान' : 'वित्तीय ऑडिट')))))

@section('content')
@php
    $labels = [
        'sales' => ['बिक्री रजिस्टर', 'दुकान की सभी पोस्ट की गई बिक्री देखें और नई बिक्री दर्ज करें।'],
        'payments' => ['जमा रजिस्टर', 'ग्राहकों से प्राप्त भुगतान और उनकी स्थिति देखें।'],
        'ledger' => ['ग्राहक खाता-बही', 'ग्राहक चुनकर principal और interest का running balance देखें।'],
        'interest' => ['ब्याज नीति', 'दुकान की effective-dated ब्याज नीति और ग्राहक overrides नियंत्रित करें।'],
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
</div>

<div class="content-card mb-3">
    <div class="financial-toolbar">
        <div class="financial-filter-grid">
            @if (in_array($section, ['ledger', 'reconciliation'], true))
                <div><label class="form-label" for="financial-customer">ग्राहक</label><select class="form-select" id="financial-customer"><option value="">ग्राहक चुनें</option></select></div>
            @endif
            @if (in_array($section, ['sales', 'payments', 'ledger', 'audit'], true))
                <div><label class="form-label" for="financial-from">से तारीख</label><input class="form-control" type="date" id="financial-from"></div>
                <div><label class="form-label" for="financial-to">तक तारीख</label><input class="form-control" type="date" id="financial-to"></div>
            @endif
            @if ($section === 'audit')
                <div><label class="form-label" for="financial-action">Action</label><input class="form-control" id="financial-action" placeholder="financial.*"></div>
            @endif
            <div class="financial-filter-action"><button class="btn btn-outline-secondary" id="financial-refresh" type="button"><i class="ti ti-refresh me-1"></i> रिफ्रेश</button></div>
        </div>
    </div>
    <div id="financial-feedback" class="px-3 pt-3" aria-live="polite"></div>
    <div id="financial-results" class="table-responsive">
        <div class="empty-state"><i class="ti ti-loader-2"></i><h3>डेटा लोड हो रहा है…</h3></div>
    </div>
</div>

@if ($section === 'interest')
    <div class="content-card mb-3">
        <div class="content-card-header"><div><h2>नई Shop Interest Policy</h2><p>पुरानी policy immutable रहेगी; बदलाव भविष्य की तारीख से लागू होगा।</p></div></div>
        <form id="shop-policy-form" class="financial-form-grid p-3">
            <div><label class="form-label">लागू होने की तारीख</label><input class="form-control" type="date" name="effective_from" required></div>
            <div><label class="form-label">मासिक ब्याज दर (%)</label><input class="form-control" type="number" name="monthly_rate_percent" min="0" max="100" step="0.00000001" required></div>
            <div><label class="form-label">Grace days</label><input class="form-control" type="number" name="grace_days" min="0" max="365" value="30" required></div>
            <div><label class="form-label">Payment waterfall</label><select class="form-select" name="payment_waterfall"><option value="PRINCIPAL_FIRST">Principal first</option><option value="INTEREST_FIRST">Interest first</option></select></div>
            <div><label class="form-label">Capitalization</label><select class="form-select" name="capitalization_enabled"><option value="0">बंद</option><option value="1">चालू</option></select></div>
            <div><label class="form-label">Settlement date 1 (महीना / दिन)</label><div class="d-flex gap-2"><input class="form-control" type="number" name="annual_settlement_month_1" min="1" max="12" value="3" required><input class="form-control" type="number" name="annual_settlement_day_1" min="1" max="31" value="30" required></div></div>
            <div><label class="form-label">Settlement date 2 (महीना / दिन)</label><div class="d-flex gap-2"><input class="form-control" type="number" name="annual_settlement_month_2" min="1" max="12" value="10" required><input class="form-control" type="number" name="annual_settlement_day_2" min="1" max="31" value="30" required></div></div>
            <div class="financial-form-wide"><label class="form-label">बदलाव का कारण</label><input class="form-control" name="reason" minlength="10" maxlength="500" required></div><div class="financial-form-submit"><button class="btn btn-primary kk-primary" type="submit">नई policy version सेव करें</button></div>
        </form>
    </div>
    <div class="content-card">
        <div class="content-card-header"><div><h2>Customer Interest Override</h2><p>खाली छोड़े गए fields Shop policy से inherit होंगे।</p></div></div>
        <form id="customer-policy-form" class="financial-form-grid p-3">
            <div><label class="form-label">ग्राहक</label><select class="form-select" name="customer_id" required id="interest-customer"><option value="">ग्राहक चुनें</option></select></div>
            <div><label class="form-label">लागू होने की तारीख</label><input class="form-control" type="date" name="effective_from" required></div>
            <div><label class="form-label">मासिक ब्याज दर (%)</label><input class="form-control" type="number" name="monthly_rate_percent" min="0" max="100" step="0.00000001" placeholder="Shop policy से inherit"></div>
            <div><label class="form-label">Capitalization override</label><select class="form-select" name="capitalization_enabled"><option value="">Shop policy से inherit</option><option value="0">बंद</option><option value="1">चालू</option></select></div>
            <div class="financial-form-wide"><label class="form-label">बदलाव का कारण</label><input class="form-control" name="reason" minlength="10" maxlength="500" required></div><div class="financial-form-submit"><button class="btn btn-outline-success" type="submit">Customer override सेव करें</button></div>
        </form>
        <div id="customer-policy-history" class="p-3"></div>
    </div>
@endif

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

@push('scripts')
<script>
window.KKFinancialWorkspace = {
    section: @json($section),
    role: @json(auth()->user()->role),
    api: @json(url('/api/v1')),
    csrf: @json(csrf_token()),
};
</script>

@endpush
