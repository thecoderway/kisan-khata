<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_interest_policy_versions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedInteger('version_number');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('monthly_rate_percent', 12, 8)->nullable();
            $table->boolean('capitalization_enabled')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->dateTime('created_at', 6);

            $table->unique(['shop_id', 'customer_id', 'version_number'], 'cipv_shop_customer_version_unique');
            $table->unique(['shop_id', 'id'], 'cipv_shop_id_unique');
            $table->unique(['shop_id', 'customer_id', 'id'], 'cipv_shop_customer_id_unique');
            $table->index(['shop_id', 'customer_id', 'effective_from', 'effective_to'], 'cipv_shop_customer_effective_idx');
            $table->foreign(['shop_id', 'customer_id'], 'cipv_shop_customer_fk')
                ->references(['shop_id', 'id'])->on('customers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['shop_id', 'created_by'], 'cipv_shop_creator_fk')
                ->references(['shop_id', 'id'])->on('users')->restrictOnDelete()->restrictOnUpdate();
        });

        DB::statement(
            'ALTER TABLE customer_interest_policy_versions
             ADD CONSTRAINT cipv_version_check CHECK (version_number > 0),
             ADD CONSTRAINT cipv_rate_check CHECK (monthly_rate_percent IS NULL OR monthly_rate_percent >= 0),
             ADD CONSTRAINT cipv_effective_range_check CHECK (effective_to IS NULL OR effective_to > effective_from),
             ADD CONSTRAINT cipv_nonempty_override_check
                 CHECK (monthly_rate_percent IS NOT NULL OR capitalization_enabled IS NOT NULL)'
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_interest_policy_versions') && DB::table('customer_interest_policy_versions')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty customer_interest_policy_versions.');
        }

        Schema::dropIfExists('customer_interest_policy_versions');
    }
};
