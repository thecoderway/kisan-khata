@extends('layouts.auth')
@section('title', 'पासवर्ड बदलें')
@section('content')
<div class="auth-heading"><span class="eyebrow">FIRST LOGIN SECURITY</span><h1>अपना पासवर्ड बदलें</h1><p>आप पहली बार लॉगिन कर रहे हैं। आगे बढ़ने से पहले अस्थायी पासवर्ड बदलना आवश्यक है।</p></div>
@if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
<form method="POST" action="{{ route('password.first-login.update') }}" class="auth-form">
    @csrf
    <div class="form-group"><label for="current_password">अस्थायी पासवर्ड</label><input id="current_password" name="current_password" type="password" class="form-control" autocomplete="current-password" required autofocus></div>
    <div class="form-group"><label for="password">नया पासवर्ड</label><input id="password" name="password" type="password" class="form-control" autocomplete="new-password" required></div>
    <div class="form-group"><label for="password_confirmation">नया पासवर्ड दोबारा लिखें</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" required></div>
    <button class="btn btn-primary w-100 auth-submit" type="submit">पासवर्ड अपडेट करें <i class="ti ti-check"></i></button>
</form>
<form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="btn btn-outline-secondary w-100" type="submit">लॉगआउट</button></form>
@endsection
