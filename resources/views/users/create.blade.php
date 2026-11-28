@extends('layouts.app')

@section('title', 'नया उपयोगकर्ता')
@section('page-title', 'नया उपयोगकर्ता')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">टीम प्रबंधन</span><h1>नया उपयोगकर्ता बनाएँ</h1>
            <p>प्रबंधक या कर्मचारी को आपकी दुकान में जोड़ा जाएगा।</p></div>
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i> वापस</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">कृपया नीचे दी गई जानकारी जाँचें।</div>
    @endif

    <section class="content-card form-card">
        <form method="POST" action="{{ route('users.store') }}" class="user-create-form">
            @csrf
            <div class="form-section-heading"><i class="ti ti-user-plus"></i><div><h2>खाते की जानकारी</h2><p>उपयोगकर्ता की व्यक्तिगत और लॉगिन जानकारी दर्ज करें।</p></div></div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">पूरा नाम <span class="text-danger">*</span></label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" maxlength="255" autocomplete="name" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="role" class="form-label">भूमिका <span class="text-danger">*</span></label>
                    <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                        <option value="">भूमिका चुनें</option>
                        <option value="manager" @selected(old('role') === 'manager')>प्रबंधक</option>
                        <option value="staff" @selected(old('role') === 'staff')>कर्मचारी</option>
                    </select>
                    @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">ईमेल पता <span class="text-danger">*</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" maxlength="255" autocomplete="email" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="mobile" class="form-label">मोबाइल नंबर <span class="text-danger">*</span></label>
                    <input id="mobile" name="mobile" type="tel" value="{{ old('mobile') }}" class="form-control @error('mobile') is-invalid @enderror" maxlength="20" autocomplete="tel" required>
                    @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-section-heading mt-4"><i class="ti ti-lock"></i><div><h2>अस्थायी पासवर्ड</h2><p>पहली बार लॉगिन पर उपयोगकर्ता को अपना नया पासवर्ड बनाना होगा।</p></div></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="temporary_password" class="form-label">अस्थायी पासवर्ड <span class="text-danger">*</span></label>
                    <input id="temporary_password" name="temporary_password" type="password" class="form-control @error('temporary_password') is-invalid @enderror" autocomplete="new-password" required>
                    @error('temporary_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="form-hint">कम से कम 8 अक्षर, एक अंग्रेज़ी अक्षर और एक अंक।</small>
                </div>
                <div class="col-md-6">
                    <label for="temporary_password_confirmation" class="form-label">अस्थायी पासवर्ड की पुष्टि करें <span class="text-danger">*</span></label>
                    <input id="temporary_password_confirmation" name="temporary_password_confirmation" type="password" class="form-control" autocomplete="new-password" required>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">रद्द करें</a>
                <button type="submit" class="btn btn-primary kk-primary"><i class="ti ti-check me-1"></i> उपयोगकर्ता बनाएँ</button>
            </div>
        </form>
    </section>
@endsection
