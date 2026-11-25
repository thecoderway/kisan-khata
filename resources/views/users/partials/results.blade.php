@if ($users->isEmpty())
    <div class="empty-state"><i class="ti ti-users"></i><h3>कोई यूज़र नहीं मिला</h3>
        @if ($isOwner)
            <p>फ़िल्टर बदलें या अपनी टीम का पहला Manager अथवा Staff account बनाएँ।</p>
            <button type="button" class="btn btn-primary kk-primary" data-user-drawer data-url="{{ route('users.create', ['drawer' => 1]) }}" data-title="नया यूज़र" data-width="wide">यूज़र बनाएँ</button>
        @else
            <p>आपकी खोज या स्थिति फ़िल्टर से मेल खाता Staff account उपलब्ध नहीं है।</p>
        @endif
    </div>
@else
    <div class="table-responsive kk-data-table-wrap">
        <table class="table table-vcenter user-table">
            <thead><tr>
                <th scope="col">नाम</th>
                @if ($isOwner)
                    <th scope="col">ईमेल</th><th scope="col">मोबाइल</th><th scope="col">भूमिका</th>
                @endif
                <th scope="col">स्थिति</th>
                @if ($isOwner)<th scope="col" class="actions-column">कार्रवाई</th>@endif
            </tr></thead>
            <tbody>
            @foreach ($users as $member)
                <tr>
                    <td><div class="member-cell"><span class="member-avatar">{{ mb_substr($member->name, 0, 1) }}</span><strong>{{ $member->name }}</strong></div></td>
                    @if ($isOwner)
                        <td>{{ $member->email ?: '—' }}</td><td>{{ $member->mobile ?: '—' }}</td>
                        <td><span class="role-pill">{{ ucfirst($member->role) }}</span></td>
                    @endif
                    <td><span class="account-status {{ $member->is_active ? 'is-active' : 'is-inactive' }}"><i class="ti ti-circle-filled"></i>{{ $member->is_active ? 'Active' : 'Deactivated' }}</span></td>
                    @if ($isOwner)
                        <td>
                            @if (in_array($member->role, [\App\Models\User::ROLE_MANAGER, \App\Models\User::ROLE_STAFF], true))
                                <div class="user-actions">
                                    @if ($member->is_active)
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-user-drawer data-url="{{ route('users.lifecycle-form', [$member->id, 'deactivate']) }}" data-title="यूज़र Deactivate करें">Deactivate</button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-success" data-user-drawer data-url="{{ route('users.lifecycle-form', [$member->id, 'reactivate']) }}" data-title="यूज़र Reactivate करें">Reactivate</button>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-user-drawer data-url="{{ route('users.lifecycle-form', [$member->id, 'role']) }}" data-title="यूज़र की भूमिका बदलें">भूमिका बदलें</button>
                                </div>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="customer-pagination-wrap d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div class="customer-pagination-summary text-secondary small" aria-live="polite">
        @if ($users->total() > 0)
            Showing <strong>{{ $users->firstItem() }}–{{ $users->lastItem() }}</strong> of <strong>{{ $users->total() }}</strong> users
        @else
            Showing <strong>0–0</strong> of <strong>0</strong> users
        @endif
    </div>
    <div class="customer-pagination-controls d-flex flex-wrap align-items-center gap-3">
        <label class="customer-page-size d-flex align-items-center gap-2 small text-secondary">
            <span>Rows per page</span>
            <select class="form-select form-select-sm" aria-label="Rows per page" data-user-page-size>
                @foreach ([10, 20, 50, 100] as $size)
                    <option value="{{ $size }}" @selected($users->perPage() === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </label>
        {{ $users->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
</div>
