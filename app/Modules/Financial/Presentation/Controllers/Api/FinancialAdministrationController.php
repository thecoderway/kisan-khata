<?php

namespace App\Modules\Financial\Presentation\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerInterestPolicyVersion;
use App\Models\InterestPolicy;
use App\Models\InterestPolicyVersion;
use App\Modules\Financial\Application\ReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FinancialAdministrationController extends Controller
{
    public function shopPolicy(): JsonResponse
    {
        Gate::authorize('update', InterestPolicy::class);

        $policy = InterestPolicy::query()->where('policy_code', 'DEFAULT')->firstOrFail();

        return response()->json([
            'data' => $policy->versions()->orderByDesc('version_number')->get(),
        ]);
    }

    public function updateShopPolicy(Request $request): JsonResponse
    {
        Gate::authorize('update', InterestPolicy::class);

        $data = $request->validate([
            'effective_from' => ['required', 'date_format:Y-m-d', 'after:today'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'monthly_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:365'],
            'capitalization_enabled' => ['required', 'boolean'],
            'payment_waterfall' => ['required', 'in:PRINCIPAL_FIRST,INTEREST_FIRST'],
            'annual_settlement_month_1' => ['required', 'integer', 'between:1,12'],
            'annual_settlement_day_1' => ['required', 'integer', 'between:1,31'],
            'annual_settlement_month_2' => ['required', 'integer', 'between:1,12'],
            'annual_settlement_day_2' => ['required', 'integer', 'between:1,31'],
        ]);

        foreach ([1, 2] as $slot) {
            $month = (int) $data['annual_settlement_month_'.$slot];
            $day = (int) $data['annual_settlement_day_'.$slot];

            // Leap year keeps 29 February valid as a recurring calendar date.
            if (! checkdate($month, $day, 2000)) {
                throw ValidationException::withMessages([
                    'annual_settlement_day_'.$slot => 'Settlement day must be a valid calendar date for the selected month.',
                ]);
            }
        }

        $version = DB::transaction(function () use ($request, $data) {
            $policy = InterestPolicy::query()->where('policy_code', 'DEFAULT')->lockForUpdate()->firstOrFail();
            $open = InterestPolicyVersion::query()->where('interest_policy_id', $policy->getKey())
                ->whereNull('effective_to')->lockForUpdate()->first();
            $previousVersion = $open?->toArray();

            $effectiveFrom = CarbonImmutable::createFromFormat('!Y-m-d', $data['effective_from']);
            if ($open !== null && $effectiveFrom->lessThanOrEqualTo($open->effective_from)) {

                throw ValidationException::withMessages(['effective_from' => 'नई policy की तारीख मौजूदा policy के effective start से आगे होनी चाहिए।']);
            }

            $overlap = InterestPolicyVersion::query()
                ->where('interest_policy_id', $policy->getKey())
                ->where('effective_from', '<=', $effectiveFrom->toDateString())
                ->where(function ($query) use ($effectiveFrom): void {
                    $query->whereNull('effective_to')->orWhere('effective_to', '>', $effectiveFrom->toDateString());
                })
                ->when($open !== null, fn ($query) => $query->where('id', '!=', $open->getKey()))
                ->exists();

            if ($overlap) {

                throw ValidationException::withMessages(['effective_from' => 'यह तारीख किसी मौजूदा Shop policy version की अवधि से overlap करती है।']);
            }

            if ($open !== null) {
                $open->effective_to = $effectiveFrom->toDateString();
                $open->save();
            }

            $version = InterestPolicyVersion::query()->create([
                'interest_policy_id' => $policy->getKey(),
                'version_number' => ((int) InterestPolicyVersion::query()->where('interest_policy_id', $policy->getKey())->max('version_number')) + 1,
                ...$data,
                'created_by' => $request->user()->getKey(),
            ]);

            AuditLog::query()->create([
                'user_id' => $request->user()->getKey(),
                'action' => 'financial.shop_interest_policy_updated',
                'entity_type' => 'interest_policy_version',
                'entity_id' => $version->getKey(),
                'reason' => trim($data['reason']),
                'old_values' => $previousVersion === null ? null : ['previous_version' => $previousVersion],
                'new_values' => $version->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);

            return $version;
        }, 3);

        return response()->json(['data' => $version], 201);
    }

    public function customerPolicy(Request $request, int $customer): JsonResponse
    {
        $customer = Customer::query()->findOrFail($customer);
        Gate::authorize('viewCustomer', [InterestPolicy::class, $customer]);

        return response()->json([
            'data' => CustomerInterestPolicyVersion::query()->where('customer_id', $customer->getKey())
                ->orderByDesc('version_number')->get(),
        ]);
    }

    public function updateCustomerPolicy(Request $request, int $customer): JsonResponse
    {
        $customer = Customer::query()->findOrFail($customer);
        Gate::authorize('updateCustomer', [InterestPolicy::class, $customer]);

        $data = $request->validate([
            'effective_from' => ['required', 'date_format:Y-m-d', 'after:today'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'monthly_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'capitalization_enabled' => ['nullable', 'boolean'],
        ]);
        if (($data['monthly_rate_percent'] ?? null) === null && ($data['capitalization_enabled'] ?? null) === null) {

            throw ValidationException::withMessages(['monthly_rate_percent' => 'कम से कम एक Customer override आवश्यक है।']);
        }

        $version = DB::transaction(function () use ($request, $customer, $data) {
            $locked = Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $open = CustomerInterestPolicyVersion::query()->where('customer_id', $locked->getKey())
                ->whereNull('effective_to')->lockForUpdate()->first();
            $previousVersion = $open?->toArray();
            $effectiveFrom = CarbonImmutable::createFromFormat('!Y-m-d', $data['effective_from']);

            if ($open !== null && $effectiveFrom->lessThanOrEqualTo($open->effective_from)) {

                throw ValidationException::withMessages(['effective_from' => 'नई override तारीख मौजूदा override के effective start से आगे होनी चाहिए।']);
            }
            $overlap = CustomerInterestPolicyVersion::query()
                ->where('customer_id', $locked->getKey())
                ->where('effective_from', '<=', $effectiveFrom->toDateString())
                ->where(function ($query) use ($effectiveFrom): void {
                    $query->whereNull('effective_to')->orWhere('effective_to', '>', $effectiveFrom->toDateString());
                })
                ->when($open !== null, fn ($query) => $query->where('id', '!=', $open->getKey()))
                ->exists();

            if ($overlap) {

                throw ValidationException::withMessages(['effective_from' => 'यह तारीख किसी मौजूदा Customer override की अवधि से overlap करती है।']);
            }

            if ($open !== null) {
                $open->effective_to = $effectiveFrom->toDateString();
                $open->save();
            }

            $version = CustomerInterestPolicyVersion::query()->create([
                'customer_id' => $locked->getKey(),
                'version_number' => ((int) CustomerInterestPolicyVersion::query()->where('customer_id', $locked->getKey())->max('version_number')) + 1,
                'effective_from' => $effectiveFrom->toDateString(),
                'monthly_rate_percent' => $data['monthly_rate_percent'] ?? null,
                'capitalization_enabled' => $data['capitalization_enabled'] ?? null,
                'created_by' => $request->user()->getKey(),
            ]);

            AuditLog::query()->create([
                'user_id' => $request->user()->getKey(),
                'action' => 'financial.customer_interest_policy_updated',
                'entity_type' => 'customer_interest_policy_version',
                'entity_id' => $version->getKey(),
                'reason' => trim($data['reason']),
                'old_values' => $previousVersion === null ? null : ['previous_version' => $previousVersion],
                'new_values' => $version->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);

            return $version;
        }, 3);

        return response()->json(['data' => $version], 201);
    }

    public function auditHistory(Request $request): JsonResponse
    {
        Gate::authorize('viewFinancialAudit');

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'action' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = AuditLog::query()->where('action', 'like', 'financial.%')
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->when(isset($filters['action']), fn ($q) => $q->where('action', $filters['action']))
            ->with('user:id,name')->orderByDesc('created_at')->orderByDesc('id');

        return response()->json($query->paginate((int) ($filters['per_page'] ?? 20)));
    }

    public function repairCustomer(Request $request, int $customer, ReconciliationService $reconciliation): JsonResponse
    {
        $customer = Customer::query()->findOrFail($customer);
        Gate::authorize('repairCustomerProjection', $customer);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        $result = DB::transaction(function () use ($request, $customer, $reconciliation, $data) {
            $before = $reconciliation->reconcileCustomer($customer);
            if ($before['consistent']) {

                return ['report' => $before, 'repaired' => false];
            }
            $after = $reconciliation->rebuildAndReconcile($customer);
            AuditLog::query()->create([
                'user_id' => $request->user()->getKey(),
                'action' => 'financial.customer_projection_repaired',
                'entity_type' => 'customer_projection',
                'entity_id' => $customer->getKey(),
                'reason' => trim($data['reason']),
                'old_values' => ['consistent' => $before['consistent'], 'discrepancies' => $before['discrepancies']],
                'new_values' => ['consistent' => $after['consistent'], 'discrepancies' => $after['discrepancies']],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);

            return ['report' => $after, 'repaired' => true];
        }, 3);

        return response()->json([
            'message' => $result['repaired'] ? 'Projection repaired and audit event recorded.' : 'Projection is already consistent; no repair was needed.',
            'data' => $result['report'],
            'repaired' => $result['repaired'],
        ]);
    }
}
