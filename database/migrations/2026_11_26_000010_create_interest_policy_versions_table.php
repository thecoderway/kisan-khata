<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interest_policy_versions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('interest_policy_id');
            $table->unsignedInteger('version_number');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('monthly_rate_percent', 12, 8)->default('0.00000000');
            $table->unsignedSmallInteger('grace_days')->default(30);
            $table->boolean('capitalization_enabled')->default(false);
            $table->string('payment_waterfall', 20)->default('PRINCIPAL_FIRST');
            $table->unsignedTinyInteger('annual_settlement_month_1')->default(3);
            $table->unsignedTinyInteger('annual_settlement_day_1')->default(30);
            $table->unsignedTinyInteger('annual_settlement_month_2')->default(10);
            $table->unsignedTinyInteger('annual_settlement_day_2')->default(30);
            $table->unsignedBigInteger('created_by');
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'interest_policy_id', 'version_number'], 'ipv_shop_policy_version_unique');
            $table->unique(['shop_id', 'id'], 'ipv_shop_id_unique');
            $table->index(['shop_id', 'effective_from', 'effective_to'], 'ipv_shop_effective_idx');
            $table->foreign(['shop_id', 'interest_policy_id'], 'ipv_shop_policy_fk')
                ->references(['shop_id', 'id'])->on('interest_policies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'created_by'], 'ipv_shop_creator_fk')
                ->references(['shop_id', 'id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE interest_policy_versions
             ADD CONSTRAINT ipv_version_check CHECK (version_number > 0),
             ADD CONSTRAINT ipv_rate_check CHECK (monthly_rate_percent >= 0),
             ADD CONSTRAINT ipv_waterfall_check CHECK (payment_waterfall IN ('PRINCIPAL_FIRST', 'INTEREST_FIRST')),
             ADD CONSTRAINT ipv_effective_range_check CHECK (effective_to IS NULL OR effective_to > effective_from),
             ADD CONSTRAINT ipv_months_check CHECK (
                 annual_settlement_month_1 BETWEEN 1 AND 12
                 AND annual_settlement_month_2 BETWEEN 1 AND 12
             ),
             ADD CONSTRAINT ipv_days_check CHECK (
                 annual_settlement_day_1 BETWEEN 1 AND 31
                 AND annual_settlement_day_2 BETWEEN 1 AND 31
             )"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('interest_policy_versions') && DB::table('interest_policy_versions')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty interest_policy_versions.');
        }

        Schema::dropIfExists('interest_policy_versions');
    }
};
