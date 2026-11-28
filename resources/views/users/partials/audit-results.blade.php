@if ($logs->isEmpty())
    <div class="empty-state"><i class="ti ti-history"></i><h3>कोई लेखा-जाँच रिकॉर्ड नहीं मिला</h3>
        <p>{{ request()->hasAny(['q', 'action']) ? 'खोज या छँटाई बदलकर दोबारा प्रयास करें।' : 'उपयोगकर्ता बनाने, निष्क्रिय करने, पुनः सक्रिय करने या भूमिका बदलने होने पर record यहाँ दिखाई देगा।' }}</p></div>
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
                            {{ ($log->old_values['role'] ?? null) === 'manager' ? 'प्रबंधक' : (($log->old_values['role'] ?? null) === 'staff' ? 'कर्मचारी' : '—') }} → {{ ($log->new_values['role'] ?? null) === 'manager' ? 'प्रबंधक' : (($log->new_values['role'] ?? null) === 'staff' ? 'कर्मचारी' : '—') }}
                        @elseif (isset($log->old_values['is_active']) || isset($log->new_values['is_active']))
                            {{ ($log->old_values['is_active'] ?? false) ? 'सक्रिय' : 'निष्क्रिय' }} → {{ ($log->new_values['is_active'] ?? false) ? 'सक्रिय' : 'निष्क्रिय' }}
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
            कुल {{ $logs->total() }} में से <strong>{{ $logs->firstItem() }}–{{ $logs->lastItem() }}</strong> रिकॉर्ड
        @else
            कुल 0 में से <strong>0–0</strong> रिकॉर्ड
        @endif
    </div>
    <div class="customer-pagination-controls d-flex flex-wrap align-items-center gap-3">
        <label class="customer-page-size d-flex align-items-center gap-2 small text-secondary">
            <span>प्रति पृष्ठ पंक्तियाँ</span>
            <select class="form-select form-select-sm" aria-label="प्रति पृष्ठ पंक्तियाँ" data-audit-page-size>
                @foreach ([10, 20, 50, 100] as $size)
                    <option value="{{ $size }}" @selected($logs->perPage() === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </label>
        {{ $logs->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
</div>
