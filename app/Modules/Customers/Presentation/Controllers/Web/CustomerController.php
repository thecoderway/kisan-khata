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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request, CustomerDirectoryQuery $directory): View
    {
        Gate::authorize('viewAny', Customer::class);

        return view('customers.index', [
            'customers' => $directory->paginate($request),
            'search' => trim((string) $request->query('q', '')),
        ]);
    }

    public function create(CurrentShop $currentShop): View
    {
        Gate::authorize('create', Customer::class);

        $shop = $currentShop->require();

        return view('customers.form', [
            'customer' => null,
            'locationDefaults' => [
                'state_code' => $shop->state_code,
                'district_code' => $shop->district_code,
                'subdistrict_code' => $shop->subdistrict_code,
                'village_code' => $shop->village_code,
            ],
        ]);
    }

    public function store(CustomerRequest $request, SaveCustomer $save): RedirectResponse
    {
        $customer = $save->create(
            $request->user(),
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()->route('customers.show', $customer)
            ->with('status', 'ग्राहक सफलतापूर्वक जोड़ दिया गया।');
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

    public function edit(int $customer): View
    {
        $customer = Customer::query()->findOrFail($customer);
        Gate::authorize('update', $customer);

        return view('customers.form', [
            'customer' => $customer,
            'locationDefaults' => [],
        ]);
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

        return redirect()->route('customers.show', $customer)
            ->with('status', 'ग्राहक की जानकारी अपडेट हो गई है।');
    }
}
