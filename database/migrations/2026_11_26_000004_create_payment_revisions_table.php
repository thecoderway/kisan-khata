<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_revisions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('payment_id');
            $table->unsignedInteger('revision_number');
            $table->string('revision_type', 24);
            $table->json('old_values');
            $table->json('new_values')->nullable();
            $table->string('correction_reason', 500);
            $table->unsignedBigInteger('changed_by');
            $table->dateTime('changed_at', 6);
            $table->unsignedBigInteger('reversal_payment_id')->nullable();
            $table->unsignedBigInteger('replacement_payment_id')->nullable();
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'payment_id', 'revision_number'], 'payment_rev_shop_payment_number_unique');
            $table->index(['shop_id', 'payment_id', 'changed_at'], 'payment_rev_shop_payment_changed_idx');

            $table->foreign('shop_id', 'payment_rev_shop_fk')
                ->references('id')->on('shops')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'payment_id'], 'payment_rev_shop_customer_payment_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('payments')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'reversal_payment_id'], 'payment_rev_shop_customer_reversal_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('payments')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'customer_id', 'replacement_payment_id'], 'payment_rev_shop_customer_replacement_fk')
                ->references(['shop_id', 'customer_id', 'id'])->on('payments')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'changed_by'], 'payment_rev_shop_actor_fk')
                ->references(['shop_id', 'id'])->on('users')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            "ALTER TABLE \`payment_revisions\`
             ADD CONSTRAINT \`payment_rev_number_check\` CHECK (\`revision_number\` > 0),
             ADD CONSTRAINT \`payment_rev_type_check\`
                 CHECK (\`revision_type\` IN ('REVERSAL', 'REPLACEMENT')),
             ADD CONSTRAINT \`payment_rev_reason_check\`
                 CHECK (CHAR_LENGTH(TRIM(\`correction_reason\`)) > 0),
             ADD CONSTRAINT \`payment_rev_reference_check\`
                 CHECK (
                     (\`revision_type\` = 'REVERSAL' AND \`reversal_payment_id\` IS NOT NULL)
                     OR (\`revision_type\` = 'REPLACEMENT' AND \`replacement_payment_id\` IS NOT NULL)
                 )"
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_revisions') && DB::table('payment_revisions')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty payment_revisions; preserve correction history.');
        }

        Schema::dropIfExists('payment_revisions');
    }
};
