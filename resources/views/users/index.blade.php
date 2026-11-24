@extends('layouts.app')

@section('title', auth()->user()->role === \\App\\Models\\User::ROLE_MANAGER ? 'Staff सूची' : 'यूज़र प्रबंधन')
@section('page-title', auth()->user()->role === \\App\\Models\\User::ROLE_MANAGER ? 'Staff सूची' : 'यूज़र प्रबंधन')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">TEAM & ACCESS</span>
            <h1>{{ auth()->user()->role === \\App\\Models\\User::ROLE_MANAGER ? 'आपकी दुकान के Staff' : 'दुकान के यूज़र' }}</h1>
            <p>{{ auth()->user()->role === \\App\\Models\\User::ROLE_MANAGER ? 'आप Staff सदस्यों का नाम और account status देख सकते हैं।' : 'Manager और Staff accounts तथा उनकी access स्थिति प्रबंधित करें।' }}</p>
        </div>
        @if (auth()->user()->role === \\App\\Models\\User::ROLE_OWNER)
            <a href="{{ route('users.create') }}" class="btn btn-primary kk-primary"><i class="ti ti-user-plus me-1"></i> नया यूज़र</a>
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
            @foreach ($capacity['roles'] as $role => $usage)
                <article class="capacity-card">
                    <span>{{ ucfirst($role) }}</span>
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

    <section class="content-card">
        <div class="content-card-header">
            <div><h2>{{ auth()->user()->role === \\App\\Models\\User::ROLE_MANAGER ? 'Staff सदस्य' : 'Manager और Staff' }}</h2>
                <p>{{ $users->count() }} account{{ $users->count() === 1 ? '' : 's' }}</p></div>
        </div>

        @if ($users->isEmpty())
            <div class="empty-state"><i class="ti ti-users"></i><h3>अभी कोई यूज़र नहीं है</h3>
                @if (auth()->user()->role === \\App\\Models\\User::ROLE_OWNER)
                    <p>अपनी टीम का पहला Manager या Staff account बनाएँ।</p>
                    <a href="{{ route('users.create') }}" class="btn btn-primary kk-primary">यूज़र बनाएँ</a>
                @else
                    <p>आपकी दुकान में अभी Staff account उपलब्ध नहीं है।</p>
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter user-table">
                    <thead><tr>
                        <th>नाम</th>
                        @if (auth()->user()->role === \\App\\Models\\User::ROLE_OWNER)
                            <th>ईमेल</th><th>मोबाइल</th><th>भूमिका</th>
                        @endif
                        <th>स्थिति</th>
                        @if (auth()->user()->role === \\App\\Models\\User::ROLE_OWNER)<th class="actions-column">कार्रवाई</th>@endif
                    </tr></thead>
                    <tbody>
                    @foreach ($users as $member)
                        <tr>
                            <td><div class="member-cell"><span class="member-avatar">{{ mb_substr($member->name, 0, 1) }}</span><strong>{{ $member->name }}</strong></div></td>
                            @if (auth()->user()->role === \\App\\Models\\User::ROLE_OWNER)
                                <td>{{ $member->email ?: '—' }}</td>
                                <td>{{ $member->mobile ?: '—' }}</td>
                                <td><span class="role-pill">{{ ucfirst($member->role) }}</span></td>
                            @endif
                            <td><span class="account-status {{ $member->is_active ? 'is-active' : 'is-inactive' }}">
                                <i class="ti ti-circle-filled"></i>{{ $member->is_active ? 'Active' : 'Deactivated' }}
                            </span></td>
                            @if (auth()->user()->role === \\App\\Models\\User::ROLE_OWNER)
                                <td>
                                    <div class="user-actions">
                                        @if ($member->is_active)
                                            <form method="POST" action="{{ route('users.deactivate', $member->id) }}" onsubmit="return confirm('क्या आप इस account को deactivate करना चाहते हैं? इसकी सभी sessions और mobile tokens revoke हो जाएँगे।')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('users.reactivate', $member->id) }}" onsubmit="return confirm('क्या आप इस account को फिर से active करना चाहते हैं?')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success">Reactivate</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('users.role', $member->id) }}" class="role-change-form" onsubmit="return confirm('क्या आप इस यूज़र की भूमिका बदलना चाहते हैं?')">
                                            @csrf
                                            @method('PATCH')
                                            <label class="visually-hidden" for="role-{{ $member->id }}">नई भूमिका</label>
                                            <select id="role-{{ $member->id }}" name="role" class="form-select form-select-sm">
                                                <option value="manager" @selected($member->role === 'manager')>Manager</option>
                                                <option value="staff" @selected($member->role === 'staff')>Staff</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">बदलें</button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
