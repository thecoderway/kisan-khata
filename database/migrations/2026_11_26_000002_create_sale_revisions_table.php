<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_revisions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('sale_id');
            $table->unsignedInteger('revision_number');
            $table->json('old_values');
            $table->json('new_values');
            $table->string('correction_reason', 500);
            $table->unsignedBigInteger('changed_by');
            $table->dateTime('changed_at', 6);
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'sale_id', 'revision_number'], 'sale_rev_shop_sale_number_unique');
            $table->index(['shop_id', 'sale_id', 'changed_at'], 'sale_rev_shop_sale_changed_idx');

            $table->foreign('shop_id', 'sale_rev_shop_fk')
                ->references('id')->on('shops')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'sale_id'], 'sale_rev_shop_sale_fk')
                ->references(['shop_id', 'id'])->on('sales')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'changed_by'], 'sale_rev_shop_actor_fk')
                ->references(['shop_id', 'id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE `sale_revisions`
             ADD CONSTRAINT `sale_rev_number_check` CHECK (`revision_number` > 0),
             ADD CONSTRAINT `sale_rev_reason_check`
                 CHECK (CHAR_LENGTH(TRIM(`correction_reason`)) > 0)"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_revisions') && DB::table('sale_revisions')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty sale_revisions; preserve correction history.');
        }

        Schema::dropIfExists('sale_revisions');
    }
};
