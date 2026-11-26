<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('financial_transaction_id');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('component', 24);
            $table->string('entry_kind', 24);
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->date('effective_date');
            $table->unsignedSmallInteger('sequence_number');
            $table->unsignedBigInteger('reverses_entry_id')->nullable();
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'financial_transaction_id', 'sequence_number'], 'le_shop_transaction_sequence_unique');
            $table->unique(['shop_id', 'customer_id', 'id'], 'le_shop_customer_id_unique');
            $table->index(['shop_id', 'customer_id', 'effective_date', 'id'], 'le_shop_customer_date_idx');
            $table->index(['shop_id', 'customer_id', 'component', 'effective_date', 'id'], 'le_shop_customer_component_date_idx');

            $table->foreign('shop_id', 'le_shop_fk')
                ->references('id')->on('shops')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id'], 'le_shop_customer_fk')
                ->references(['shop_id', 'id'])->on('customers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'financial_transaction_id'], 'le_shop_customer_transaction_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('financial_transactions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'sale_id'], 'le_shop_customer_sale_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('sales')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'payment_id'], 'le_shop_customer_payment_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('payments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'reverses_entry_id'], 'le_shop_customer_reversal_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('ledger_entries')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE ledger_entries
             ADD CONSTRAINT le_component_check CHECK (component IN ('PRINCIPAL', 'INTEREST')),
             ADD CONSTRAINT le_kind_check CHECK (entry_kind IN ('SALE', 'INTEREST', 'PAYMENT', 'CAPITALIZATION', 'REVERSAL')),
             ADD CONSTRAINT le_amount_check
                 CHECK (
                     (debit_amount > 0 AND credit_amount = 0)
                     OR (credit_amount > 0 AND debit_amount = 0)
                 ),
             ADD CONSTRAINT le_sequence_check CHECK (sequence_number > 0)"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('ledger_entries') && DB::table('ledger_entries')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty ledger_entries; preserve authoritative financial history.');
        }

        Schema::dropIfExists('ledger_entries');
    }
};
