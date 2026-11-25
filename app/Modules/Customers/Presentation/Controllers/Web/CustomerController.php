<?php

namespace App\Modules\Customers\Presentation\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Modules\Customers\Application\CustomerDirectoryQuery;
use App\Modules\Customers\Application\SaveCustomer;
use App\Modules\Customers\Presentation\Requests\CustomerRequest;
use App\Tenancy\CurrentShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request, CustomerDirectoryQuery $directory, CurrentShop $currentShop): View|Response
    {
        Gate::authorize('viewAny', Customer::class);

        $drawerAction = $request->query('drawer_action');
        $drawerCustomer = null;
        $drawerContent = null;

        if ($drawerAction === 'create') {
            Gate::authorize('create', Customer::class);
            $shop = $currentShop->require();
            $drawerContent = view('customers.partials.form', [
                'customer' => null,
                'drawerMode' => true,
                'locationDefaults' => [
                    'state_code' => $shop->state_code,
                    'district_code' => $shop->district_code,
                    'subdistrict_code' => $shop->subdistrict_code,
                    'village_code' => $shop->village_code,
                ],
            ])->render();
        } elseif ($drawerAction === 'edit' && $request->filled('drawer_customer_id')) {
            $drawerCustomer = Customer::query()->findOrFail((int) $request->query('drawer_customer_id'));
            Gate::authorize('update', $drawerCustomer);
            $drawerContent = view('customers.partials.form', [
                'customer' => $drawerCustomer,
                'drawerMode' => true,
                'locationDefaults' => [],
            ])->render();
        } elseif (in_array($drawerAction, ['deactivate', 'reactivate', 'soft-delete', 'restore'], true) && $request->filled('drawer_customer_id')) {
            $drawerCustomer = Customer::withTrashed()->findOrFail((int) $request->query('drawer_customer_id'));
            Gate::authorize($drawerAction === 'soft-delete' ? 'softDelete' : $drawerAction, $drawerCustomer);
            $drawerContent = view('customers.partials.lifecycle-form', [
                'customer' => $drawerCustomer,
                'action' => $drawerAction,
            ])->render();
        }

        if ($request->ajax() && $drawerContent !== null) {
            return response($drawerContent);
        }

        return view('customers.index', [
            'customers' => $directory->paginate($request),
            'search' => trim((string) $request->query('q', '')),
            'status' => in_array($request->query('status'), ['active', 'inactive', 'deleted'], true) ? $request->query('status') : 'active',
            'drawerAction' => $drawerContent ? $drawerAction : null,
            'drawerCustomer' => $drawerCustomer,
            'drawerContent' => $drawerContent,
        ]);
    }

    public function create(CurrentShop $currentShop, Request $request): View
    {
        Gate::authorize('create', Customer::class);

        $shop = $currentShop->require();
        $data = [
            'customer' => null,
            'drawerMode' => $request->boolean('drawer'),
            'locationDefaults' => [
                'state_code' => $shop->state_code,
                'district_code' => $shop->district_code,
                'subdistrict_code' => $shop->subdistrict_code,
                'village_code' => $shop->village_code,
            ],
        ];

        return $request->boolean('drawer')
            ? view('customers.partials.form', $data)
            : view('customers.form', $data);
    }

    public function store(CustomerRequest $request, SaveCustomer $save): RedirectResponse
    {
        $customer = $save->create(
            $request->user(),
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        $redirect = $request->boolean('drawer_context')
            ? redirect()->route('customers.index')
            : redirect()->route('customers.show', $customer);

        return $redirect->with('status', 'ग्राहक सफलतापूर्वक जोड़ दिया गया।');
    }

    public function show(int $customer): View
    {
        $customer = Customer::query()->findOrFail($customer);
        Gate::authorize('view', $customer);

        $location = $customer->village_code
            ? DB::table('villages')->where('villageCode', $customer->village_code)->first()
            : null;

        return view('customers.show', compact('customer', 'location'));
    }

    public function edit(int $customer, Request $request): View
    {
        $customer = Customer::query()->findOrFail($customer);
        Gate::authorize('update', $customer);

        $data = [
            'customer' => $customer,
            'drawerMode' => $request->boolean('drawer'),
            'locationDefaults' => [],
        ];

        return $request->boolean('drawer')
            ? view('customers.partials.form', $data)
            : view('customers.form', $data);
    }

    public function update(CustomerRequest $request, int $customer, SaveCustomer $save): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($customer);
        $save->update(
            $request->user(),
            $customer,
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        $redirect = $request->boolean('drawer_context')
            ? redirect()->route('customers.index')
            : redirect()->route('customers.show', $customer);

        return $redirect->with('status', 'ग्राहक की जानकारी अपडेट हो गई है।');
    }
}
