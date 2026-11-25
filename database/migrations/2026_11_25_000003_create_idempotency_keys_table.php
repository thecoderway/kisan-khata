<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('operation_type', 40);
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->string('status', 20);
            $table->string('resource_type', 64)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->unique(
                ['shop_id', 'operation_type', 'key_hash'],
                'idem_shop_operation_key_unique'
            );
            $table->unique(['shop_id', 'id'], 'idem_shop_id_unique');
            $table->index(['shop_id', 'status', 'created_at'], 'idem_shop_status_created_idx');
            $table->foreign('shop_id', 'idem_shop_fk')
                ->references('id')->on('shops')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE idempotency_keys
             ADD CONSTRAINT idem_status_check
             CHECK (status IN ('IN_PROGRESS', 'COMPLETED', 'FAILED'))"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('idempotency_keys') && DB::table('idempotency_keys')->exists()) {
            throw new RuntimeException(
                'Refusing to drop non-empty idempotency_keys; preserve retry identity and use a forward recovery plan.'
            );
        }

        Schema::dropIfExists('idempotency_keys');
    }
};
