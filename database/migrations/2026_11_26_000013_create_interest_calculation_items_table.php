<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interest_calculation_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('calculation_run_id');
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('source_interest_lot_id')->nullable();
            $table->unsignedBigInteger('shop_policy_version_id');
            $table->unsignedBigInteger('customer_policy_version_id')->nullable();
            $table->date('interval_start_date');
            $table->date('interval_end_date_exclusive');
            $table->unsignedInteger('actual_days');
            $table->unsignedSmallInteger('grace_days_applied');
            $table->decimal('monthly_rate_percent', 12, 8);
            $table->decimal('eligible_principal_basis', 20, 8);
            $table->decimal('unrounded_interest', 20, 8);
            $table->decimal('posted_interest', 15, 2);
            $table->boolean('capitalization_enabled');
            $table->char('item_key_hash', 64);
            $table->unsignedBigInteger('ledger_entry_id')->nullable();
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'item_key_hash'], 'ici_shop_item_key_unique');
            $table->unique(['shop_id', 'customer_id', 'id'], 'ici_shop_customer_id_unique');
            $table->unique(['shop_id', 'customer_id', 'sale_id', 'id'], 'ici_shop_customer_sale_id_unique');
            $table->index(['shop_id', 'customer_id', 'sale_id', 'interval_start_date', 'interval_end_date_exclusive'], 'ici_shop_customer_sale_interval_idx');
            $table->foreign(['shop_id', 'customer_id', 'calculation_run_id'], 'ici_shop_customer_run_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('interest_calculation_runs')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'sale_id'], 'ici_shop_customer_sale_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('sales')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'shop_policy_version_id'], 'ici_shop_policy_version_fk')
                ->references(['shop_id', 'id'])->on('interest_policy_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'customer_policy_version_id'], 'ici_shop_customer_policy_version_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('customer_interest_policy_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'ledger_entry_id'], 'ici_shop_customer_ledger_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('ledger_entries')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            'ALTER TABLE interest_calculation_items
             ADD CONSTRAINT ici_date_range_check CHECK (interval_end_date_exclusive >= interval_start_date),
             ADD CONSTRAINT ici_rate_check CHECK (monthly_rate_percent >= 0),
             ADD CONSTRAINT ici_principal_check CHECK (eligible_principal_basis >= 0),
             ADD CONSTRAINT ici_interest_check CHECK (unrounded_interest >= 0 AND posted_interest >= 0)'
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('interest_calculation_items') && DB::table('interest_calculation_items')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty interest_calculation_items.');
        }

        Schema::dropIfExists('interest_calculation_items');
    }
};
