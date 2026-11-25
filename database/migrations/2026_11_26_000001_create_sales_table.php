<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertMySql();
        $this->ensureFinancialTransactionCustomerKey();

        Schema::create('sales', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('sale_number', 64);
            $table->date('sale_date');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->string('payment_status', 20)->default('UNPAID');
            $table->string('interest_settlement_status', 20)->default('PENDING');
            $table->string('sale_payment_choice', 10);
            $table->unsignedBigInteger('financial_transaction_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->dateTime('posted_at', 6)->nullable();
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'sale_number'], 'sales_shop_number_unique');
            $table->unique(['shop_id', 'id'], 'sales_shop_id_unique');
            $table->unique(['shop_id', 'customer_id', 'id'], 'sales_shop_customer_id_unique');
            $table->index(['shop_id', 'customer_id', 'sale_date', 'id'], 'sales_shop_customer_date_idx');
            $table->index(['shop_id', 'customer_id', 'payment_status', 'sale_date', 'id'], 'sales_shop_customer_status_date_idx');
            $table->index(['shop_id', 'created_by'], 'sales_shop_creator_idx');
            $table->index(['shop_id', 'posted_by'], 'sales_shop_poster_idx');

            $table->foreign('shop_id', 'sales_shop_fk')
                ->references('id')->on('shops')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id'], 'sales_shop_customer_fk')
                ->references(['shop_id', 'id'])->on('customers')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'financial_transaction_id'], 'sales_shop_customer_transaction_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('financial_transactions')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'created_by'], 'sales_shop_creator_fk')
                ->references(['shop_id', 'id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'posted_by'], 'sales_shop_poster_fk')
                ->references(['shop_id', 'id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE `sales`
             ADD CONSTRAINT `sales_amount_check` CHECK (`amount` > 0),
             ADD CONSTRAINT `sales_payment_status_check`
                 CHECK (`payment_status` IN ('UNPAID', 'PARTIALLY_PAID', 'PAID')),
             ADD CONSTRAINT `sales_interest_status_check`
                 CHECK (`interest_settlement_status` IN ('PENDING', 'SETTLED')),
             ADD CONSTRAINT `sales_payment_choice_check`
                 CHECK (`sale_payment_choice` IN ('PAID', 'UNPAID'))"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('sales') && DB::table('sales')->exists()) {
            throw new RuntimeException(
                'Refusing to drop non-empty sales; preserve posted Sales and use a forward corrective migration.'
            );
        }

        Schema::dropIfExists('sales');
    }

    private function assertMySql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Group 2 financial migrations require MySQL 8.4/InnoDB.');
        }
    }

    private function ensureFinancialTransactionCustomerKey(): void
    {
        $indexes = collect(Schema::getIndexes('financial_transactions'));
        $existing = $indexes->first(fn (array $index): bool => $index['name'] === 'ft_shop_customer_id_unique');

        if ($existing !== null) {
            if ($existing['columns'] !== ['shop_id', 'customer_id', 'id'] || ! $existing['unique']) {
                throw new RuntimeException('ft_shop_customer_id_unique exists with an unexpected definition.');
            }

            return;
        }

        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->unique(['shop_id', 'customer_id', 'id'], 'ft_shop_customer_id_unique');
        });
    }

};
