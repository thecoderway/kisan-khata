<?php

namespace App\Modules\Customers\Presentation\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Modules\Customers\Application\CustomerDirectoryQuery;
use App\Modules\Customers\Application\SaveCustomer;
use App\Modules\Customers\Presentation\Requests\CustomerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function index(Request $request, CustomerDirectoryQuery $directory): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Customer::class);
        if ($request->query('status') === 'deleted') {
            Gate::authorize('viewDeleted', Customer::class);
        }

        return CustomerResource::collection($directory->paginate($request));
    }

    public function store(CustomerRequest $request, SaveCustomer $save): JsonResponse
    {
        $customer = $save->create(
            $request->user(),
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $customer): CustomerResource
    {
        $customer = Customer::query()->findOrFail($customer);
        Gate::authorize('view', $customer);

        return new CustomerResource($customer);
    }

    public function update(CustomerRequest $request, int $customer, SaveCustomer $save): CustomerResource
    {
        $customer = Customer::query()->findOrFail($customer);
        $customer = $save->update(
            $request->user(),
            $customer,
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        return new CustomerResource($customer);
    }
}
