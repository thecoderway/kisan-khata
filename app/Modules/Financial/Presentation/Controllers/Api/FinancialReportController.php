<?php

namespace App\Modules\Financial\Presentation\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Tenancy\CurrentShop;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class FinancialReportController extends Controller
{
    public function outstanding(Request $request, CurrentShop $currentShop): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $filters = $request->validate([
            'as_of' => ['required', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:100'],
            'positive_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $shopId = $currentShop->require()->getKey();
        $ledger = DB::table('ledger_entries')
            ->select('customer_id')
            ->selectRaw("SUM(CASE WHEN component = 'PRINCIPAL' THEN debit_amount - credit_amount ELSE 0 END) AS principal_balance")
            ->selectRaw("SUM(CASE WHEN component = 'INTEREST' THEN debit_amount - credit_amount ELSE 0 END) AS interest_balance")
            ->where('shop_id', $shopId)
            ->whereDate('effective_date', '<=', $filters['as_of'])
            ->groupBy('customer_id');

        $query = DB::table('customers as c')
            ->joinSub($ledger, 'balances', fn ($join) => $join->on('balances.customer_id', '=', 'c.id'))
            ->where('c.shop_id', $shopId)
            ->whereNotNull('c.id')
            ->select([
                'c.id as customer_id', 'c.name_original', 'c.name_hi', 'c.name_en',
                'c.mobile', 'c.status', 'c.deleted_at',
            ])
            ->selectRaw('CAST(balances.principal_balance AS DECIMAL(20,2)) AS principal')
            ->selectRaw('CAST(balances.interest_balance AS DECIMAL(20,2)) AS interest')
            ->selectRaw('CAST(balances.principal_balance + balances.interest_balance AS DECIMAL(20,2)) AS total')
            ->when(isset($filters['q']), function (Builder $builder) use ($filters): void {
                $term = '%'.trim($filters['q']).'%';
                $builder->where(function (Builder $nested) use ($term): void {
                    $nested->where('c.name_original', 'like', $term)
                        ->orWhere('c.name_hi', 'like', $term)
                        ->orWhere('c.name_en', 'like', $term)
                        ->orWhere('c.mobile', 'like', $term);
                });
            })
            ->when(($filters['positive_only'] ?? false) === true, fn (Builder $builder) => $builder->whereRaw('(balances.principal_balance + balances.interest_balance) > 0'))
            ->orderBy('c.name_original')->orderBy('c.id');

        $summary = (clone $query)->reorder()->selectRaw('COUNT(*) AS customer_count')
            ->selectRaw('COALESCE(SUM(balances.principal_balance), 0) AS principal_total')
            ->selectRaw('COALESCE(SUM(balances.interest_balance), 0) AS interest_total')
            ->selectRaw('COALESCE(SUM(balances.principal_balance + balances.interest_balance), 0) AS grand_total')
            ->first();

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 20));

        return $this->reportResponse($paginator, [
            'customer_count' => (int) $summary->customer_count,
            'principal_total' => $this->decimal($summary->principal_total),
            'interest_total' => $this->decimal($summary->interest_total),
            'grand_total' => $this->decimal($summary->grand_total),
        ], $filters);
    }

    public function sales(Request $request, CurrentShop $currentShop): JsonResponse
    {
        Gate::authorize('viewAny', Sale::class);
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'customer_id' => ['nullable', 'integer'],
            'payment_status' => ['nullable', Rule::in([
                Sale::PAYMENT_STATUS_UNPAID, Sale::PAYMENT_STATUS_PARTIALLY_PAID, Sale::PAYMENT_STATUS_PAID,
            ])],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = DB::table('sales as s')
            ->join('customers as c', function ($join): void {
                $join->on('c.id', '=', 's.customer_id')->on('c.shop_id', '=', 's.shop_id');
            })
            ->where('s.shop_id', $currentShop->require()->getKey())
            ->whereNotNull('s.posted_at')
            ->select([
                's.id', 's.sale_number', 's.sale_date', 's.description', 's.amount',
                's.payment_status', 's.posted_at', 'c.id as customer_id',
                'c.name_original as customer_name', 'c.name_hi as customer_name_hi',
                'c.name_en as customer_name_en', 'c.mobile as customer_mobile', 'c.deleted_at as customer_deleted_at',
            ])
            ->when(isset($filters['from']), fn (Builder $builder) => $builder->whereDate('s.sale_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $builder) => $builder->whereDate('s.sale_date', '<=', $filters['to']))
            ->when(isset($filters['customer_id']), fn (Builder $builder) => $builder->where('s.customer_id', $filters['customer_id']))
            ->when(isset($filters['payment_status']), fn (Builder $builder) => $builder->where('s.payment_status', $filters['payment_status']))
            ->when(isset($filters['q']), function (Builder $builder) use ($filters): void {
                $term = '%'.trim($filters['q']).'%';
                $builder->where(function (Builder $nested) use ($term): void {
                    $nested->where('s.sale_number', 'like', $term)
                        ->orWhere('c.name_original', 'like', $term)
                        ->orWhere('c.name_hi', 'like', $term)
                        ->orWhere('c.name_en', 'like', $term)
                        ->orWhere('c.mobile', 'like', $term);
                });
            });

        $summary = (clone $query)->reorder()->selectRaw('COUNT(*) AS sale_count')
            ->selectRaw('COALESCE(SUM(s.amount), 0) AS gross_sales')->first();
        $paginator = $query->orderByDesc('s.sale_date')->orderByDesc('s.id')
            ->paginate((int) ($filters['per_page'] ?? 20));

        return $this->reportResponse($paginator, [
            'sale_count' => (int) $summary->sale_count,
            'gross_sales' => $this->decimal($summary->gross_sales),
        ], $filters);
    }

    public function payments(Request $request, CurrentShop $currentShop): JsonResponse
    {
        Gate::authorize('viewAny', Payment::class);
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'customer_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'payment_type' => ['nullable', Rule::in([Payment::TYPE_NORMAL, Payment::TYPE_SALE_DAY_AUTO, Payment::TYPE_REVERSAL])],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $shopId = $currentShop->require()->getKey();
        $query = DB::table('payments as p')
            ->join('customers as c', function ($join): void {
                $join->on('c.id', '=', 'p.customer_id')->on('c.shop_id', '=', 'p.shop_id');
            })
            ->leftJoin('payments as original', function ($join): void {
                $join->on('original.id', '=', 'p.reversal_of_payment_id')->on('original.shop_id', '=', 'p.shop_id');
            })
            ->leftJoin('payments as replacement_for', function ($join): void {
                $join->on('replacement_for.id', '=', 'p.replacement_for_payment_id')->on('replacement_for.shop_id', '=', 'p.shop_id');
            })
            ->where('p.shop_id', $shopId)
            ->whereNotNull('p.posted_at')
            ->select([
                'p.id', 'p.payment_number', 'p.payment_date', 'p.payment_type',
                'p.payment_method', 'p.reference_number', 'p.description', 'p.amount',
                'p.reversal_of_payment_id', 'p.replacement_for_payment_id',
                'original.payment_number as reversed_payment_number',
                'replacement_for.payment_number as replacement_for_payment_number',
                'c.id as customer_id', 'c.name_original as customer_name',
                'c.name_hi as customer_name_hi', 'c.name_en as customer_name_en',
                'c.mobile as customer_mobile', 'c.deleted_at as customer_deleted_at',
            ])
            ->when(isset($filters['from']), fn (Builder $builder) => $builder->whereDate('p.payment_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $builder) => $builder->whereDate('p.payment_date', '<=', $filters['to']))
            ->when(isset($filters['customer_id']), fn (Builder $builder) => $builder->where('p.customer_id', $filters['customer_id']))
            ->when(isset($filters['payment_method']), fn (Builder $builder) => $builder->where('p.payment_method', $filters['payment_method']))
            ->when(isset($filters['payment_type']), fn (Builder $builder) => $builder->where('p.payment_type', $filters['payment_type']))
            ->when(isset($filters['q']), function (Builder $builder) use ($filters): void {
                $term = '%'.trim($filters['q']).'%';
                $builder->where(function (Builder $nested) use ($term): void {
                    $nested->where('p.payment_number', 'like', $term)
                        ->orWhere('p.reference_number', 'like', $term)
                        ->orWhere('c.name_original', 'like', $term)
                        ->orWhere('c.name_hi', 'like', $term)
                        ->orWhere('c.name_en', 'like', $term)
                        ->orWhere('c.mobile', 'like', $term);
                });
            });

        $totals = DB::table('payments as p')->where('p.shop_id', $shopId)->whereNotNull('p.posted_at')
            ->when(isset($filters['from']), fn (Builder $builder) => $builder->whereDate('p.payment_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $builder) => $builder->whereDate('p.payment_date', '<=', $filters['to']))
            ->when(isset($filters['customer_id']), fn (Builder $builder) => $builder->where('p.customer_id', $filters['customer_id']))
            ->when(isset($filters['payment_method']), fn (Builder $builder) => $builder->where('p.payment_method', $filters['payment_method']))
            ->when(isset($filters['q']), function (Builder $builder) use ($filters): void {
                $term = '%'.trim($filters['q']).'%';
                $builder->whereExists(function (Builder $sub) use ($term): void {
                    $sub->selectRaw('1')->from('customers as c')->whereColumn('c.id', 'p.customer_id')
                        ->whereColumn('c.shop_id', 'p.shop_id')
                        ->where(function (Builder $nested) use ($term): void {
                            $nested->where('p.payment_number', 'like', $term)
                                ->orWhere('p.reference_number', 'like', $term)
                                ->orWhere('c.name_original', 'like', $term)
                                ->orWhere('c.name_hi', 'like', $term)
                                ->orWhere('c.name_en', 'like', $term)
                                ->orWhere('c.mobile', 'like', $term);
                        });
                });
            });

        $summaryRows = (clone $totals)->selectRaw("
            COALESCE(SUM(CASE WHEN p.payment_type = 'NORMAL' AND p.replacement_for_payment_id IS NULL THEN p.amount ELSE 0 END), 0) AS normal_receipts,
            COALESCE(SUM(CASE WHEN p.payment_type = 'NORMAL' AND p.replacement_for_payment_id IS NOT NULL THEN p.amount ELSE 0 END), 0) AS unverified_replacements,
            COALESCE(SUM(CASE WHEN p.payment_type = 'REVERSAL' THEN p.amount ELSE 0 END), 0) AS reversals,
            COALESCE(SUM(CASE WHEN p.payment_type = 'SALE_DAY_AUTO' THEN p.amount ELSE 0 END), 0) AS auto_settlements
        ")->first();

        $paginator = $query->orderByDesc('p.payment_date')->orderByDesc('p.id')
            ->paginate((int) ($filters['per_page'] ?? 20));
        $normal = BigDecimal::of((string) $summaryRows->normal_receipts);
        $reversals = BigDecimal::of((string) $summaryRows->reversals);
        $netCollection = $normal->minus($reversals)->toScale(2, RoundingMode::HALF_UP)->__toString();

        return $this->reportResponse($paginator, [
            'normal_receipts' => $this->decimal($summaryRows->normal_receipts),
            'reversals' => $this->decimal($summaryRows->reversals),
            'net_collection' => $this->decimal($netCollection),
            'unverified_replacements' => $this->decimal($summaryRows->unverified_replacements),
            'auto_settlements' => $this->decimal($summaryRows->auto_settlements),
        ], $filters);
    }

    public function interest(Request $request, CurrentShop $currentShop): JsonResponse
    {
        abort_unless((bool) $request->user()?->is_active && $request->user()?->role === User::ROLE_OWNER, 403);

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = DB::table('interest_calculation_items as i')
            ->join('interest_calculation_runs as r', function ($join): void {
                $join->on('r.id', '=', 'i.calculation_run_id')->on('r.shop_id', '=', 'i.shop_id')
                    ->on('r.customer_id', '=', 'i.customer_id');
            })
            ->join('customers as c', function ($join): void {
                $join->on('c.id', '=', 'i.customer_id')->on('c.shop_id', '=', 'i.shop_id');
            })
            ->leftJoin('ledger_entries as le', function ($join): void {
                $join->on('le.id', '=', 'i.ledger_entry_id')->on('le.shop_id', '=', 'i.shop_id')
                    ->on('le.customer_id', '=', 'i.customer_id');
            })
            ->leftJoin('sales as s', function ($join): void {
                $join->on('s.id', '=', 'i.sale_id')->on('s.shop_id', '=', 'i.shop_id')
                    ->on('s.customer_id', '=', 'i.customer_id');
            })
            ->where('i.shop_id', $currentShop->require()->getKey())
            ->where('r.status', 'COMPLETED')
            ->select([
                'i.id', 'i.customer_id', 'c.name_original as customer_name',
                'c.name_hi as customer_name_hi', 'c.name_en as customer_name_en',
                's.sale_number', 's.sale_date', 'r.trigger_type', 'r.status as run_status',
                'r.cutoff_date_exclusive', 'i.interval_start_date',
                'i.interval_end_date_exclusive', 'i.actual_days', 'i.grace_days_applied',
                'i.monthly_rate_percent', 'i.eligible_principal_basis',
                'i.unrounded_interest', 'i.posted_interest', 'i.capitalization_enabled',
                'i.shop_policy_version_id', 'i.customer_policy_version_id',
                'i.ledger_entry_id', 'le.effective_date as posted_date',
            ])
            ->when(isset($filters['from']), fn (Builder $builder) => $builder->whereDate('le.effective_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $builder) => $builder->whereDate('le.effective_date', '<=', $filters['to']))
            ->orderBy('le.effective_date')->orderBy('i.id');

        $summaryQuery = (clone $query)->reorder();
        $summary = $summaryQuery->selectRaw('COUNT(*) AS item_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN i.ledger_entry_id IS NOT NULL THEN i.posted_interest ELSE 0 END), 0) AS posted_interest_total')
            ->first();

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 20));

        return $this->reportResponse($paginator, [
            'item_count' => (int) $summary->item_count,
            'posted_interest_total' => $this->decimal($summary->posted_interest_total),
        ], $filters);
    }

    private function reportResponse($paginator, array $summary, array $filters): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'summary' => $summary,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => $filters,
        ]);
    }

    private function decimal(mixed $value): string
    {
        return BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HALF_UP)->__toString();
    }
}
