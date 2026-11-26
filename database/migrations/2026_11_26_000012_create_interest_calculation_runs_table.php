<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interest_calculation_runs', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('trigger_type', 32);
            $table->unsignedBigInteger('trigger_payment_id')->nullable();
            $table->unsignedBigInteger('settlement_run_id')->nullable();
            $table->date('cutoff_date_exclusive');
            $table->char('event_key_hash', 64);
            $table->dateTime('started_at', 6);
            $table->dateTime('completed_at', 6)->nullable();
            $table->string('status', 20);
            $table->unsignedBigInteger('idempotency_key_id');
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'event_key_hash'], 'icr_shop_event_key_unique');
            $table->unique(['shop_id', 'customer_id', 'id'], 'icr_shop_customer_id_unique');
            $table->index(['shop_id', 'customer_id', 'cutoff_date_exclusive'], 'icr_shop_customer_cutoff_idx');
            $table->foreign(['shop_id', 'customer_id'], 'icr_shop_customer_fk')
                ->references(['shop_id', 'id'])->on('customers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'trigger_payment_id'], 'icr_shop_customer_payment_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('payments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'idempotency_key_id'], 'icr_shop_idempotency_fk')
                ->references(['shop_id', 'id'])->on('idempotency_keys')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE interest_calculation_runs
             ADD CONSTRAINT icr_trigger_check CHECK (trigger_type IN ('CUSTOMER_PAYMENT', 'ANNUAL_AUTO_SETTLEMENT')),
             ADD CONSTRAINT icr_status_check CHECK (status IN ('PENDING', 'COMPLETED', 'FAILED')),
             ADD CONSTRAINT icr_trigger_source_check
                 CHECK (
                     (trigger_type = 'CUSTOMER_PAYMENT' AND trigger_payment_id IS NOT NULL)
                     OR (trigger_type = 'ANNUAL_AUTO_SETTLEMENT' AND trigger_payment_id IS NULL)
                 ),
             ADD CONSTRAINT icr_completion_check
                 CHECK ((status = 'COMPLETED' AND completed_at IS NOT NULL) OR status <> 'COMPLETED')"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('interest_calculation_runs') && DB::table('interest_calculation_runs')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty interest_calculation_runs.');
        }

        Schema::dropIfExists('interest_calculation_runs');
    }
};
