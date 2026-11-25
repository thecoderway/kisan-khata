@extends('layouts.app')

@section('title', $customer ? 'ग्राहक संपादित करें' : 'नया ग्राहक')
@section('page-title', $customer ? 'ग्राहक संपादित करें' : 'नया ग्राहक')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">CUSTOMER PROFILE</span>
            <h1>{{ $customer ? 'ग्राहक की जानकारी बदलें' : 'नया ग्राहक जोड़ें' }}</h1>
            <p>नाम आवश्यक है। मोबाइल, पता और स्थान वैकल्पिक हैं।</p>
        </div>
    </div>

    @include('customers.partials.form')

    <div class="form-actions">
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">वापस जाएँ</a>
        <button type="submit" form="customer-form" class="btn btn-primary kk-primary">सेव करें <i class="ti ti-check ms-1"></i></button>
    </div>
@endsection
