<?php

namespace App\Modules\Financial\Presentation\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LedgerEntry;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerLedgerController extends Controller
{
    public function show(Request $request, int $customer): JsonResponse
    {
        $customer = Customer::withTrashed()->findOrFail($customer);
        abort_unless(! $customer->trashed() || $customer->hasFinancialHistory(), 404);
        Gate::authorize('viewCustomerLedger', $customer);

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $base = LedgerEntry::query()
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey());

        if (isset($filters['from'])) {
            $base->where('effective_date', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $base->where('effective_date', '<=', $filters['to']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);
        $entries = (clone $base)
            ->orderBy('effective_date')
            ->orderBy('sequence_number')
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get();

        $openingQuery = LedgerEntry::query()
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey());

        if ($entries->isNotEmpty()) {
            $first = $entries->first();
            $openingQuery->where(function ($query) use ($first): void {
                $query->where('effective_date', '<', $first->effective_date)
                    ->orWhere(function ($sameDate) use ($first): void {
                        $sameDate->where('effective_date', $first->effective_date)
                            ->where(function ($sequence) use ($first): void {
                                $sequence->where('sequence_number', '<', $first->sequence_number)
                                    ->orWhere(function ($id) use ($first): void {
                                        $id->where('sequence_number', $first->sequence_number)
                                            ->where('id', '<', $first->getKey());
                                    });
                            });
                    });
            });
        } elseif (isset($filters['from'])) {
            $openingQuery->where('effective_date', '<', $filters['from']);
        }

        $principalQuery = (clone $openingQuery)->where('component', 'PRINCIPAL');
        $interestQuery = (clone $openingQuery)->where('component', 'INTEREST');
        $principal = BigDecimal::of((string) $principalQuery->sum('debit_amount'))
            ->minus((string) $principalQuery->sum('credit_amount'));
        $interest = BigDecimal::of((string) $interestQuery->sum('debit_amount'))
            ->minus((string) $interestQuery->sum('credit_amount'));

        $runningPrincipal = $principal;
        $runningInterest = $interest;
        $items = [];

        foreach ($entries as $entry) {
            $delta = BigDecimal::of((string) $entry->debit_amount)
                ->minus((string) $entry->credit_amount);

            if ($entry->component === 'PRINCIPAL') {
                $runningPrincipal = $runningPrincipal->plus($delta);
            } else {
                $runningInterest = $runningInterest->plus($delta);
            }

            $items[] = [
                'id' => (int) $entry->getKey(),
                'effective_date' => $entry->effective_date->toDateString(),
                'entry_kind' => $entry->entry_kind,
                'component' => $entry->component,
                'sale_id' => $entry->sale_id,
                'payment_id' => $entry->payment_id,
                'debit' => (string) $entry->debit_amount,
                'credit' => (string) $entry->credit_amount,
                'principal_balance' => (string) $runningPrincipal->toScale(2),
                'interest_balance' => (string) $runningInterest->toScale(2),
                'total_balance' => (string) $runningPrincipal->plus($runningInterest)->toScale(2),
            ];
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => (clone $base)->count(),
                'opening_balance' => [
                    'principal' => (string) $principal->toScale(2),
                    'interest' => (string) $interest->toScale(2),
                    'total' => (string) $principal->plus($interest)->toScale(2),
                ],
            ],
        ]);
    }
}
