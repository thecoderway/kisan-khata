<?php

namespace App\Modules\Financial\Application;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\IdempotencyKey;
use App\Models\InterestSettlementRun;
use App\Models\Shop;
use App\Models\User;
use App\Tenancy\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Throwable;

class AnnualInterestSettlementService
{
    public function __construct(
        private readonly CurrentShop $currentShop,
        private readonly InterestCalculationService $calculations,
    ) {}

    /**
     * Process the previous local calendar day for every active shop.
     *
     * @return array{shops:int, due:int, completed:int, failed:int, skipped:int}
     */
    public function runDueSettlements(): array
    {
        $stats = ['shops' => 0, 'due' => 0, 'completed' => 0, 'failed' => 0, 'skipped' => 0];

        foreach (Shop::query()->where('status', Shop::STATUS_ACTIVE)->orderBy('id')->get() as $shop) {
            $stats['shops']++;

            try {
                $timezone = $shop->timezone ?: config('app.timezone', 'UTC');
                $settlementDate = CarbonImmutable::now($timezone)->subDay()->toDateString();
                $result = $this->settleShopDate($shop, $settlementDate);

                $stats['due'] += $result['due'];
                $stats['completed'] += $result['completed'];
                $stats['failed'] += $result['failed'];
                $stats['skipped'] += $result['skipped'];
            } catch (Throwable $exception) {
                report($exception);
                $stats['failed']++;
            } finally {
                $this->currentShop->clear();
            }
        }

        return $stats;
    }

    /**
     * Run one configured settlement date for a shop.
     *
     * @return array{due:int, completed:int, failed:int, skipped:int}
     */
    public function settleShopDate(Shop $shop, string $settlementDate): array
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $settlementDate, $shop->timezone ?: 'UTC');

        if ($date === false || $date->format('Y-m-d') !== $settlementDate) {
            throw new RuntimeException('The annual settlement date must be a valid YYYY-MM-DD date.');
        }

        $this->currentShop->set($shop);

        try {
            $policy = $this->effectiveShopPolicy((int) $shop->getKey(), $settlementDate);

            if (
                ((int) $policy->annual_settlement_month_1 !== (int) $date->month
                    || (int) $policy->annual_settlement_day_1 !== (int) $date->day)
                && ((int) $policy->annual_settlement_month_2 !== (int) $date->month
                    || (int) $policy->annual_settlement_day_2 !== (int) $date->day)
            ) {
                return ['due' => 0, 'completed' => 0, 'failed' => 0, 'skipped' => 1];
            }

            $actor = User::query()
                ->where('shop_id', $shop->getKey())
                ->where('role', User::ROLE_OWNER)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if ($actor === null) {
                throw new RuntimeException('No active Shop Owner is available to authorize annual interest settlement.');
            }

            $accounts = CustomerAccount::query()
                ->where('shop_id', $shop->getKey())
                ->where('total_payable', '>', 0)
                ->orderBy('customer_id')
                ->get();

            $stats = ['due' => 0, 'completed' => 0, 'failed' => 0, 'skipped' => 0];

            foreach ($accounts as $account) {
                $customer = Customer::query()->find($account->customer_id);

                if ($customer === null) {
                    $stats['skipped']++;
                    continue;
                }

                $stats['due']++;

                try {
                    $run = $this->getOrCreateRun(
                        $shop,
                        $customer,
                        $settlementDate,
                        (int) $policy->id,
                    );

                    if ($run->status !== 'COMPLETED') {
                        if ($run->status === 'FAILED') {
                            $run->forceFill([
                                'status' => 'PENDING',
                                'started_at' => now($shop->timezone ?: 'UTC'),
                                'completed_at' => null,
                            ])->save();
                        }

                        $this->calculations->calculateForSettlement($actor, $customer, $run->fresh());
                    }

                    $this->completeSettlementKey($run->fresh());
                    $stats['completed']++;
                } catch (Throwable $exception) {
                    report($exception);
                    $stats['failed']++;
                }
            }

            return $stats;
        } finally {
            $this->currentShop->clear();
        }
    }

    private function effectiveShopPolicy(int $shopId, string $date): object
    {
        $policy = DB::table('interest_policies')
            ->where('shop_id', $shopId)
            ->where('policy_code', InterestPolicyResolver::DEFAULT_POLICY_CODE)
            ->first();

        if ($policy === null) {
            throw new RuntimeException('The Shop default interest policy is not configured.');
        }

        $versions = DB::table('interest_policy_versions')
            ->where('shop_id', $shopId)
            ->where('interest_policy_id', $policy->id)
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $date);
            })
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($versions->count() !== 1) {
            throw new RuntimeException(
                $versions->isEmpty()
                    ? 'No Shop interest policy is effective on the annual settlement date.'
                    : 'Multiple Shop interest policy versions overlap on the annual settlement date.'
            );
        }

        return $versions->first();
    }

    private function getOrCreateRun(
        Shop $shop,
        Customer $customer,
        string $date,
        int $policyVersionId,
    ): InterestSettlementRun {
        $identity = implode('|', ['ANNUAL_AUTO_SETTLEMENT', $shop->getKey(), $customer->getKey(), $date]);
        $keyHash = hash('sha256', $identity);
        $requestHash = hash('sha256', json_encode([
            'shop_id' => (int) $shop->getKey(),
            'customer_id' => (int) $customer->getKey(),
            'settlement_date' => $date,
            'shop_policy_version_id' => $policyVersionId,
        ], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use (
                $shop,
                $customer,
                $date,
                $policyVersionId,
                $keyHash,
                $requestHash,
            ): InterestSettlementRun {
                $key = IdempotencyKey::query()
                    ->where('operation_type', 'ANNUAL_AUTO_SETTLEMENT')
                    ->where('key_hash', $keyHash)
                    ->lockForUpdate()
                    ->first();

                if ($key !== null) {
                    if (! hash_equals($key->request_hash, $requestHash)) {
                        throw new LogicException('The annual settlement identity was reused with a different policy snapshot.');
                    }

                    $run = InterestSettlementRun::query()
                        ->where('customer_id', $customer->getKey())
                        ->where('settlement_date', $date)
                        ->lockForUpdate()
                        ->first();

                    if ($run === null) {
                        throw new LogicException('An annual settlement idempotency record has no matching settlement run.');
                    }

                    if ($key->status === IdempotencyKey::STATUS_COMPLETED && $run->status !== 'COMPLETED') {
                        throw new LogicException('A completed annual settlement key points to an incomplete run.');
                    }

                    return $run;
                }

                $key = IdempotencyKey::query()->create([
                    'operation_type' => 'ANNUAL_AUTO_SETTLEMENT',
                    'key_hash' => $keyHash,
                    'request_hash' => $requestHash,
                    'status' => IdempotencyKey::STATUS_IN_PROGRESS,
                ]);

                return InterestSettlementRun::query()->create([
                    'customer_id' => $customer->getKey(),
                    'settlement_date' => $date,
                    'cutoff_date_exclusive' => $date,
                    'shop_policy_version_id' => $policyVersionId,
                    'status' => 'PENDING',
                    'idempotency_key_id' => $key->getKey(),
                    'started_at' => now($shop->timezone ?: 'UTC'),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);

            if (! in_array($driverCode, [19, 1062], true) && ! str_starts_with((string) $exception->getCode(), '23')) {
                throw $exception;
            }

            $key = IdempotencyKey::query()
                ->where('operation_type', 'ANNUAL_AUTO_SETTLEMENT')
                ->where('key_hash', $keyHash)
                ->first();
            $run = InterestSettlementRun::query()
                ->where('customer_id', $customer->getKey())
                ->where('settlement_date', $date)
                ->first();

            if ($key === null || $run === null || (int) $run->idempotency_key_id !== (int) $key->getKey()) {
                throw $exception;
            }

            if (! hash_equals($key->request_hash, $requestHash)) {
                throw new LogicException('The annual settlement identity was reused with a different request.');
            }

            return $run;
        }
    }

    private function completeSettlementKey(InterestSettlementRun $run): void
    {
        if ($run->status !== 'COMPLETED') {
            throw new LogicException('An annual settlement cannot be acknowledged before its calculation completes.');
        }

        IdempotencyKey::query()
            ->whereKey($run->idempotency_key_id)
            ->where('status', '!=', IdempotencyKey::STATUS_COMPLETED)
            ->update([
                'status' => IdempotencyKey::STATUS_COMPLETED,
                'resource_type' => 'interest_settlement_run',
                'resource_id' => $run->getKey(),
                'response_code' => 200,
                'updated_at' => now(),
            ]);
    }
}
