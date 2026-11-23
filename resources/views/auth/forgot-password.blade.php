@extends('layouts.auth')
@section('title', 'पासवर्ड रीसेट')
@section('content')
<div class="auth-heading"><span class="eyebrow">ACCOUNT RECOVERY</span><h1>पासवर्ड भूल गए?</h1><p>अपना रजिस्टर्ड ईमेल दें। हम पासवर्ड रीसेट लिंक भेजेंगे।</p></div>
@if (session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif
@if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
<form method="POST" action="{{ route('password.email') }}" class="auth-form">
    @csrf
    <div class="form-group"><label for="email">ईमेल पता</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control" autocomplete="email" required autofocus></div>
    <button class="btn btn-primary w-100 auth-submit" type="submit">रीसेट लिंक भेजें <i class="ti ti-mail"></i></button>
</form>
<div class="auth-switch"><a href="{{ route('login') }}">लॉगिन पर वापस जाएं</a></div>
@endsection
