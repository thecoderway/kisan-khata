@extends('layouts.app')

@section('title', auth()->user()->role === \App\Models\User::ROLE_MANAGER ? 'Staff सूची' : 'यूज़र प्रबंधन')
@section('page-title', auth()->user()->role === \App\Models\User::ROLE_MANAGER ? 'Staff सूची' : 'यूज़र प्रबंधन')

@section('content')
    @php($isOwner = auth()->user()->role === \App\Models\User::ROLE_OWNER)
    <div class="page-heading">
        <div>
            <span class="eyebrow">TEAM & ACCESS</span>
            <h1>{{ $isOwner ? 'दुकान के यूज़र' : 'आपकी दुकान के Staff' }}</h1>
            <p>{{ $isOwner ? 'Manager और Staff accounts तथा उनकी access स्थिति प्रबंधित करें।' : 'आप Staff सदस्यों का नाम और account status देख सकते हैं।' }}</p>
        </div>
        @if ($isOwner)
            <button type="button" class="btn btn-primary kk-primary" data-user-drawer data-url="{{ route('users.create', ['drawer' => 1]) }}" data-title="नया यूज़र" data-width="wide"><i class="ti ti-user-plus me-1"></i> नया यूज़र</button>
        @endif
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible" role="status">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="बंद करें"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($capacity)
        <section class="capacity-grid" aria-label="Active user capacity">
            @foreach ($capacity['roles'] as $roleName => $usage)
                <article class="capacity-card">
                    <span>{{ ucfirst($roleName) }}</span>
                    <strong>{{ $usage['active'] }} <small>/ {{ $usage['limit'] }}</small></strong>
                    <div class="capacity-track"><span style="width: {{ min(100, ($usage['active'] / $usage['limit']) * 100) }}%"></span></div>
                    <small>Active accounts</small>
                </article>
            @endforeach
            <article class="capacity-card capacity-total">
                <span>कुल active users</span>
                <strong>{{ $capacity['active_total'] }} <small>/ {{ $capacity['total_limit'] }}</small></strong>
                <div class="capacity-track"><span style="width: {{ min(100, ($capacity['active_total'] / $capacity['total_limit']) * 100) }}%"></span></div>
                <small>Shop capacity</small>
            </article>
        </section>
    @endif

    <section class="content-card" data-user-directory>
        <div class="content-card-header">
            <div><h2>{{ $isOwner ? 'Manager और Staff' : 'Staff सदस्य' }}</h2><p>{{ $users->total() }} accounts</p></div>
        </div>

        <form method="GET" action="{{ route('users.index') }}" class="d-flex flex-wrap align-items-end gap-2 p-3 border-bottom" role="search" data-user-filters>
            <div class="flex-grow-1" style="min-width:min(100%,220px)">
                <label for="user-search" class="form-label small">नाम से खोजें</label>
                <input id="user-search" type="search" name="q" value="{{ request('q') }}" class="form-control" maxlength="100" placeholder="यूज़र का नाम" autocomplete="off">
            </div>
            @if ($isOwner)
                <div>
                    <label for="user-role-filter" class="form-label small">भूमिका</label>
                    <select id="user-role-filter" name="role" class="form-select">
                        <option value="all" @selected(request('role', 'all') === 'all')>सभी भूमिकाएँ</option>
                        <option value="manager" @selected(request('role') === 'manager')>Manager</option>
                        <option value="staff" @selected(request('role') === 'staff')>Staff</option>
                    </select>
                </div>
            @endif
            <div>
                <label for="user-status-filter" class="form-label small">स्थिति</label>
                <select id="user-status-filter" name="status" class="form-select">
                    <option value="all" @selected(request('status', 'all') === 'all')>सभी स्थितियाँ</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Deactivated</option>
                </select>
            </div>
            <input type="hidden" name="pageSize" value="{{ $users->perPage() }}">
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary" data-user-filter-reset @if (!request('q') && request('role', 'all') === 'all' && request('status', 'all') === 'all') hidden @endif>Reset</a>
        </form>

        <div class="visually-hidden" data-user-filter-status role="status" aria-live="polite"></div>
        <div id="user-results" data-user-results>
            @include('users.partials.results', ['users' => $users, 'isOwner' => $isOwner])
        </div>
    </section>
@endsection

@if ($isOwner)
    @push('drawers')
        <x-ui.action-drawer id="user-action-drawer" title="नया यूज़र" width="wide">
            <x-slot:footer>
                <button type="button" class="btn btn-outline-secondary" data-drawer-close>रद्द करें</button>
                <button type="submit" form="user-drawer-form" class="btn btn-primary kk-primary">जारी रखें <i class="ti ti-check ms-1"></i></button>
            </x-slot:footer>
        </x-ui.action-drawer>
    @endpush
@endif
