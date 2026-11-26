<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_allocation_items', function (Blueprint $table): void {
            $table->unique(['shop_id', 'customer_id', 'id'], 'pai_shop_customer_id_unique');
            $table->unique(['shop_id', 'customer_id', 'source_interest_lot_id', 'id'], 'pai_shop_customer_lot_id_unique');
            $table->foreign(
                ['shop_id', 'customer_id', 'sale_id', 'source_interest_lot_id'],
                'pai_shop_customer_sale_lot_fk'
            )->references(['shop_id', 'customer_id', 'sale_id', 'id'])
                ->on('interest_lots')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->foreign(['shop_id', 'waterfall_policy_version_id'], 'pa_shop_waterfall_policy_fk')
                ->references(['shop_id', 'id'])->on('interest_policy_versions')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('interest_calculation_runs', function (Blueprint $table): void {
            $table->foreign(['shop_id', 'customer_id', 'settlement_run_id'], 'icr_shop_customer_settlement_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('interest_settlement_runs')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('interest_calculation_items', function (Blueprint $table): void {
            $table->foreign(
                ['shop_id', 'customer_id', 'sale_id', 'source_interest_lot_id'],
                'ici_shop_customer_sale_lot_fk'
            )->references(['shop_id', 'customer_id', 'sale_id', 'id'])
                ->on('interest_lots')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('interest_lot_events', function (Blueprint $table): void {
            $table->foreign(['shop_id', 'customer_id', 'interest_lot_id', 'allocation_item_id'], 'ile_shop_customer_lot_allocation_item_fk')
                ->references(['shop_id', 'customer_id', 'source_interest_lot_id', 'id'])->on('payment_allocation_items')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            'ALTER TABLE interest_calculation_runs
             ADD CONSTRAINT icr_annual_source_check
                 CHECK (
                     (trigger_type = \'CUSTOMER_PAYMENT\' AND settlement_run_id IS NULL)
                     OR (trigger_type = \'ANNUAL_AUTO_SETTLEMENT\' AND settlement_run_id IS NOT NULL)
                 )'
        );

        DB::statement(
            'ALTER TABLE payment_allocations
             ADD CONSTRAINT pa_policy_version_check
                 CHECK (allocation_type = \'SALE_RESTRICTED\' OR waterfall_policy_version_id IS NOT NULL)'
        );

        DB::statement(
            'ALTER TABLE interest_lot_events
             ADD CONSTRAINT ile_source_check
                 CHECK (
                     (event_type IN (\'POSTED\', \'CAPITALIZED\') AND financial_transaction_id IS NOT NULL AND allocation_item_id IS NULL)
                     OR (event_type = \'ALLOCATED\' AND allocation_item_id IS NOT NULL)
                     OR (event_type = \'REVERSED\' AND reverses_event_id IS NOT NULL)
                 )'
        );

        DB::statement(
            'ALTER TABLE interest_calculation_items
             ADD CONSTRAINT ici_ledger_posting_check
                 CHECK (
                     (posted_interest = 0 AND ledger_entry_id IS NULL)
                     OR (posted_interest > 0 AND ledger_entry_id IS NOT NULL)
                 )'
        );

        DB::statement(
            'ALTER TABLE ledger_entries
             ADD CONSTRAINT le_reversal_reference_check
                 CHECK (
                     (entry_kind = \'REVERSAL\' AND reverses_entry_id IS NOT NULL)
                     OR (entry_kind <> \'REVERSAL\' AND reverses_entry_id IS NULL)
                 )'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ledger_entries DROP CHECK le_reversal_reference_check');
        DB::statement('ALTER TABLE interest_calculation_items DROP CHECK ici_ledger_posting_check');
        DB::statement('ALTER TABLE interest_lot_events DROP CHECK ile_source_check');
        DB::statement('ALTER TABLE payment_allocations DROP CHECK pa_policy_version_check');
        DB::statement('ALTER TABLE interest_calculation_runs DROP CHECK icr_annual_source_check');

        Schema::table('interest_lot_events', function (Blueprint $table): void {
            $table->dropForeign('ile_shop_customer_lot_allocation_item_fk');
        });

        Schema::table('interest_calculation_items', function (Blueprint $table): void {
            $table->dropForeign('ici_shop_customer_sale_lot_fk');
        });

        Schema::table('interest_calculation_runs', function (Blueprint $table): void {
            $table->dropForeign('icr_shop_customer_settlement_fk');
        });

        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->dropForeign('pa_shop_waterfall_policy_fk');
        });

        Schema::table('payment_allocation_items', function (Blueprint $table): void {
            $table->dropForeign('pai_shop_customer_sale_lot_fk');
            $table->dropUnique('pai_shop_customer_id_unique');
            $table->dropUnique('pai_shop_customer_lot_id_unique');
        });
    }
};
