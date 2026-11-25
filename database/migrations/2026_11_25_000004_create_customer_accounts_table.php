<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->decimal('principal_balance', 15, 2)->default('0.00');
            $table->decimal('interest_balance', 15, 2)->default('0.00');
            $table->decimal('total_payable', 15, 2)->default('0.00');
            $table->date('last_interest_cutoff_date')->nullable();
            $table->dateTime('last_financial_event_at', 6)->nullable();
            $table->unsignedBigInteger('projection_version')->default(0);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->unique(['shop_id', 'customer_id'], 'acct_shop_customer_unique');
            $table->unique(['shop_id', 'id'], 'acct_shop_id_unique');
            $table->foreign('shop_id', 'acct_shop_fk')
                ->references('id')->on('shops')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id'], 'acct_shop_customer_fk')
                ->references(['shop_id', 'id'])->on('customers')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            'ALTER TABLE customer_accounts
             ADD CONSTRAINT acct_nonnegative_check
             CHECK (
                 principal_balance >= 0
                 AND interest_balance >= 0
                 AND total_payable >= 0
             ),
             ADD CONSTRAINT acct_total_check
             CHECK (total_payable = principal_balance + interest_balance)'
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_accounts') && DB::table('customer_accounts')->exists()) {
            throw new RuntimeException(
                'Refusing to drop non-empty customer_accounts; account anchors and projections must be preserved.'
            );
        }

        Schema::dropIfExists('customer_accounts');
    }
};
