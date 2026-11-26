<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interest_lots', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('calculation_item_id');
            $table->string('lot_type', 32);
            $table->date('lot_date');
            $table->decimal('original_amount', 15, 2);
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'customer_id', 'sale_id', 'id'], 'il_shop_customer_sale_id_unique');
            $table->unique(['shop_id', 'customer_id', 'calculation_item_id'], 'il_shop_customer_calculation_item_unique');
            $table->unique(['shop_id', 'customer_id', 'sale_id', 'calculation_item_id', 'id'], 'il_shop_customer_sale_calc_id_unique');
            $table->unique(['shop_id', 'customer_id', 'id'], 'il_shop_customer_id_unique');
            $table->index(['shop_id', 'customer_id', 'sale_id', 'lot_type', 'lot_date', 'id'], 'il_shop_customer_sale_lot_date_idx');
            $table->foreign(['shop_id', 'customer_id', 'sale_id'], 'il_shop_customer_sale_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('sales')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'sale_id', 'calculation_item_id'], 'il_shop_customer_sale_calculation_fk')
                ->references(['shop_id', 'customer_id', 'sale_id', 'id'])->on('interest_calculation_items')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE interest_lots
             ADD CONSTRAINT il_type_check CHECK (lot_type IN ('NON_CAPITALIZED_INTEREST', 'CAPITALIZED_INTEREST')),
             ADD CONSTRAINT il_amount_check CHECK (original_amount > 0)"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('interest_lots') && DB::table('interest_lots')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty interest_lots.');
        }

        Schema::dropIfExists('interest_lots');
    }
};
