@if ($errors->any())
    <div class="alert alert-danger" role="alert" tabindex="-1" data-drawer-error-summary>
        <strong>उपयोगकर्ता नहीं बनाया जा सका।</strong>
        <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
<form id="user-drawer-form" method="POST" action="{{ route('users.store') }}" data-drawer-ajax>
    @csrf
    <input type="hidden" name="drawer_context" value="1">
    <div class="form-section-heading"><i class="ti ti-user-plus"></i><div><h2>खाते की जानकारी</h2><p>प्रबंधक या कर्मचारी की व्यक्तिगत और लॉगिन जानकारी दर्ज करें।</p></div></div>
    <div class="row g-3">
        <div class="col-md-6"><label for="user-name" class="form-label">पूरा नाम <span class="text-danger">*</span></label><input id="user-name" name="name" type="text" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" maxlength="255" autocomplete="name" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label for="user-role" class="form-label">भूमिका <span class="text-danger">*</span></label><select id="user-role" name="role" class="form-select @error('role') is-invalid @enderror" required><option value="">भूमिका चुनें</option><option value="manager" @selected(old('role') === 'manager')>प्रबंधक</option><option value="staff" @selected(old('role') === 'staff')>कर्मचारी</option></select>@error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label for="user-email" class="form-label">ईमेल पता <span class="text-danger">*</span></label><input id="user-email" name="email" type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" maxlength="255" autocomplete="email" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label for="user-mobile" class="form-label">मोबाइल नंबर <span class="text-danger">*</span></label><input id="user-mobile" name="mobile" type="tel" value="{{ old('mobile') }}" class="form-control @error('mobile') is-invalid @enderror" maxlength="20" autocomplete="tel" required>@error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </div>
    <div class="form-section-heading mt-4"><i class="ti ti-lock"></i><div><h2>अस्थायी पासवर्ड</h2><p>पहली बार लॉगिन पर उपयोगकर्ता को अपना नया पासवर्ड बनाना होगा।</p></div></div>
    <div class="row g-3">
        <div class="col-md-6"><label for="user-temporary-password" class="form-label">अस्थायी पासवर्ड <span class="text-danger">*</span></label><input id="user-temporary-password" name="temporary_password" type="password" class="form-control @error('temporary_password') is-invalid @enderror" autocomplete="new-password" required>@error('temporary_password')<div class="invalid-feedback">{{ $message }}</div>@enderror<small class="form-hint">कम से कम 8 अक्षर, एक अंग्रेज़ी अक्षर और एक अंक।</small></div>
        <div class="col-md-6"><label for="user-temporary-password-confirmation" class="form-label">अस्थायी पासवर्ड की पुष्टि करें <span class="text-danger">*</span></label><input id="user-temporary-password-confirmation" name="temporary_password_confirmation" type="password" class="form-control" autocomplete="new-password" required></div>
    </div>
</form>
