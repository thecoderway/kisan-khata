@extends('layouts.app')

@section('title', 'डैशबोर्ड')
@section('page-title', 'डैशबोर्ड')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">YOUR WORKSPACE</span>
            <h1>नमस्ते, {{ auth()->user()->name }} जी!</h1>
            <p>आपका Kisan Khata कार्यक्षेत्र तैयार है।</p>
        </div>
        <span class="status-pill"><i class="ti ti-circle-check"></i> खाता सक्रिय</span>
    </div>

    <section class="welcome-panel">
        <div class="welcome-icon"><i class="ti ti-building-store"></i></div>
        <div>
            <h2>{{ $shop->name }}</h2>
            <p>आपकी भूमिका: <strong>{{ ucfirst(auth()->user()->role) }}</strong></p>
        </div>
    </section>

    <div class="empty-module">
        <i class="ti ti-seedling"></i>
        <h2>आपका हिसाब-किताब यहाँ दिखाई देगा</h2>
        <p>ग्राहक और वित्तीय मॉड्यूल अगले विकास चरणों में जोड़े जाएँगे।</p>
    </div>
@endsection
