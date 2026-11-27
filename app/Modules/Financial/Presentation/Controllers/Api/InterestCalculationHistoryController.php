<?php

namespace App\Modules\Financial\Presentation\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InterestCalculationRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InterestCalculationHistoryController extends Controller
{
    public function index(Request $request, int $customer): JsonResponse
    {
        $customer = Customer::withTrashed()->findOrFail($customer);
        Gate::authorize('viewInterestCalculationHistory', $customer);

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'trigger_type' => ['nullable', Rule::in(['CUSTOMER_PAYMENT', 'ANNUAL_AUTO_SETTLEMENT'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $runs = InterestCalculationRun::query()
            ->where('customer_id', $customer->getKey())
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('cutoff_date_exclusive', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('cutoff_date_exclusive', '<=', $filters['to']))
            ->when(isset($filters['trigger_type']), fn ($query) => $query->where('trigger_type', $filters['trigger_type']))
            ->withCount('items')
            ->withSum('items', 'posted_interest')
            ->with([
                'triggerPayment:id,payment_number,payment_date,amount',
                'settlementRun:id,settlement_date,status',
            ])
            ->orderByDesc('cutoff_date_exclusive')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 20));

        return response()->json([
            'data' => $runs->getCollection()->map(fn (InterestCalculationRun $run): array => [
                'id' => (int) $run->getKey(),
                'trigger_type' => $run->trigger_type,
                'status' => $run->status,
                'cutoff_date_exclusive' => $run->cutoff_date_exclusive?->toDateString(),
                'started_at' => $run->started_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
                'items_count' => (int) $run->items_count,
                'posted_interest_total' => (string) ($run->items_sum_posted_interest ?? '0.00'),
                'payment' => $run->triggerPayment ? [
                    'id' => (int) $run->triggerPayment->getKey(),
                    'number' => $run->triggerPayment->payment_number,
                    'date' => $run->triggerPayment->payment_date?->toDateString(),
                    'amount' => (string) $run->triggerPayment->amount,
                ] : null,
                'settlement' => $run->settlementRun ? [
                    'id' => (int) $run->settlementRun->getKey(),
                    'date' => $run->settlementRun->settlement_date?->toDateString(),
                    'status' => $run->settlementRun->status,
                ] : null,
            ])->values(),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
                'last_page' => $runs->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $customer, int $run): JsonResponse
    {
        $customer = Customer::withTrashed()->findOrFail($customer);
        Gate::authorize('viewInterestCalculationHistory', $customer);

        $calculationRun = InterestCalculationRun::query()
            ->where('customer_id', $customer->getKey())
            ->with([
                'triggerPayment:id,payment_number,payment_date,amount',
                'settlementRun:id,settlement_date,status',
                'items.sale:id,sale_number,sale_date,amount',
                'items.shopPolicyVersion:id,version_number,effective_from,effective_to,monthly_rate_percent,grace_days,capitalization_enabled,payment_waterfall',
                'items.customerPolicyVersion:id,version_number,effective_from,effective_to,monthly_rate_percent,capitalization_enabled',
                'items.ledgerEntry:id,effective_date,component,entry_kind,debit_amount,credit_amount',
                'items.sourceInterestLot:id,lot_type,lot_date,original_amount',
            ])
            ->findOrFail($run);

        return response()->json([
            'data' => [
                'id' => (int) $calculationRun->getKey(),
                'trigger_type' => $calculationRun->trigger_type,
                'status' => $calculationRun->status,
                'cutoff_date_exclusive' => $calculationRun->cutoff_date_exclusive?->toDateString(),
                'started_at' => $calculationRun->started_at?->toIso8601String(),
                'completed_at' => $calculationRun->completed_at?->toIso8601String(),
                'payment' => $calculationRun->triggerPayment ? [
                    'id' => (int) $calculationRun->triggerPayment->getKey(),
                    'number' => $calculationRun->triggerPayment->payment_number,
                    'date' => $calculationRun->triggerPayment->payment_date?->toDateString(),
                    'amount' => (string) $calculationRun->triggerPayment->amount,
                ] : null,
                'settlement' => $calculationRun->settlementRun ? [
                    'id' => (int) $calculationRun->settlementRun->getKey(),
                    'date' => $calculationRun->settlementRun->settlement_date?->toDateString(),
                    'status' => $calculationRun->settlementRun->status,
                ] : null,
                'items' => $calculationRun->items->map(fn ($item): array => [
                    'id' => (int) $item->getKey(),
                    'sale' => $item->sale ? [
                        'id' => (int) $item->sale->getKey(),
                        'number' => $item->sale->sale_number,
                        'date' => $item->sale->sale_date?->toDateString(),
                        'amount' => (string) $item->sale->amount,
                    ] : null,
                    'interval_start_date' => $item->interval_start_date?->toDateString(),
                    'interval_end_date_exclusive' => $item->interval_end_date_exclusive?->toDateString(),
                    'actual_days' => (int) $item->actual_days,
                    'grace_days_applied' => (int) $item->grace_days_applied,
                    'monthly_rate_percent' => (string) $item->monthly_rate_percent,
                    'eligible_principal_basis' => (string) $item->eligible_principal_basis,
                    'unrounded_interest' => (string) $item->unrounded_interest,
                    'posted_interest' => (string) $item->posted_interest,
                    'capitalization_enabled' => (bool) $item->capitalization_enabled,
                    'shop_policy' => $item->shopPolicyVersion ? [
                        'version' => (int) $item->shopPolicyVersion->version_number,
                        'effective_from' => $item->shopPolicyVersion->effective_from?->toDateString(),
                        'effective_to' => $item->shopPolicyVersion->effective_to?->toDateString(),
                        'monthly_rate_percent' => (string) $item->shopPolicyVersion->monthly_rate_percent,
                        'capitalization_enabled' => (bool) $item->shopPolicyVersion->capitalization_enabled,
                        'grace_days' => (int) $item->shopPolicyVersion->grace_days,
                        'payment_waterfall' => $item->shopPolicyVersion->payment_waterfall,
                    ] : null,
                    'customer_policy' => $item->customerPolicyVersion ? [
                        'version' => (int) $item->customerPolicyVersion->version_number,
                        'effective_from' => $item->customerPolicyVersion->effective_from?->toDateString(),
                        'effective_to' => $item->customerPolicyVersion->effective_to?->toDateString(),
                        'monthly_rate_percent' => $item->customerPolicyVersion->monthly_rate_percent === null ? null : (string) $item->customerPolicyVersion->monthly_rate_percent,
                        'capitalization_enabled' => $item->customerPolicyVersion->capitalization_enabled === null ? null : (bool) $item->customerPolicyVersion->capitalization_enabled,
                    ] : null,
                    'ledger_entry' => $item->ledgerEntry ? [
                        'id' => (int) $item->ledgerEntry->getKey(),
                        'date' => $item->ledgerEntry->effective_date?->toDateString(),
                        'component' => $item->ledgerEntry->component,
                        'entry_kind' => $item->ledgerEntry->entry_kind,
                        'debit' => (string) $item->ledgerEntry->debit_amount,
                        'credit' => (string) $item->ledgerEntry->credit_amount,
                    ] : null,
                    'source_interest_lot' => $item->sourceInterestLot ? [
                        'id' => (int) $item->sourceInterestLot->getKey(),
                        'type' => $item->sourceInterestLot->lot_type,
                        'date' => $item->sourceInterestLot->lot_date?->toDateString(),
                        'original_amount' => (string) $item->sourceInterestLot->original_amount,
                    ] : null,
                ])->values(),
            ],
        ]);
    }
}
