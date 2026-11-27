<?php

use Carbon\\CarbonImmutable;
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;

return new class extends Migration
{
    /**
     * Ensure every pre-existing shop has the approved initial DEFAULT policy.
     *
     * Existing policy versions are never modified. Shops without a DEFAULT
     * policy receive a zero-rate version effective from their local migration
     * date, so the backfill does not introduce retroactive interest.
     */
    public function up(): void
    {
        DB::table('shops')
            ->select(['id', 'timezone'])
            ->orderBy('id')
            ->chunkById(100, function ($shops): void {
                foreach ($shops as $shop) {
                    DB::transaction(function () use ($shop): void {
                        $shopId = (int) $shop->id;
                        $ownerId = DB::table('users')
                            ->where('shop_id', $shopId)
                            ->where('role', 'owner')
                            ->orderBy('id')
                            ->value('id');

                        if ($ownerId === null) {
                            throw new RuntimeException(
                                "Cannot initialize the default interest policy for shop {$shopId}: no Owner user exists."
                            );
                        }

                        $policyId = DB::table('interest_policies')
                            ->where('shop_id', $shopId)
                            ->where('policy_code', 'DEFAULT')
                            ->value('id');

                        if ($policyId === null) {
                            $policyId = DB::table('interest_policies')->insertGetId([
                                'shop_id' => $shopId,
                                'policy_code' => 'DEFAULT',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        $hasVersions = DB::table('interest_policy_versions')
                            ->where('shop_id', $shopId)
                            ->where('interest_policy_id', $policyId)
                            ->exists();

                        if ($hasVersions) {
                            return;
                        }

                        $effectiveFrom = CarbonImmutable::now($shop->timezone ?: 'Asia/Kolkata')->toDateString();
                        $versionId = DB::table('interest_policy_versions')->insertGetId([
                            'shop_id' => $shopId,
                            'interest_policy_id' => $policyId,
                            'version_number' => 1,
                            'effective_from' => $effectiveFrom,
                            'effective_to' => null,
                            'monthly_rate_percent' => '0.00000000',
                            'grace_days' => 30,
                            'capitalization_enabled' => false,
                            'payment_waterfall' => 'PRINCIPAL_FIRST',
                            'annual_settlement_month_1' => 3,
                            'annual_settlement_day_1' => 30,
                            'annual_settlement_month_2' => 10,
                            'annual_settlement_day_2' => 30,
                            'created_by' => $ownerId,
                            'created_at' => now(),
                        ]);

                        DB::table('audit_logs')->insert([
                            'shop_id' => $shopId,
                            'user_id' => $ownerId,
                            'action' => 'financial.shop_interest_policy_initialized',
                            'entity_type' => 'interest_policy_version',
                            'entity_id' => $versionId,
                            'reason' => 'Initial default policy backfilled during interest policy migration',
                            'old_values' => null,
                            'new_values' => json_encode([
                                'policy_code' => 'DEFAULT',
                                'version_number' => 1,
                                'effective_from' => $effectiveFrom,
                                'monthly_rate_percent' => '0.00000000',
                                'grace_days' => 30,
                                'capitalization_enabled' => false,
                                'payment_waterfall' => 'PRINCIPAL_FIRST',
                                'annual_settlement_dates' => ['03-30', '10-30'],
                            ], JSON_THROW_ON_ERROR),
                            'created_at' => now(),
                        ]);
                    });
                }
            });
    }

    public function down(): void
    {
        // Intentionally retain initialized policy history. Removing it would
        // invalidate financial calculations and audit references.
    }
};
