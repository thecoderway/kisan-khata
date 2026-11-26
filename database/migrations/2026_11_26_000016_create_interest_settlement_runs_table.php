<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interest_settlement_runs', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->date('settlement_date');
            $table->date('cutoff_date_exclusive');
            $table->unsignedBigInteger('shop_policy_version_id');
            $table->string('status', 20);
            $table->unsignedBigInteger('idempotency_key_id');
            $table->dateTime('started_at', 6);
            $table->dateTime('completed_at', 6)->nullable();
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'customer_id', 'settlement_date'], 'isr_shop_customer_date_unique');
            $table->unique(['shop_id', 'customer_id', 'id'], 'isr_shop_customer_id_unique');
            $table->index(['shop_id', 'settlement_date', 'status'], 'isr_shop_date_status_idx');
            $table->foreign(['shop_id', 'customer_id'], 'isr_shop_customer_fk')
                ->references(['shop_id', 'id'])->on('customers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'shop_policy_version_id'], 'isr_shop_policy_version_fk')
                ->references(['shop_id', 'id'])->on('interest_policy_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'idempotency_key_id'], 'isr_shop_idempotency_fk')
                ->references(['shop_id', 'id'])->on('idempotency_keys')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE interest_settlement_runs
             ADD CONSTRAINT isr_status_check CHECK (status IN ('PENDING', 'COMPLETED', 'FAILED')),
             ADD CONSTRAINT isr_cutoff_check CHECK (cutoff_date_exclusive = settlement_date),
             ADD CONSTRAINT isr_completion_check
                 CHECK ((status = 'COMPLETED' AND completed_at IS NOT NULL) OR status <> 'COMPLETED')"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('interest_settlement_runs') && DB::table('interest_settlement_runs')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty interest_settlement_runs.');
        }

        Schema::dropIfExists('interest_settlement_runs');
    }
};
