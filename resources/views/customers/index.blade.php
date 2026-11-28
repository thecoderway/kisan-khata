@extends('layouts.app')

@section('title', 'ग्राहक')
@section('page-title', 'ग्राहक')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">CUSTOMER DIRECTORY</span>
            <h1>ग्राहक</h1>
            <p>अपनी दुकान के ग्राहकों की जानकारी खोजें और प्रबंधित करें।</p>
        </div>
        @can('create', App\Models\Customer::class)
            <button type="button" class="btn btn-primary kk-primary" data-customer-drawer data-url="{{ route('customers.create', ['drawer' => 1]) }}" data-title="नया ग्राहक" data-width="wide">
                <i class="ti ti-user-plus me-1"></i> नया ग्राहक
            </button>
        @endcan
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any() && ! $drawerContent)
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="content-card">
        <form method="GET" action="{{ route('customers.index') }}" class="content-card-header d-flex flex-wrap gap-2 align-items-center" data-customer-filters data-village-options-url="{{ route('customers.villages') }}">
            <div class="flex-grow-1">
                <label for="customer-search" class="visually-hidden">ग्राहक खोजें</label>
                <input id="customer-search" name="q" value="{{ $search }}" class="form-control" placeholder="नाम या मोबाइल नंबर से खोजें" maxlength="100" autocomplete="off">
            </div>
            <div class="position-relative" data-village-filter>
                <label for="customer-village-search" class="visually-hidden">गाँव खोजें</label>
                <input
                    id="customer-village-search"
                    type="search"
                    class="form-control"
                    placeholder="गाँव खोजें…"
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-haspopup="listbox"
                    aria-expanded="false"
                    aria-controls="customer-village-options"
                    data-village-search
                    value="{{ $selectedVillage ? collect([$selectedVillage->villageNameLocal ?: $selectedVillage->villageNameEnglish, $selectedVillage->subdistrictNameLocal ?: $selectedVillage->subdistrictNameEnglish, $selectedVillage->districtNameLocal ?: $selectedVillage->districtNameEnglish])->filter()->implode(', ') : '' }}"
                >
                <input type="hidden" name="village_code" value="{{ $villageCode }}">
                <div
                    id="customer-village-options"
                    class="list-group position-absolute start-0 top-100 mt-1 w-100 shadow"
                    style="z-index: 1050; max-height: 18rem; overflow-y: auto;"
                    role="listbox"
                    data-village-options
                    hidden
                ></div>
            </div>
            <div>
                <label for="customer-status" class="visually-hidden">ग्राहक स्थिति</label>
                <select id="customer-status" name="status" class="form-select">
                    <option value="active" @selected($status === 'active')>सक्रिय ग्राहक</option>
                    <option value="inactive" @selected($status === 'inactive')>निष्क्रिय ग्राहक</option>
                    <option value="deleted" @selected($status === 'deleted')>हटाए गए ग्राहक</option>
                </select>
            </div>
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary" data-customer-filter-reset @if ($search === '' && $status === 'active' && $villageCode === '') hidden @endif>साफ़ करें</a>
        </form>

        <div class="visually-hidden" data-customer-filter-status role="status" aria-live="polite"></div>
        <div id="customer-results" data-customer-results>
            @include('customers.partials.results', ['customers' => $customers, 'search' => $search, 'status' => $status])
        </div>
    </section>
@endsection

@push('drawers')
    <x-ui.action-drawer id="customer-action-drawer" title="{{ match($drawerAction) { 'edit' => 'ग्राहक संपादित करें', 'deactivate' => 'ग्राहक निष्क्रिय करें', 'reactivate' => 'ग्राहक पुनः सक्रिय करें', 'soft-delete' => 'ग्राहक Soft Delete करें', 'restore' => 'ग्राहक Restore करें', default => 'नया ग्राहक' } }}" width="wide">
        @if ($drawerContent)
            {!! $drawerContent !!}
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-outline-secondary" data-drawer-close>बंद करें</button>
            <button type="submit" form="customer-form" class="btn btn-primary kk-primary">{{ match($drawerAction) { "deactivate" => "निष्क्रिय करें", "reactivate" => "पुनः सक्रिय करें", "soft-delete" => "Soft Delete", "restore" => "Restore", default => "सेव करें" } }} <i class="ti ti-check ms-1"></i></button>
        </x-slot:footer>
    </x-ui.action-drawer>
@endpush
