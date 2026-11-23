@extends('layouts.auth')
@section('title', 'नया पासवर्ड')
@section('content')
<div class="auth-heading"><span class="eyebrow">NEW PASSWORD</span><h1>नया पासवर्ड सेट करें</h1><p>अपने खाते के लिए नया सुरक्षित पासवर्ड बनाएं।</p></div>
@if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
<form method="POST" action="{{ route('password.store') }}" class="auth-form">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">
    <div class="form-group"><label for="email">ईमेल पता</label><input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" class="form-control" autocomplete="email" required></div>
    <div class="form-group"><label for="password">नया पासवर्ड</label><input id="password" name="password" type="password" class="form-control" autocomplete="new-password" required></div>
    <div class="form-group"><label for="password_confirmation">पासवर्ड दोबारा लिखें</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" required></div>
    <button class="btn btn-primary w-100 auth-submit" type="submit">पासवर्ड अपडेट करें <i class="ti ti-check"></i></button>
</form>
@endsection
