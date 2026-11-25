<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unique(['shop_id', 'id'], 'users_shop_id_id_unique');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->unique(['shop_id', 'id'], 'customers_shop_id_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_shop_id_id_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_shop_id_id_unique');
        });
    }
};
