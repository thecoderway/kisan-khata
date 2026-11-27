<?php

namespace App\Modules\Financial\Presentation\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Modules\Financial\Application\PaymentPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'sort' => ['nullable', Rule::in(['date_desc', 'date_asc', 'amount_desc', 'amount_asc'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Payment::query()->with('customer:id,name_original,name_hi,name_en,status,deleted_at')
            ->whereNotNull('posted_at')
            ->when(isset($filters['customer_id']), fn ($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('payment_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('payment_date', '<=', $filters['to']))
            ->when(isset($filters['payment_method']), fn ($q) => $q->where('payment_method', $filters['payment_method']))
            ->when(isset($filters['q']), function ($query) use ($filters): void {
                $term = '%'.trim($filters['q']).'%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('payment_number', 'like', $term)
                        ->orWhere('reference_number', 'like', $term)
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name_original', 'like', $term)
                            ->orWhere('name_hi', 'like', $term)->orWhere('name_en', 'like', $term)->orWhere('mobile', 'like', $term));
                });
            });

        match ($filters['sort'] ?? 'date_desc') {
            'date_asc' => $query->orderBy('payment_date')->orderBy('id'),
            'amount_desc' => $query->orderByDesc('amount')->orderByDesc('id'),
            'amount_asc' => $query->orderBy('amount')->orderBy('id'),
            default => $query->orderByDesc('payment_date')->orderByDesc('id'),
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

    public function store(Request $request, PaymentPostingService $posting): JsonResponse
    {
        Gate::authorize('create', Payment::class);

        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'amount' => ['required', 'regex:/^\\d{1,12}(?:\\.\\d{1,2})?$/'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'reference_number' => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $key = trim((string) $request->header('Idempotency-Key'));

        abort_if($key === '' || mb_strlen($key) > 255, 422, 'A valid Idempotency-Key header is required.');

        $customer = Customer::query()->findOrFail($data['customer_id']);
        Gate::authorize('view', $customer);

        $payment = $posting->post(
            $request->user(),
            $customer,
            $data['amount'],
            new \DateTimeImmutable($data['payment_date']),
            $data['payment_method'] ?? null,
            $data['reference_number'] ?? null,
            $data['description'] ?? null,
            $key,
        );

        return response()->json(['success' => true, 'message' => 'भुगतान सफलतापूर्वक पोस्ट हुआ।', 'data' => $payment->fresh()], 201);
    }

    public function show(int $payment): JsonResponse
    {
        $payment = Payment::query()->with([
            'customer:id,name_original,name_hi,name_en,status,deleted_at',
            'sourceSale:id,sale_number,sale_date,amount,payment_status',
            'allocation.items.sale:id,sale_number,sale_date,amount,payment_status',
            'allocation.items.reversedItem:id,payment_id,sale_id,component_type,amount',
            'ledgerEntries',
            'financialTransaction',
            'revisions' => fn ($query) => $query->orderBy('revision_number'),
            'revisions.actor:id,name',
            'revisions.reversalPayment:id,payment_number,payment_type,payment_date,amount',
            'revisions.replacementPayment:id,payment_number,payment_type,payment_date,amount',
            'reversedPayment:id,payment_number,payment_type,payment_date,amount',
            'replacementForPayment:id,payment_number,payment_type,payment_date,amount',
        ])->findOrFail($payment);
        Gate::authorize('view', $payment);

        return response()->json(['data' => $payment]);
    }
}
