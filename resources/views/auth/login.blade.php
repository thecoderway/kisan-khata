@extends('layouts.auth')
@section('title', 'लॉगिन')
@section('content')
<div class="auth-heading"><span class="eyebrow">WELCOME BACK</span><h1>अपने खाते में लॉगिन करें</h1><p>अपनी दुकान का हिसाब-किताब आगे बढ़ाएं।</p></div>
@if (session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif
@if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
<form method="POST" action="{{ route('login') }}" class="auth-form">
    @csrf
    <div class="form-group"><label for="email">ईमेल पता</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control" autocomplete="username" required autofocus>@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="form-group"><div class="field-label-row"><label for="password">पासवर्ड</label><a href="{{ route('password.request') }}">पासवर्ड भूल गए?</a></div><input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required></div>
    <label class="form-check"><input type="checkbox" name="remember" value="1" class="form-check-input"> <span>मुझे लॉगिन रखें</span></label>
    <button class="btn btn-primary w-100 auth-submit" type="submit">लॉगिन करें <i class="ti ti-arrow-right"></i></button>
</form>
<div class="auth-switch">नया खाता बनाना है? <a href="{{ route('register') }}">दुकान रजिस्टर करें</a></div>
@endsection
