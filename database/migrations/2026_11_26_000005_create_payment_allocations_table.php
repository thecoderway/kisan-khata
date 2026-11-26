<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('payment_id');
            $table->string('allocation_type', 24);
            $table->unsignedBigInteger('waterfall_policy_version_id')->nullable();
            $table->dateTime('allocation_effective_at', 6);
            $table->decimal('total_allocated', 15, 2);
            $table->unsignedBigInteger('reversal_of_allocation_id')->nullable();
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'id'], 'pa_shop_id_unique');
            $table->unique(['shop_id', 'customer_id', 'id'], 'pa_shop_customer_id_unique');
            $table->unique(['shop_id', 'customer_id', 'payment_id'], 'pa_shop_customer_payment_unique');
            $table->unique(['shop_id', 'customer_id', 'payment_id', 'id'], 'pa_shop_customer_payment_id_unique');
            $table->index(['shop_id', 'customer_id', 'allocation_effective_at', 'id'], 'pa_shop_customer_effective_idx');

            $table->foreign('shop_id', 'pa_shop_fk')
                ->references('id')->on('shops')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id'], 'pa_shop_customer_fk')
                ->references(['shop_id', 'id'])->on('customers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'payment_id'], 'pa_shop_customer_payment_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('payments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'reversal_of_allocation_id'], 'pa_shop_customer_reversal_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('payment_allocations')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE payment_allocations
             ADD CONSTRAINT pa_type_check
                 CHECK (allocation_type IN ('NORMAL_FIFO', 'SALE_RESTRICTED', 'REVERSAL')),
             ADD CONSTRAINT pa_amount_check CHECK (total_allocated > 0),
             ADD CONSTRAINT pa_reversal_check
                 CHECK (
                     (allocation_type = 'REVERSAL' AND reversal_of_allocation_id IS NOT NULL)
                     OR (allocation_type IN ('NORMAL_FIFO', 'SALE_RESTRICTED') AND reversal_of_allocation_id IS NULL)
                 )"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_allocations') && DB::table('payment_allocations')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty payment_allocations; preserve allocation history.');
        }

        Schema::dropIfExists('payment_allocations');
    }
};
