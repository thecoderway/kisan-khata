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
        <a href="{{ route('customers.create') }}" class="btn btn-primary kk-primary"><i class="ti ti-user-plus me-1"></i> नया ग्राहक</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
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
                    <a href="{{ route('customers.create') }}" class="btn btn-primary kk-primary">ग्राहक जोड़ें</a>
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
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    @if (! $customer->trashed())
                                        <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-outline-secondary">विवरण</a>
                                    @endif

                                    @if ($customer->trashed())
                                        @can('restore', $customer)
                                            <form method="POST" action="{{ route('customers.restore', $customer) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                                            </form>
                                        @endcan
                                    @elseif ($customer->status === 'active')
                                        @can('deactivate', $customer)
                                            <form method="POST" action="{{ route('customers.deactivate', $customer) }}" class="d-flex flex-wrap gap-1">
                                                @csrf
                                                <input name="reason" class="form-control form-control-sm" placeholder="निष्क्रिय करने का कारण" aria-label="निष्क्रिय करने का कारण" required maxlength="500">
                                                <button type="submit" class="btn btn-sm btn-outline-warning">निष्क्रिय करें</button>
                                            </form>
                                        @endcan
                                    @else
                                        @can('reactivate', $customer)
                                            <form method="POST" action="{{ route('customers.reactivate', $customer) }}" class="d-flex flex-wrap gap-1">
                                                @csrf
                                                <input name="reason" class="form-control form-control-sm" placeholder="कारण (वैकल्पिक)" aria-label="पुनः सक्रिय करने का कारण" maxlength="500">
                                                <button type="submit" class="btn btn-sm btn-outline-success">पुनः सक्रिय करें</button>
                                            </form>
                                        @endcan
                                    @endif

                                    @if (! $customer->trashed())
                                        @can('softDelete', $customer)
                                            <form method="POST" action="{{ route('customers.soft-delete', $customer) }}" class="d-flex flex-wrap gap-1" onsubmit="return confirm('क्या आप इस ग्राहक को Soft Delete करना चाहते हैं?')">
                                                @csrf
                                                @method('DELETE')
                                                <input name="reason" class="form-control form-control-sm" placeholder="हटाने का कारण" aria-label="Soft Delete का कारण" required maxlength="500">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Soft Delete</button>
                                            </form>
                                        @endcan
                                    @endif
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
