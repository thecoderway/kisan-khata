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
        @if ($logs->isEmpty())
            <div class="empty-state"><i class="ti ti-history"></i><h3>अभी कोई audit activity नहीं है</h3>
                <p>यूज़र create, deactivate, reactivate या role change होने पर record यहाँ दिखाई देगा।</p></div>
        @else
            <div class="table-responsive kk-data-table-wrap">
                <table class="table table-vcenter audit-table">
                    <thead><tr><th>समय</th><th>कार्रवाई</th><th>किसने की</th><th>किस पर</th><th>बदलाव</th><th>कारण</th></tr></thead>
                    <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->created_at?->timezone(config('app.timezone'))->format('d M Y, h:i A') }}</td>
                            <td><span class="role-pill">{{ str($log->action)->replace('user.', '')->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $log->actor?->name ?? '—' }}</td>
                            <td>{{ $log->target?->name ?? '—' }}</td>
                            <td>
                                @if (isset($log->old_values['role']) || isset($log->new_values['role']))
                                    {{ ucfirst($log->old_values['role'] ?? '—') }} → {{ ucfirst($log->new_values['role'] ?? '—') }}
                                @elseif (isset($log->old_values['is_active']) || isset($log->new_values['is_active']))
                                    {{ ($log->old_values['is_active'] ?? false) ? 'Active' : 'Deactivated' }} → {{ ($log->new_values['is_active'] ?? false) ? 'Active' : 'Deactivated' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $log->reason ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $logs->links() }}</div>
        @endif
    </section>
@endsection
