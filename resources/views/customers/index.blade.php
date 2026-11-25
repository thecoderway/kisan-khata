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

    <section class="content-card">
        <form method="GET" action="{{ route('customers.index') }}" class="content-card-header d-flex flex-wrap gap-2 align-items-center">
            <div class="flex-grow-1">
                <label for="customer-search" class="visually-hidden">ग्राहक खोजें</label>
                <input id="customer-search" name="q" value="{{ $search }}" class="form-control" placeholder="नाम या मोबाइल नंबर से खोजें" maxlength="100">
            </div>
            <button type="submit" class="btn btn-outline-secondary"><i class="ti ti-search me-1"></i> खोजें</button>
            @if ($search !== '')
                <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">साफ़ करें</a>
            @endif
        </form>

        @if ($customers->isEmpty())
            <div class="empty-state">
                <i class="ti ti-users"></i>
                <h3>{{ $search !== '' ? 'कोई ग्राहक नहीं मिला' : 'अभी कोई ग्राहक नहीं है' }}</h3>
                <p>{{ $search !== '' ? 'दूसरे नाम या मोबाइल नंबर से खोजें।' : 'अपनी दुकान का पहला ग्राहक जोड़ें।' }}</p>
                @if ($search === '')
                    <a href="{{ route('customers.create') }}" class="btn btn-primary kk-primary">ग्राहक जोड़ें</a>
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter user-table">
                    <thead><tr><th>ग्राहक</th><th>मोबाइल</th><th>गाँव</th><th>कार्रवाई</th></tr></thead>
                    <tbody>
                    @foreach ($customers as $customer)
                        <tr>
                            <td><strong>{{ $customer->name_hi ?: $customer->name_original }}</strong>
                                @if ($customer->name_en && $customer->name_en !== $customer->name_original)
                                    <div class="text-secondary small">{{ $customer->name_en }}</div>
                                @endif
                            </td>
                            <td>{{ $customer->mobile ?: '—' }}</td>
                            <td>{{ $customer->village_name_local ?: $customer->village_name_english ?: '—' }}</td>
                            <td><a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-outline-secondary">विवरण</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $customers->links() }}</div>
        @endif
    </section>
@endsection
