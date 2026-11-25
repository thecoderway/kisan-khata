@extends('layouts.app')

@section('title', 'यूज़र ऑडिट हिस्ट्री')
@section('page-title', 'यूज़र ऑडिट हिस्ट्री')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">SECURITY & ACCOUNTABILITY</span><h1>यूज़र ऑडिट हिस्ट्री</h1>
            <p>दुकान के Manager और Staff account में हुए बदलावों का append-only record।</p></div>
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i> यूज़र सूची</a>
    </div>
    <section class="content-card">
        <div class="content-card-header"><div><h2>Recent account activity</h2><p>{{ $logs->total() }} audit records</p></div></div>
        <form method="GET" action="{{ route('users.audit') }}" class="d-flex flex-wrap align-items-end gap-2 p-3 border-bottom" role="search">
            <div class="flex-grow-1" style="min-width:min(100%,220px)">
                <label for="audit-search" class="form-label small">नाम या कारण से खोजें</label>
                <input id="audit-search" type="search" name="q" value="{{ request('q') }}" class="form-control" maxlength="100" placeholder="Actor, target या reason">
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
            <button type="submit" class="btn btn-primary kk-primary">फ़िल्टर</button>
            <a href="{{ route('users.audit') }}" class="btn btn-outline-secondary">Reset</a>
        </form>
        @if ($logs->isEmpty())
            <div class="empty-state"><i class="ti ti-history"></i><h3>कोई audit record नहीं मिला</h3>
                <p>{{ request()->hasAny(['q', 'action']) ? 'खोज या filter बदलकर दोबारा प्रयास करें।' : 'यूज़र create, deactivate, reactivate या role change होने पर record यहाँ दिखाई देगा।' }}</p></div>
        @else
            <div class="table-responsive kk-data-table-wrap">
                <table class="table table-vcenter audit-table">
                    <thead><tr><th>समय</th><th>कार्रवाई</th><th>किसने की</th><th>किस पर</th><th>बदलाव</th><th>कारण</th></tr></thead>
                    <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->created_at?->timezone(config('app.timezone'))->format('d M Y, h:i A') }}</td>
                            <td><span class="role-pill">{{ str($log->action)->replace('user.', '')->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $log->actor?->name ?? '—' }}</td><td>{{ $log->target?->name ?? '—' }}</td>
                            <td>
                                @if (isset($log->old_values['role']) || isset($log->new_values['role']))
                                    {{ ucfirst($log->old_values['role'] ?? '—') }} → {{ ucfirst($log->new_values['role'] ?? '—') }}
                                @elseif (isset($log->old_values['is_active']) || isset($log->new_values['is_active']))
                                    {{ ($log->old_values['is_active'] ?? false) ? 'Active' : 'Deactivated' }} → {{ ($log->new_values['is_active'] ?? false) ? 'Active' : 'Deactivated' }}
                                @else —
                                @endif
                            </td>
                            <td>{{ $log->reason ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <div class="customer-pagination-wrap d-flex flex-wrap align-items-center justify-content-between gap-3 p-3">
            <div class="customer-pagination-summary text-secondary small" aria-live="polite">
                @if ($logs->total() > 0)
                    Showing <strong>{{ $logs->firstItem() }}–{{ $logs->lastItem() }}</strong> of <strong>{{ $logs->total() }}</strong> records
                @else
                    Showing <strong>0–0</strong> of <strong>0</strong> records
                @endif
            </div>
            <div class="customer-pagination-controls d-flex flex-wrap align-items-center gap-3">
                <label class="customer-page-size d-flex align-items-center gap-2 small text-secondary">
                    <span>Rows per page</span>
                    <select class="form-select form-select-sm" aria-label="Rows per page" onchange="const url = new URL(window.location.href); url.searchParams.set('pageSize', this.value); url.searchParams.delete('page'); window.location.assign(url);">
                        @foreach ([10, 20, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($logs->perPage() === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </label>
                {{ $logs->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </section>
@endsection
