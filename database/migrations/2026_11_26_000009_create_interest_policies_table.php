<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interest_policies', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('policy_code', 40);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->unique(['shop_id', 'policy_code'], 'ip_shop_code_unique');
            $table->unique(['shop_id', 'id'], 'ip_shop_id_unique');
            $table->foreign('shop_id', 'ip_shop_fk')
                ->references('id')->on('shops')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('interest_policies') && DB::table('interest_policies')->exists()) {
            throw new RuntimeException('Refusing to drop non-empty interest_policies.');
        }

        Schema::dropIfExists('interest_policies');
    }
};
