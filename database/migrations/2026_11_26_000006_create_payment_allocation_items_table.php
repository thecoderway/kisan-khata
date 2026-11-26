<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocation_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('allocation_id');
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('sale_id');
            $table->string('component_type', 40);
            $table->unsignedBigInteger('source_interest_lot_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->unsignedInteger('sale_fifo_sequence');
            $table->unsignedInteger('component_sequence');
            // 0 is the deterministic sentinel for components without a lot.
            $table->unsignedInteger('lot_sequence')->default(0);
            $table->unsignedBigInteger('reverses_allocation_item_id')->nullable();
            $table->dateTime('created_at', 6);

            $table->unique(
                ['shop_id', 'allocation_id', 'sale_fifo_sequence', 'component_sequence', 'lot_sequence'],
                'pai_allocation_order_unique'
            );
            $table->unique(['shop_id', 'customer_id', 'sale_id', 'id'], 'pai_shop_customer_sale_id_unique');
            $table->index(['shop_id', 'customer_id', 'sale_id', 'component_type', 'id'], 'pai_shop_customer_sale_component_idx');

            $table->foreign('shop_id', 'pai_shop_fk')
                ->references('id')->on('shops')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(
                ['shop_id', 'customer_id', 'payment_id', 'allocation_id'],
                'pai_shop_customer_payment_allocation_fk'
            )->references(['shop_id', 'customer_id', 'payment_id', 'id'])
                ->on('payment_allocations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'payment_id'], 'pai_shop_customer_payment_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('payments')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'sale_id'], 'pai_shop_customer_sale_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('sales')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(
                ['shop_id', 'customer_id', 'sale_id', 'reverses_allocation_item_id'],
                'pai_shop_customer_sale_reversal_fk'
            )->references(['shop_id', 'customer_id', 'sale_id', 'id'])
                ->on('payment_allocation_items')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE payment_allocation_items
             ADD CONSTRAINT pai_component_check
                 CHECK (component_type IN ('PRINCIPAL', 'NON_CAPITALIZED_INTEREST', 'CAPITALIZED_INTEREST_PRINCIPAL')),
             ADD CONSTRAINT pai_amount_check CHECK (amount > 0),
             ADD CONSTRAINT pai_sequence_check
                 CHECK (sale_fifo_sequence > 0 AND component_sequence > 0),
             ADD CONSTRAINT pai_lot_source_check
                 CHECK (
                     (component_type = 'PRINCIPAL' AND source_interest_lot_id IS NULL AND lot_sequence = 0)
                     OR (component_type IN ('NON_CAPITALIZED_INTEREST', 'CAPITALIZED_INTEREST_PRINCIPAL')
                         AND source_interest_lot_id IS NOT NULL AND lot_sequence > 0)
                 )"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_allocation_items') && DB::table('payment_allocation_items')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty payment_allocation_items; preserve allocation detail.');
        }

        Schema::dropIfExists('payment_allocation_items');
    }
};
