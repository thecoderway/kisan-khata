<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interest_settlement_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('settlement_run_id');
            $table->unsignedBigInteger('calculation_item_id');
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('interest_lot_id')->nullable();
            $table->decimal('posted_amount', 15, 2);
            $table->unsignedBigInteger('ledger_entry_id')->nullable();
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'settlement_run_id', 'calculation_item_id'], 'isi_shop_run_calculation_unique');
            $table->unique(['shop_id', 'customer_id', 'id'], 'isi_shop_customer_id_unique');
            $table->foreign(['shop_id', 'customer_id', 'settlement_run_id'], 'isi_shop_customer_run_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('interest_settlement_runs')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'sale_id', 'calculation_item_id'], 'isi_shop_customer_sale_calculation_fk')
                ->references(['shop_id', 'customer_id', 'sale_id', 'id'])->on('interest_calculation_items')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'sale_id'], 'isi_shop_customer_sale_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('sales')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(
                ['shop_id', 'customer_id', 'sale_id', 'calculation_item_id', 'interest_lot_id'],
                'isi_shop_customer_sale_calculation_lot_fk'
            )->references(['shop_id', 'customer_id', 'sale_id', 'calculation_item_id', 'id'])
                ->on('interest_lots')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'ledger_entry_id'], 'isi_shop_customer_ledger_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('ledger_entries')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            'ALTER TABLE interest_settlement_items
             ADD CONSTRAINT isi_amount_check CHECK (posted_amount > 0)'
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('interest_settlement_items') && DB::table('interest_settlement_items')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty interest_settlement_items.');
        }

        Schema::dropIfExists('interest_settlement_items');
    }
};
