@extends('layouts.app')

@section('title', 'ग्राहक विवरण')
@section('page-title', 'ग्राहक विवरण')

@section('content')
    <div class="page-heading">
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
        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary kk-primary"><i class="ti ti-edit me-1"></i> जानकारी बदलें</a>
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

        @if (in_array(auth()->user()->role, [\App\Models\User::ROLE_OWNER, \App\Models\User::ROLE_MANAGER], true))
            <hr class="my-4">
            <h2 class="h3 mb-3">ग्राहक स्थिति प्रबंधन</h2>
            <div class="d-flex flex-wrap gap-3">
                @if ($customer->status === 'active')
                    @can('deactivate', $customer)
                        <form method="POST" action="{{ route('customers.deactivate', $customer) }}" class="d-flex flex-wrap gap-2 align-items-end">
                            @csrf
                            <div><label for="deactivate-reason" class="form-label">निष्क्रिय करने का कारण</label><input id="deactivate-reason" name="reason" class="form-control" required maxlength="500"></div>
                            <button type="submit" class="btn btn-outline-warning">निष्क्रिय करें</button>
                        </form>
                    @endcan
                @else
                    @can('reactivate', $customer)
                        <form method="POST" action="{{ route('customers.reactivate', $customer) }}" class="d-flex flex-wrap gap-2 align-items-end">
                            @csrf
                            <div><label for="reactivate-reason" class="form-label">कारण (वैकल्पिक)</label><input id="reactivate-reason" name="reason" class="form-control" maxlength="500"></div>
                            <button type="submit" class="btn btn-outline-success">पुनः सक्रिय करें</button>
                        </form>
                    @endcan
                @endif

                @can('softDelete', $customer)
                    <form method="POST" action="{{ route('customers.soft-delete', $customer) }}" class="d-flex flex-wrap gap-2 align-items-end" onsubmit="return confirm('क्या आप इस ग्राहक को Soft Delete करना चाहते हैं?')">
                        @csrf
                        @method('DELETE')
                        <div><label for="delete-reason" class="form-label">Soft Delete का कारण</label><input id="delete-reason" name="reason" class="form-control" required maxlength="500"></div>
                        <button type="submit" class="btn btn-outline-danger">Soft Delete</button>
                    </form>
                @endcan
            </div>
        @endif
        <div class="form-actions"><a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">ग्राहक सूची</a></div>
    </section>
@endsection
