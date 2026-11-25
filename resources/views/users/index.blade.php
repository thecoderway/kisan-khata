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

        <form method="GET" action="{{ route('users.index') }}" class="d-flex flex-wrap align-items-end gap-2 p-3 border-bottom" role="search">
            <div class="flex-grow-1" style="min-width:min(100%,220px)">
                <label for="user-search" class="form-label small">नाम से खोजें</label>
                <input id="user-search" type="search" name="q" value="{{ request('q') }}" class="form-control" maxlength="100" placeholder="यूज़र का नाम">
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
            <button type="submit" class="btn btn-primary kk-primary">फ़िल्टर</button>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Reset</a>
        </form>

        @if ($users->isEmpty())
            <div class="empty-state"><i class="ti ti-users"></i><h3>कोई यूज़र नहीं मिला</h3>
                @if ($isOwner)
                    <p>फ़िल्टर बदलें या अपनी टीम का पहला Manager अथवा Staff account बनाएँ।</p>
                    <a href="{{ route('users.create') }}" class="btn btn-primary kk-primary">यूज़र बनाएँ</a>
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
                                                <form method="POST" action="{{ route('users.deactivate', $member->id) }}" onsubmit="return confirm('क्या आप इस account को deactivate करना चाहते हैं? इसकी सभी sessions और mobile tokens revoke हो जाएँगे।')">@csrf<button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button></form>
                                            @else
                                                <form method="POST" action="{{ route('users.reactivate', $member->id) }}" onsubmit="return confirm('क्या आप इस account को फिर से active करना चाहते हैं?')">@csrf<button type="submit" class="btn btn-sm btn-outline-success">Reactivate</button></form>
                                            @endif
                                            <form method="POST" action="{{ route('users.role', $member->id) }}" class="role-change-form" onsubmit="return confirm('क्या आप इस यूज़र की भूमिका बदलना चाहते हैं?')">
                                                @csrf @method('PATCH')
                                                <label class="visually-hidden" for="role-{{ $member->id }}">नई भूमिका</label>
                                                <select id="role-{{ $member->id }}" name="role" class="form-select form-select-sm" required>
                                                    <option value="" selected disabled>भूमिका बदलें</option>
                                                    @if ($member->role === 'manager')<option value="staff">Staff</option>@else<option value="manager">Manager</option>@endif
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">बदलें</button>
                                            </form>
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
                    <select class="form-select form-select-sm" aria-label="Rows per page" onchange="const url = new URL(window.location.href); url.searchParams.set('pageSize', this.value); url.searchParams.delete('page'); window.location.assign(url);">
                        @foreach ([10, 20, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($users->perPage() === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </label>
                {{ $users->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </section>
@endsection

@push('drawers')
    <x-ui.action-drawer id="user-action-drawer" title="नया यूज़र" width="wide">
        <x-slot:footer>
            <button type="button" class="btn btn-outline-secondary" data-drawer-close>रद्द करें</button>
            <button type="submit" form="user-create-form" class="btn btn-primary kk-primary">यूज़र बनाएँ <i class="ti ti-check ms-1"></i></button>
        </x-slot:footer>
    </x-ui.action-drawer>
@endpush
