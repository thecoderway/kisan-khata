<?php

namespace App\Modules\Financial\Presentation\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;
use App\Modules\Financial\Application\SalePostingService;
use App\Modules\Financial\Application\SaleRevisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Sale::class);

        $filters = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['date_desc', 'date_asc', 'amount_desc', 'amount_asc'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'payment_status' => ['nullable', Rule::in([
                Sale::PAYMENT_STATUS_UNPAID,
                Sale::PAYMENT_STATUS_PARTIALLY_PAID,
                Sale::PAYMENT_STATUS_PAID,
            ])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Sale::query()->with('customer:id,name_original,name_hi,name_en,status,deleted_at')
            ->whereNotNull('posted_at')
            ->when(isset($filters['customer_id']), fn ($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('sale_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('sale_date', '<=', $filters['to']))
            ->when(isset($filters['payment_status']), fn ($q) => $q->where('payment_status', $filters['payment_status']))
            ->when(isset($filters['q']), function ($query) use ($filters): void {
                $term = '%'.trim($filters['q']).'%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('sale_number', 'like', $term)
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name_original', 'like', $term)
                            ->orWhere('name_hi', 'like', $term)->orWhere('name_en', 'like', $term)->orWhere('mobile', 'like', $term));
                });
            });

        match ($filters['sort'] ?? 'date_desc') {
            'date_asc' => $query->orderBy('sale_date')->orderBy('id'),
            'amount_desc' => $query->orderByDesc('amount')->orderByDesc('id'),
            'amount_asc' => $query->orderBy('amount')->orderBy('id'),
            default => $query->orderByDesc('sale_date')->orderByDesc('id'),
        };

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 20));

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request, SalePostingService $posting): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'payment_choice' => ['required', Rule::in([Sale::PAYMENT_CHOICE_PAID, Sale::PAYMENT_CHOICE_UNPAID])],
            'sale_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'regex:/^\\d{1,12}(?:\\.\\d{1,2})?$/'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $key = trim((string) $request->header('Idempotency-Key'));

        abort_if($key === '' || mb_strlen($key) > 255, 422, 'A valid Idempotency-Key header is required.');

        $customer = Customer::query()->findOrFail($data['customer_id']);
        Gate::authorize('view', $customer);

        $sale = $posting->post(
            $request->user(),
            $customer,
            $data['payment_choice'],
            new \DateTimeImmutable($data['sale_date']),
            $data['amount'],
            $data['description'] ?? null,
            $key,
        );

        return response()->json(['success' => true, 'message' => 'बिक्री सफलतापूर्वक पोस्ट हुई।', 'data' => $sale->fresh()], 201);
    }

    public function revise(Request $request, int $sale, SaleRevisionService $revisions): JsonResponse
    {
        $sale = Sale::query()->findOrFail($sale);
        Gate::authorize('revise', $sale);

        $reason = $request->input('reason');
        if (is_string($reason)) {
            $request->merge(['reason' => trim($reason)]);
        }

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $key = trim((string) $request->header('Idempotency-Key'));
        abort_if($key === '' || mb_strlen($key) > 255, 422, 'A valid Idempotency-Key header is required.');

        $revision = $revisions->reviseDescription(
            $request->user(),
            $sale,
            $data['description'] ?? null,
            $data['reason'],
            $key,
        );

        return response()->json(['success' => true, 'message' => 'बिक्री विवरण संशोधित हो गया है।', 'data' => $revision->fresh()], 201);
    }

    public function show(int $sale): JsonResponse
    {
        $sale = Sale::query()->with([
            'customer:id,name_original,name_hi,name_en,status,deleted_at',
            'saleDayPayment',
            'allocationItems.payment',
            'ledgerEntries',
            'financialTransaction',
            'revisions.actor:id,name',
        ])->findOrFail($sale);
        Gate::authorize('view', $sale);

        return response()->json(['data' => $sale]);
    }
}
