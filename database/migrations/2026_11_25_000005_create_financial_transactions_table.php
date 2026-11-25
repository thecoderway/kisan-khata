<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('transaction_number', 64);
            $table->string('transaction_type', 32);
            $table->date('effective_date');
            $table->dateTime('occurred_at', 6);
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('idempotency_key_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('posted_by');
            $table->dateTime('posted_at', 6);
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'transaction_number'], 'ft_shop_number_unique');
            $table->unique(['shop_id', 'id'], 'ft_shop_id_unique');
            $table->unique(['shop_id', 'idempotency_key_id'], 'ft_shop_idem_unique');
            $table->index(
                ['shop_id', 'customer_id', 'effective_date', 'occurred_at', 'id'],
                'ft_shop_customer_date_idx'
            );
            $table->index(['shop_id', 'transaction_type', 'effective_date'], 'ft_shop_type_date_idx');
            $table->index(['shop_id', 'created_by'], 'ft_shop_creator_idx');
            $table->index(['shop_id', 'posted_by'], 'ft_shop_poster_idx');

            $table->foreign('shop_id', 'ft_shop_fk')
                ->references('id')->on('shops')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id'], 'ft_shop_customer_fk')
                ->references(['shop_id', 'id'])->on('customers')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'created_by'], 'ft_shop_creator_fk')
                ->references(['shop_id', 'id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'posted_by'], 'ft_shop_poster_fk')
                ->references(['shop_id', 'id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'idempotency_key_id'], 'ft_shop_idem_fk')
                ->references(['shop_id', 'id'])->on('idempotency_keys')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE financial_transactions
             ADD CONSTRAINT ft_type_check
             CHECK (transaction_type IN (
                 'SALE_POSTING',
                 'PAYMENT_POSTING',
                 'INTEREST_POSTING',
                 'CAPITALIZATION',
                 'REVERSAL'
             ))"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('financial_transactions') && DB::table('financial_transactions')->exists()) {
            throw new RuntimeException(
                'Refusing to drop non-empty financial_transactions; use a forward corrective migration or approved restore.'
            );
        }

        Schema::dropIfExists('financial_transactions');
    }
};
