@if ($customers->isEmpty())
    <div class="empty-state">
        <i class="ti ti-users" aria-hidden="true"></i>
        <h3>{{ $search !== '' ? 'कोई ग्राहक नहीं मिला' : 'इस श्रेणी में कोई ग्राहक नहीं है' }}</h3>
        <p>दूसरे नाम या ग्राहक स्थिति से खोजें।</p>
        @if ($status === 'active')
            @can('create', App\Models\Customer::class)
                <button type="button" class="btn btn-primary kk-primary" data-customer-drawer data-url="{{ route('customers.create', ['drawer' => 1]) }}" data-title="नया ग्राहक" data-width="wide">ग्राहक जोड़ें</button>
            @endcan
        @endif
    </div>
@else
    <div class="table-responsive customer-table-wrap">
        <table class="table table-vcenter user-table customer-directory-table">
            <thead>
                <tr>
                    <th scope="col">ग्राहक</th>
                    <th scope="col">मोबाइल</th>
                    <th scope="col">गाँव</th>
                    <th scope="col">स्थिति</th>
                    <th scope="col" class="actions-column">कार्रवाई</th>
                </tr>
            </thead>
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
                            <div class="dropdown customer-row-dropdown" data-customer-dropdown-portal>
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false">
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
