@extends('layouts.auth')
@section('title', 'दुकान रजिस्ट्रेशन')
@section('content')
<div class="auth-heading"><span class="eyebrow">GET STARTED</span><h1>अपनी दुकान रजिस्टर करें</h1><p>कुछ जानकारी भरें और अपना डिजिटल खाता शुरू करें।</p></div>
@if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
<form method="POST" action="{{ route('register') }}" class="auth-form">
    @csrf
    <div class="form-group"><label for="shop_name">दुकान का नाम</label><input id="shop_name" name="shop_name" value="{{ old('shop_name') }}" class="form-control" maxlength="255" required autofocus>@error('shop_name')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="owner_name">मालिक का नाम</label><input id="owner_name" name="owner_name" value="{{ old('owner_name') }}" class="form-control" maxlength="255" autocomplete="name" required>@error('owner_name')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="mobile">मोबाइल नंबर</label><input id="mobile" name="mobile" type="tel" value="{{ old('mobile') }}" class="form-control" maxlength="20" autocomplete="tel" required>@error('mobile')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="email">ईमेल पता</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control" autocomplete="email" required>@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="password">पासवर्ड</label><input id="password" name="password" type="password" class="form-control" autocomplete="new-password" required><small class="form-hint">कम से कम 8 अक्षर, एक letter और एक number</small>@error('password')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><label for="password_confirmation">पासवर्ड दोबारा लिखें</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" required></div>
    <button class="btn btn-primary w-100 auth-submit" type="submit">दुकान और खाता बनाएं <i class="ti ti-arrow-right"></i></button>
</form>
<div class="auth-switch">पहले से खाता है? <a href="{{ route('login') }}">लॉगिन करें</a></div>
@endsection
