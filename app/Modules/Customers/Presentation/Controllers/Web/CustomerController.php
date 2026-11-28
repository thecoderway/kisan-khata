<?php

namespace App\Modules\Customers\Presentation\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Modules\Customers\Application\CustomerDirectoryQuery;
use App\Modules\Customers\Application\SaveCustomer;
use App\Modules\Customers\Presentation\Requests\CustomerRequest;
use App\Tenancy\CurrentShop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request, CustomerDirectoryQuery $directory, CurrentShop $currentShop): View|Response|JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);
        $request->validate(['village_code' => ['nullable', 'string', 'max:20', 'exists:villages,villageCode']]);
        if ($request->query('status') === 'deleted') {
            Gate::authorize('viewDeleted', Customer::class);
        }

        if ($request->expectsJson() && $request->boolean('fragment')) {
            $customers = $directory->paginate($request);
            $search = trim((string) $request->query('q', ''));
            $status = in_array($request->query('status'), ['active', 'inactive', 'deleted'], true)
                ? $request->query('status')
                : 'active';

            return response()->json([
                'html' => view('customers.partials.results', compact('customers', 'search', 'status'))->render(),
                'meta' => [
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                ],
            ]);
        }

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

        $customers = $directory->paginate($request);
        $villageCode = (string) $request->query('village_code', '');
        $selectedVillage = $villageCode !== ''
            ? DB::table('villages')->where('villageCode', $villageCode)->first()
            : null;
        $search = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), ['active', 'inactive', 'deleted'], true)
            ? $request->query('status')
            : 'active';

        return view('customers.index', [
            'customers' => $customers,
            'selectedVillage' => $selectedVillage,
            'villageCode' => $villageCode,
            'search' => $search,
            'status' => $status,
            'drawerAction' => $drawerContent ? $drawerAction : null,
            'drawerCustomer' => $drawerCustomer,
            'drawerContent' => $drawerContent,
        ]);
    }

    public function villageOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20', 'exists:villages,villageCode'],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));
        $selected = isset($validated['code'])
            ? DB::table('villages')->where('villageCode', $validated['code'])->first()
            : null;

        $villages = collect();

        if (mb_strlen($term) >= 2) {
            $pattern = $term.'%';
            $villages = DB::table('villages')
                ->where(function ($query) use ($pattern): void {
                    $query->where('villageNameLocal', 'like', $pattern)
                        ->orWhere('villageNameEnglish', 'like', $pattern)
                        ->orWhere('subdistrictNameLocal', 'like', $pattern)
                        ->orWhere('subdistrictNameEnglish', 'like', $pattern)
                        ->orWhere('districtNameLocal', 'like', $pattern)
                        ->orWhere('districtNameEnglish', 'like', $pattern);
                })
                ->select(
                    'villageCode',
                    'villageNameLocal',
                    'villageNameEnglish',
                    'subdistrictNameLocal',
                    'subdistrictNameEnglish',
                    'districtNameLocal',
                    'districtNameEnglish',
                )
                ->orderBy('villageNameLocal')
                ->orderBy('villageNameEnglish')
                ->limit(50)
                ->get();
        }

        $formatVillage = static fn (object $village): array => [
            'code' => (string) $village->villageCode,
            'label' => collect([
                $village->villageNameLocal ?: $village->villageNameEnglish,
                $village->subdistrictNameLocal ?: $village->subdistrictNameEnglish,
                $village->districtNameLocal ?: $village->districtNameEnglish,
            ])->filter()->implode(', '),
        ];

        return response()->json([
            'data' => $villages->map($formatVillage)->values(),
            'selected' => $selected ? $formatVillage($selected) : null,
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

    public function store(CustomerRequest $request, SaveCustomer $save): JsonResponse|RedirectResponse
    {
        $customer = $save->create(
            $request->user(),
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        $message = 'ग्राहक सफलतापूर्वक जोड़ दिया गया।';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['id' => $customer->getKey()],
            ], 201);
        }

        $redirect = $request->boolean('drawer_context')
            ? redirect()->route('customers.index')
            : redirect()->route('customers.show', $customer);

        return $redirect->with('status', $message);
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

    public function update(CustomerRequest $request, int $customer, SaveCustomer $save): JsonResponse|RedirectResponse
    {
        $customer = Customer::query()->findOrFail($customer);
        $save->update(
            $request->user(),
            $customer,
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        $message = 'ग्राहक की जानकारी अपडेट हो गई है।';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['id' => $customer->getKey()],
            ]);
        }

        $redirect = $request->boolean('drawer_context')
            ? redirect()->route('customers.index')
            : redirect()->route('customers.show', $customer);

        return $redirect->with('status', $message);
    }
}
