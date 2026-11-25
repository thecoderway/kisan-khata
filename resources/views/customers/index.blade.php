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
        <form method="GET" action="{{ route('customers.index') }}" class="content-card-header d-flex flex-wrap gap-2 align-items-center">
            <div class="flex-grow-1">
                <label for="customer-search" class="visually-hidden">ग्राहक खोजें</label>
                <input id="customer-search" name="q" value="{{ $search }}" class="form-control" placeholder="नाम या मोबाइल नंबर से खोजें" maxlength="100">
            </div>
            <div>
                <label for="customer-status" class="visually-hidden">ग्राहक स्थिति</label>
                <select id="customer-status" name="status" class="form-select">
                    <option value="active" @selected($status === 'active')>सक्रिय ग्राहक</option>
                    <option value="inactive" @selected($status === 'inactive')>निष्क्रिय ग्राहक</option>
                    <option value="deleted" @selected($status === 'deleted')>Soft Deleted ग्राहक</option>
                </select>
            </div>
            <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-search me-1"></i> खोजें</button>
            @if ($search !== '' || $status !== 'active')
                <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">साफ़ करें</a>
            @endif
        </form>

        @if ($customers->isEmpty())
            <div class="empty-state">
                <i class="ti ti-users"></i>
                <h3>{{ $search !== '' ? 'कोई ग्राहक नहीं मिला' : 'इस श्रेणी में कोई ग्राहक नहीं है' }}</h3>
                <p>दूसरे नाम या ग्राहक स्थिति से खोजें।</p>
                @if ($status === 'active')
                    @can('create', App\Models\Customer::class)
                        <button type="button" class="btn btn-primary kk-primary" data-customer-drawer data-url="{{ route('customers.create', ['drawer' => 1]) }}" data-title="नया ग्राहक" data-width="wide">ग्राहक जोड़ें</button>
                    @endcan
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter user-table">
                    <thead><tr><th>ग्राहक</th><th>मोबाइल</th><th>गाँव</th><th>स्थिति</th><th>कार्रवाई</th></tr></thead>
                    <tbody>
                    @foreach ($customers as $customer)
                        <tr>
                            <td>
                                @if (! $customer->trashed())
                                    <a href="{{ route('customers.show', $customer) }}" class="fw-semibold text-reset text-decoration-none">{{ $customer->name_hi ?: $customer->name_original }}</a>
                                @else
                                    <strong>{{ $customer->name_hi ?: $customer->name_original }}</strong>
                                @endif
                                @if ($customer->name_en && $customer->name_en !== $customer->name_original)
                                    <div class="text-secondary small">{{ $customer->name_en }}</div>
                                @endif
                            </td>
                            <td>{{ $customer->mobile ?: '—' }}</td>
                            <td>{{ $customer->village_name_local ?: $customer->village_name_english ?: '—' }}</td>
                            <td>
                                @if ($customer->trashed())
                                    <span class="badge bg-secondary-lt">Soft Deleted</span>
                                @elseif ($customer->status === 'inactive')
                                    <span class="badge bg-warning-lt">निष्क्रिय</span>
                                @else
                                    <span class="badge bg-success-lt">सक्रिय</span>
                                @endif
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        कार्रवाई
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if (! $customer->trashed())
                                            <a class="dropdown-item" href="{{ route('customers.show', $customer) }}">विवरण देखें</a>
                                        @endif
                                        @can('update', $customer)
                                            @if (! $customer->trashed())
                                                <button type="button" class="dropdown-item" data-customer-drawer data-url="{{ route('customers.edit', [$customer, 'drawer' => 1]) }}" data-title="ग्राहक संपादित करें" data-width="wide">संपादित करें</button>
                                            @endif
                                        @endcan
                                        @if ($customer->trashed())
                                            @can('restore', $customer)
                                                <button type="button" class="dropdown-item" data-customer-drawer data-url="{{ route('customers.index', ['drawer_action' => 'restore', 'drawer_customer_id' => $customer->id]) }}" data-title="ग्राहक Restore करें">Restore</button>
                                            @endcan
                                        @else
                                            @if ($customer->status === 'active')
                                                @can('deactivate', $customer)
                                                    <button type="button" class="dropdown-item" data-customer-drawer data-url="{{ route('customers.index', ['drawer_action' => 'deactivate', 'drawer_customer_id' => $customer->id]) }}" data-title="ग्राहक निष्क्रिय करें">निष्क्रिय करें</button>
                                                @endcan
                                            @else
                                                @can('reactivate', $customer)
                                                    <button type="button" class="dropdown-item" data-customer-drawer data-url="{{ route('customers.index', ['drawer_action' => 'reactivate', 'drawer_customer_id' => $customer->id]) }}" data-title="ग्राहक पुनः सक्रिय करें">पुनः सक्रिय करें</button>
                                                @endcan
                                            @endif
                                            @can('softDelete', $customer)
                                                <button type="button" class="dropdown-item text-danger" data-customer-drawer data-url="{{ route('customers.index', ['drawer_action' => 'soft-delete', 'drawer_customer_id' => $customer->id]) }}" data-title="ग्राहक Soft Delete करें">Soft Delete</button>
                                            @endcan
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $customers->links() }}</div>
        @endif
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
