<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interest_lot_events', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('interest_lot_id');
            $table->string('event_type', 24);
            $table->date('event_date');
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('financial_transaction_id')->nullable();
            $table->unsignedBigInteger('allocation_item_id')->nullable();
            $table->unsignedBigInteger('reverses_event_id')->nullable();
            $table->char('event_key_hash', 64);
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'event_key_hash'], 'ile_shop_event_key_unique');
            $table->unique(['shop_id', 'customer_id', 'id'], 'ile_shop_customer_id_unique');
            $table->unique(['shop_id', 'customer_id', 'interest_lot_id', 'id'], 'ile_shop_customer_lot_id_unique');
            $table->index(['shop_id', 'interest_lot_id', 'event_date', 'id'], 'ile_shop_lot_date_idx');
            $table->foreign(['shop_id', 'customer_id', 'interest_lot_id'], 'ile_shop_customer_lot_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('interest_lots')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'financial_transaction_id'], 'ile_shop_customer_transaction_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('financial_transactions')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'interest_lot_id', 'reverses_event_id'], 'ile_shop_customer_lot_reversal_fk')
                ->references(['shop_id', 'customer_id', 'interest_lot_id', 'id'])->on('interest_lot_events')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE interest_lot_events
             ADD CONSTRAINT ile_type_check CHECK (event_type IN ('POSTED', 'CAPITALIZED', 'ALLOCATED', 'REVERSED')),
             ADD CONSTRAINT ile_amount_check CHECK (amount > 0)"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('interest_lot_events') && DB::table('interest_lot_events')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty interest_lot_events.');
        }

        Schema::dropIfExists('interest_lot_events');
    }
};
