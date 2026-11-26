<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_balance_snapshots', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->date('as_of_date');
            $table->decimal('principal_balance', 15, 2);
            $table->decimal('interest_balance', 15, 2);
            $table->decimal('total_payable', 15, 2);
            $table->unsignedBigInteger('source_ledger_entry_id')->nullable();
            $table->dateTime('calculated_at', 6);
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'customer_id', 'as_of_date'], 'abs_shop_customer_date_unique');
            $table->index(['shop_id', 'customer_id', 'calculated_at'], 'abs_shop_customer_calculated_idx');

            $table->foreign('shop_id', 'abs_shop_fk')
                ->references('id')->on('shops')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id'], 'abs_shop_customer_fk')
                ->references(['shop_id', 'id'])->on('customers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'source_ledger_entry_id'], 'abs_shop_customer_source_ledger_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('ledger_entries')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            'ALTER TABLE account_balance_snapshots
             ADD CONSTRAINT abs_principal_check CHECK (principal_balance >= 0),
             ADD CONSTRAINT abs_interest_check CHECK (interest_balance >= 0),
             ADD CONSTRAINT abs_total_check CHECK (total_payable = principal_balance + interest_balance)'
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('account_balance_snapshots') && DB::table('account_balance_snapshots')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty account_balance_snapshots; preserve financial projections for reconciliation.');
        }

        Schema::dropIfExists('account_balance_snapshots');
    }
};
