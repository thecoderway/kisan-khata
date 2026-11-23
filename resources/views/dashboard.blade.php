@extends('layouts.auth')
@section('title', 'डैशबोर्ड')
@section('content')
<div class="dashboard-top"><span class="eyebrow">YOUR WORKSPACE</span><span class="status-pill"><i class="ti ti-circle-check"></i> खाता सक्रिय</span></div>
<h1 class="dashboard-title">नमस्ते, {{ auth()->user()->name }} जी!</h1>
<p class="dashboard-copy">आपका Kisan Khata तैयार है। अगले चरण में हम दुकान का पूरा डैशबोर्ड और हिसाब-किताब मॉड्यूल जोड़ेंगे।</p>
<div class="shop-summary"><span class="summary-icon"><i class="ti ti-building-store"></i></span><div><small>आपकी दुकान</small><strong>{{ auth()->user()->shop?->name ?? 'दुकान उपलब्ध नहीं' }}</strong></div></div>
<div class="shop-summary"><span class="summary-icon"><i class="ti ti-user"></i></span><div><small>आपकी भूमिका</small><strong>{{ ucfirst(auth()->user()->role) }}</strong></div></div>
<form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="btn btn-outline-secondary w-100" type="submit">लॉगआउट <i class="ti ti-logout"></i></button></form>
@endsection
