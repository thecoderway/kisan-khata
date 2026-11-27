<?php

namespace App\Modules\Financial\Presentation\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LedgerEntry;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        // Keep the immutable allocation-level ledger intact. Consolidation is a
        // read-model concern: one posted Payment/Reversal becomes one display row.
        $entries = LedgerEntry::query()
            ->with('payment:id,payment_number,payment_type,payment_method,description')
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey())
            ->orderBy('effective_date')
            ->orderBy('sequence_number')
            ->orderBy('id')
            ->get();

        $groups = [];
        foreach ($entries as $entry) {
            $isPayment = $entry->payment_id !== null
                && in_array($entry->entry_kind, ['PAYMENT', 'REVERSAL'], true);
            $groupKey = $isPayment
                ? $entry->entry_kind.':'.$entry->payment_id
                : 'ledger:'.$entry->getKey();

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'first' => $entry,
                    'entries' => [],
                    'principal_debit' => BigDecimal::zero(),
                    'principal_credit' => BigDecimal::zero(),
                    'interest_debit' => BigDecimal::zero(),
                    'interest_credit' => BigDecimal::zero(),
                    'debit' => BigDecimal::zero(),
                    'credit' => BigDecimal::zero(),
                ];
            }

            $group = &$groups[$groupKey];
            $group['entries'][] = $entry;
            $debit = BigDecimal::of((string) $entry->debit_amount);
            $credit = BigDecimal::of((string) $entry->credit_amount);
            $group['debit'] = $group['debit']->plus($debit);
            $group['credit'] = $group['credit']->plus($credit);

            if ($entry->component === 'PRINCIPAL') {
                $group['principal_debit'] = $group['principal_debit']->plus($debit);
                $group['principal_credit'] = $group['principal_credit']->plus($credit);
            } else {
                $group['interest_debit'] = $group['interest_debit']->plus($debit);
                $group['interest_credit'] = $group['interest_credit']->plus($credit);
            }
            unset($group);
        }

        $runningPrincipal = BigDecimal::zero();
        $runningInterest = BigDecimal::zero();
        $displayRows = [];

        foreach ($groups as $group) {
            $first = $group['first'];
            $openingPrincipal = $runningPrincipal;
            $openingInterest = $runningInterest;

            $runningPrincipal = $runningPrincipal
                ->plus($group['principal_debit'])
                ->minus($group['principal_credit']);
            $runningInterest = $runningInterest
                ->plus($group['interest_debit'])
                ->minus($group['interest_credit']);

            $rawEntries = $group['entries'];
            $payment = $first->payment;
            $components = collect($rawEntries)->pluck('component')->unique()->values();
            $saleIds = collect($rawEntries)->pluck('sale_id')->filter()->unique()->values();

            $displayRows[] = [
                'id' => (int) $first->getKey(),
                'effective_date' => $first->effective_date->toDateString(),
                'entry_kind' => count($rawEntries) > 1 && $first->payment_id !== null
                    ? ($first->entry_kind === 'REVERSAL' ? 'PAYMENT_REVERSAL' : 'PAYMENT')
                    : $first->entry_kind,
                'component' => $components->count() > 1 ? 'MIXED' : $first->component,
                'sale_id' => $saleIds->count() === 1 ? (int) $saleIds->first() : null,
                'sale_ids' => $saleIds->map(fn ($id): int => (int) $id)->all(),
                'ledger_entry_ids' => collect($rawEntries)->map(fn (LedgerEntry $item): int => (int) $item->getKey())->all(),
                'payment_id' => $first->payment_id,
                'payment_number' => $payment?->payment_number,
                'payment_type' => $payment?->payment_type,
                'payment_method' => $payment?->payment_method,
                'description' => $payment?->description,
                'allocation_count' => count($rawEntries),
                'debit' => (string) $group['debit']->toScale(2),
                'credit' => (string) $group['credit']->toScale(2),
                'principal_debit' => (string) $group['principal_debit']->toScale(2),
                'principal_credit' => (string) $group['principal_credit']->toScale(2),
                'interest_debit' => (string) $group['interest_debit']->toScale(2),
                'interest_credit' => (string) $group['interest_credit']->toScale(2),
                'opening_principal' => (string) $openingPrincipal->toScale(2),
                'opening_interest' => (string) $openingInterest->toScale(2),
                'principal_balance' => (string) $runningPrincipal->toScale(2),
                'interest_balance' => (string) $runningInterest->toScale(2),
                'total_balance' => (string) $runningPrincipal->plus($runningInterest)->toScale(2),
                '_sort_date' => $first->effective_date->toDateString(),
            ];
        }

        $filtered = collect($displayRows)
            ->when(isset($filters['from']), fn (Collection $rows) => $rows->where('_sort_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Collection $rows) => $rows->where('_sort_date', '<=', $filters['to']))
            ->values();

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);
        $total = $filtered->count();
        $pageRows = $filtered->forPage($page, $perPage)->values();
        $opening = $pageRows->first();

        $data = $pageRows->map(function (array $row): array {
            unset($row['_sort_date'], $row['opening_principal'], $row['opening_interest']);

            return $row;
        })->all();

        if ($opening !== null) {
            $openingBalance = [
                'principal' => $opening['opening_principal'],
                'interest' => $opening['opening_interest'],
                'total' => (string) BigDecimal::of($opening['opening_principal'])
                    ->plus($opening['opening_interest'])->toScale(2),
            ];
        } else {
            $openingBalance = [
                'principal' => (string) $runningPrincipal->toScale(2),
                'interest' => (string) $runningInterest->toScale(2),
                'total' => (string) $runningPrincipal->plus($runningInterest)->toScale(2),
            ];
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'opening_balance' => $openingBalance,
            ],
        ]);
    }
}
