@extends('layouts.app')

@section('title', 'ग्राहक विवरण')
@section('page-title', 'ग्राहक विवरण')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">CUSTOMER PROFILE</span>
            <h1>{{ $customer->name_hi ?: $customer->name_original }}</h1>
            <p>{{ $customer->mobile ?: 'मोबाइल नंबर दर्ज नहीं है' }}</p>
        </div>
        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary kk-primary"><i class="ti ti-edit me-1"></i> जानकारी बदलें</a>
    </div>

    @if (session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif

    <section class="content-card form-card">
        <div class="row g-4">
            <div class="col-md-6"><span class="text-secondary small">मूल नाम</span><div class="fw-semibold">{{ $customer->name_original }}</div></div>
            <div class="col-md-6"><span class="text-secondary small">English name</span><div class="fw-semibold">{{ $customer->name_en ?: '—' }}</div></div>
            <div class="col-md-6"><span class="text-secondary small">हिंदी नाम</span><div class="fw-semibold">{{ $customer->name_hi ?: '—' }}</div></div>
            <div class="col-md-6"><span class="text-secondary small">मोबाइल</span><div class="fw-semibold">{{ $customer->mobile ?: '—' }}</div></div>
            <div class="col-md-6"><span class="text-secondary small">गाँव</span><div class="fw-semibold">{{ $location?->villageNameLocal ?: $location?->villageNameEnglish ?: '—' }}</div></div>
            <div class="col-md-6"><span class="text-secondary small">पता</span><div class="fw-semibold">{{ $customer->address_hi ?: $customer->address_original ?: '—' }}</div></div>
            <div class="col-12"><span class="text-secondary small">नोट्स</span><div class="fw-semibold">{{ $customer->notes ?: '—' }}</div></div>
        </div>
        <div class="form-actions"><a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">ग्राहक सूची</a></div>
    </section>
@endsection
