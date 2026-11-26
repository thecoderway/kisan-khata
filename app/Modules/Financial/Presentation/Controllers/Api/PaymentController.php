<?php

namespace App\Modules\Financial\Presentation\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Modules\Financial\Application\PaymentPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = Payment::query()->with('customer:id,name_original,name_hi,name_en')
            ->whereNotNull('posted_at')
            ->when(isset($filters['customer_id']), fn ($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('payment_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('payment_date', '<=', $filters['to']))
            ->orderByDesc('payment_date')->orderByDesc('id');

        return response()->json($query->paginate((int) ($filters['per_page'] ?? 20)));
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

        return response()->json(['data' => $payment->fresh()], 201);
    }

    public function show(int $payment): JsonResponse
    {
        $payment = Payment::query()->with(['customer:id,name_original,name_hi,name_en', 'allocation.items'])
            ->findOrFail($payment);
        Gate::authorize('view', $payment);

        return response()->json(['data' => $payment]);
    }
}
