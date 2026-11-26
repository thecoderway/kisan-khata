<?php

namespace App\Modules\Financial\Application;

use App\Models\Customer;
use App\Tenancy\CurrentShop;
use DateTimeInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Resolves effective-dated shop and customer interest settings.
 *
 * Customer rate and capitalization overrides are resolved independently.
 * Historical policy rows are read-only inputs; this service never mutates them.
 */
class InterestPolicyResolver
{
    public const DEFAULT_POLICY_CODE = 'DEFAULT';

    public function __construct(private readonly CurrentShop $currentShop) {}

    /**
     * @return array{
     *   shop_policy_version_id: int,
     *   customer_policy_version_id: int|null,
     *   monthly_rate_percent: string,
     *   grace_days: int,
     *   capitalization_enabled: bool,
     *   payment_waterfall: string,
     *   annual_settlement_month_1: int,
     *   annual_settlement_day_1: int,
     *   annual_settlement_month_2: int,
     *   annual_settlement_day_2: int
     * }
     */
    public function resolve(Customer $customer, DateTimeInterface $effectiveDate): array
    {
        $shopId = (int) $this->currentShop->require()->getKey();

        if ((int) $customer->shop_id !== $shopId || $customer->getKey() === null) {
            throw new AuthorizationException('The customer does not belong to the current shop.');
        }

        $date = $effectiveDate->format('Y-m-d');

        $policy = DB::table('interest_policies')
            ->where('shop_id', $shopId)
            ->where('policy_code', self::DEFAULT_POLICY_CODE)
            ->first();

        if ($policy === null) {
            throw new DomainException('The shop default interest policy has not been configured.');
        }

        $shopVersions = DB::table('interest_policy_versions')
            ->where('shop_id', $shopId)
            ->where('interest_policy_id', $policy->id)
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $date);
            })
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($shopVersions->count() !== 1) {
            throw new DomainException(
                $shopVersions->isEmpty()
                    ? 'No shop interest policy version is effective on the requested date.'
                    : 'Multiple shop interest policy versions overlap on the requested date.'
            );
        }

        $shopVersion = $shopVersions->first();

        $customerVersions = DB::table('customer_interest_policy_versions')
            ->where('shop_id', $shopId)
            ->where('customer_id', $customer->getKey())
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $date);
            })
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($customerVersions->count() > 1) {
            throw new DomainException('Multiple customer interest policy versions overlap on the requested date.');
        }

        $customerVersion = $customerVersions->first();

        return [
            'shop_policy_version_id' => (int) $shopVersion->id,
            'customer_policy_version_id' => $customerVersion === null ? null : (int) $customerVersion->id,
            'monthly_rate_percent' => (string) ($customerVersion?->monthly_rate_percent ?? $shopVersion->monthly_rate_percent),
            'grace_days' => (int) $shopVersion->grace_days,
            'capitalization_enabled' => (bool) ($customerVersion?->capitalization_enabled ?? $shopVersion->capitalization_enabled),
            'payment_waterfall' => (string) $shopVersion->payment_waterfall,
            'annual_settlement_month_1' => (int) $shopVersion->annual_settlement_month_1,
            'annual_settlement_day_1' => (int) $shopVersion->annual_settlement_day_1,
            'annual_settlement_month_2' => (int) $shopVersion->annual_settlement_month_2,
            'annual_settlement_day_2' => (int) $shopVersion->annual_settlement_day_2,
        ];
    }
}
