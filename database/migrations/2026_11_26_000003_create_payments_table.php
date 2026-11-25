<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('payment_number', 64);
            $table->string('payment_type', 24);
            $table->unsignedBigInteger('source_sale_id')->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 32)->nullable();
            $table->string('reference_number', 128)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('financial_transaction_id');
            $table->unsignedBigInteger('reversal_of_payment_id')->nullable();
            $table->unsignedBigInteger('replacement_for_payment_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('posted_by');
            $table->dateTime('posted_at', 6);
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'payment_number'], 'payments_shop_number_unique');
            $table->unique(['shop_id', 'id'], 'payments_shop_id_unique');
            $table->unique(['shop_id', 'customer_id', 'id'], 'payments_shop_customer_id_unique');
            $table->unique(['shop_id', 'source_sale_id'], 'payments_shop_source_sale_unique');
            $table->index(['shop_id', 'customer_id', 'payment_date', 'id'], 'payments_shop_customer_date_idx');
            $table->index(['shop_id', 'payment_type', 'payment_date'], 'payments_shop_type_date_idx');
            $table->index(['shop_id', 'created_by'], 'payments_shop_creator_idx');
            $table->index(['shop_id', 'posted_by'], 'payments_shop_poster_idx');

            $table->foreign('shop_id', 'payments_shop_fk')
                ->references('id')->on('shops')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id'], 'payments_shop_customer_fk')
                ->references(['shop_id', 'id'])->on('customers')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'source_sale_id'], 'payments_shop_customer_source_sale_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('sales')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'financial_transaction_id'], 'payments_shop_customer_transaction_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('financial_transactions')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'reversal_of_payment_id'], 'payments_shop_customer_reversal_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('payments')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'replacement_for_payment_id'], 'payments_shop_customer_replacement_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('payments')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'created_by'], 'payments_shop_creator_fk')
                ->references(['shop_id', 'id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'posted_by'], 'payments_shop_poster_fk')
                ->references(['shop_id', 'id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE `payments`
             ADD CONSTRAINT `payments_amount_check` CHECK (`amount` > 0),
             ADD CONSTRAINT `payments_type_check`
                 CHECK (`payment_type` IN ('NORMAL', 'SALE_DAY_AUTO', 'REVERSAL')),
             ADD CONSTRAINT `payments_source_type_check`
                 CHECK (
                     (`payment_type` = 'NORMAL' AND `source_sale_id` IS NULL AND `reversal_of_payment_id` IS NULL)
                     OR (`payment_type` = 'SALE_DAY_AUTO' AND `source_sale_id` IS NOT NULL AND `reversal_of_payment_id` IS NULL)
                     OR (`payment_type` = 'REVERSAL' AND `source_sale_id` IS NULL AND `reversal_of_payment_id` IS NOT NULL)
                 ),
             ADD CONSTRAINT `payments_replacement_type_check`
                 CHECK (`replacement_for_payment_id` IS NULL OR `payment_type` = 'NORMAL')"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && DB::table('payments')->exists()) {
            throw new RuntimeException(
                'Refusing to drop non-empty payments; preserve posted receipts and use a forward corrective migration.'
            );
        }

        Schema::dropIfExists('payments');
    }
};
