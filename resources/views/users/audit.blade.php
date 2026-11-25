@extends('layouts.app')

@section('title', 'यूज़र ऑडिट हिस्ट्री')
@section('page-title', 'यूज़र ऑडिट हिस्ट्री')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">SECURITY & ACCOUNTABILITY</span><h1>यूज़र ऑडिट हिस्ट्री</h1>
            <p>दुकान के Manager और Staff account में हुए बदलावों का append-only record।</p></div>
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i> यूज़र सूची</a>
    </div>
    <section class="content-card" data-user-audit-history>
        <div class="content-card-header"><div><h2>Recent account activity</h2><p>{{ $logs->total() }} audit records</p></div></div>
        <form method="GET" action="{{ route('users.audit') }}" class="d-flex flex-wrap align-items-end gap-2 p-3 border-bottom" role="search" data-audit-filters>
            <div class="flex-grow-1" style="min-width:min(100%,220px)">
                <label for="audit-search" class="form-label small">नाम या कारण से खोजें</label>
                <input id="audit-search" type="search" name="q" value="{{ request('q') }}" class="form-control" maxlength="100" placeholder="Actor, target या reason" autocomplete="off">
            </div>
            <div><label for="audit-action" class="form-label small">कार्रवाई</label>
                <select id="audit-action" name="action" class="form-select">
                    <option value="all" @selected(request('action', 'all') === 'all')>सभी कार्रवाइयाँ</option>
                    <option value="user.created" @selected(request('action') === 'user.created')>Created</option>
                    <option value="user.deactivated" @selected(request('action') === 'user.deactivated')>Deactivated</option>
                    <option value="user.reactivated" @selected(request('action') === 'user.reactivated')>Reactivated</option>
                    <option value="user.role_changed" @selected(request('action') === 'user.role_changed')>Role changed</option>
                </select>
            </div>
            <input type="hidden" name="pageSize" value="{{ $logs->perPage() }}">
            <a href="{{ route('users.audit') }}" class="btn btn-outline-secondary" data-audit-filter-reset @if (!request('q') && request('action', 'all') === 'all') hidden @endif>Reset</a>
        </form>
        <div class="visually-hidden" data-audit-filter-status role="status" aria-live="polite"></div>
        <div id="audit-results" data-audit-results aria-live="polite">
            @include('users.partials.audit-results', ['logs' => $logs])
        </div>
    </section>
@endsection
