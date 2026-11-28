@extends('layouts.app')

@section('title', 'ग्राहक विवरण')
@section('page-title', 'ग्राहक विवरण')

@section('content')
    <div class="page-heading" data-customer-detail data-customer-index-url="{{ route('customers.index') }}">
        <div>
            <span class="eyebrow">CUSTOMER PROFILE</span>
            <h1>{{ $customer->name_hi ?: $customer->name_original }}</h1>
            <p>{{ $customer->mobile ?: 'मोबाइल नंबर दर्ज नहीं है' }}</p>
            @if ($customer->status === 'inactive')
                <span class="badge bg-warning-lt">निष्क्रिय ग्राहक</span>
            @else
                <span class="badge bg-success-lt">सक्रिय ग्राहक</span>
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('update', $customer)
                <button type="button" class="btn btn-primary kk-primary"
                    data-customer-drawer
                    data-url="{{ route('customers.edit', [$customer, 'drawer' => 1]) }}"
                    data-title="ग्राहक संपादित करें"
                    data-width="wide">
                    <i class="ti ti-edit me-1"></i> संपादित करें
                </button>
            @endcan
            @if (in_array(auth()->user()->role, [\App\Models\User::ROLE_OWNER, \App\Models\User::ROLE_MANAGER], true))
                @if ($customer->status === 'active')
                    @can('deactivate', $customer)
                        <button type="button" class="btn btn-outline-warning"
                            data-customer-drawer
                            data-customer-lifecycle-action="deactivate"
                            data-url="{{ route('customers.index', ['drawer_action' => 'deactivate', 'drawer_customer_id' => $customer->id]) }}"
                            data-title="ग्राहक निष्क्रिय करें">
                            <i class="ti ti-user-off me-1"></i> निष्क्रिय करें
                        </button>
                    @endcan
                @else
                    @can('reactivate', $customer)
                        <button type="button" class="btn btn-outline-success"
                            data-customer-drawer
                            data-customer-lifecycle-action="reactivate"
                            data-url="{{ route('customers.index', ['drawer_action' => 'reactivate', 'drawer_customer_id' => $customer->id]) }}"
                            data-title="ग्राहक पुनः सक्रिय करें">
                            <i class="ti ti-user-check me-1"></i> पुनः सक्रिय करें
                        </button>
                    @endcan
                @endif
                @can('softDelete', $customer)
                    <button type="button" class="btn btn-outline-danger"
                        data-customer-drawer
                        data-customer-lifecycle-action="soft-delete"
                        data-url="{{ route('customers.index', ['drawer_action' => 'soft-delete', 'drawer_customer_id' => $customer->id]) }}"
                        data-title="ग्राहक Soft Delete करें">
                        <i class="ti ti-trash me-1"></i> Soft Delete
                    </button>
                @endcan
            @endif
        </div>
    </div>

    @if (session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

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

@push('drawers')
    <x-ui.action-drawer id="customer-detail-action-drawer" title="ग्राहक कार्रवाई" width="wide">
        <x-slot:footer>
            <button type="button" class="btn btn-outline-secondary" data-drawer-close>रद्द करें</button>
            <button type="submit" form="customer-form" class="btn btn-primary kk-primary">सेव करें <i class="ti ti-check ms-1"></i></button>
        </x-slot:footer>
    </x-ui.action-drawer>
@endpush
