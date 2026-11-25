<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasInvalidCreator = DB::table('customers as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.created_by')
            ->whereNull('u.id')
            ->orWhereNull('u.shop_id')
            ->orWhereColumn('c.shop_id', '<>', 'u.shop_id')
            ->exists();

        if ($hasInvalidCreator) {
            throw new RuntimeException(
                'Cannot enforce the same-Shop customer creator constraint while existing customers '
                .'have a missing creator, a platform-level creator, or a creator from another shop. '
                .'Resolve the data through an explicitly approved remediation plan before retrying.'
            );
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->index(
                ['shop_id', 'created_by'],
                'customers_shop_creator_idx'
            );
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign('customers_created_by_foreign');

            $table->foreign(
                ['shop_id', 'created_by'],
                'customers_shop_creator_foreign'
            )
                ->references(['shop_id', 'id'])
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign('customers_shop_creator_foreign');

            $table->foreign('created_by', 'customers_created_by_foreign')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->dropIndex('customers_shop_creator_idx');
        });
    }
};
