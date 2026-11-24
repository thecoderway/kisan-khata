<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This is the initial tenancy migration. Existing accounts have no
        // approved role/shop mapping yet, so never assign them an arbitrary role.
        if (DB::table('users')->exists()) {
            throw new RuntimeException(
                'Cannot apply the shop database migration while users already exist. '
                .'Define and run an explicit user role/shop mapping before applying this migration.'
            );
        }

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('owner_name');
            $table->string('mobile', 20);
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('village')->nullable();
            $table->string('tehsil')->nullable();
            $table->string('district')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 12)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('preferred_language', 10)->default('hi');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['status', 'name']);
        });

        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('setting_key', 100);
            $table->longText('setting_value')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'setting_key']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('shop_id')
                ->nullable()
                ->after('id')
                ->constrained('shops')
                ->restrictOnDelete();

            $table->string('email')->nullable()->change();
            $table->string('mobile', 20)->nullable()->unique();
            $table->timestamp('mobile_verified_at')->nullable();
            $table->string('role', 32);
            $table->string('preferred_language', 10)->default('hi');
            $table->boolean('is_active')->default(true);

            $table->index(['shop_id', 'role']);
        });
    }

    public function down(): void
    {
        // The original users schema requires email. Refuse rollback before
        // changing anything if mobile-only accounts have since been created.
        if (DB::table('users')->whereNull('email')->exists()) {
            throw new RuntimeException(
                'Cannot roll back the shop database migration while users with a null email exist. '
                .'Assign an email to those users before retrying the rollback.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            // MySQL requires the foreign key to be removed before its supporting
            // index can be dropped.
            $table->dropForeign(['shop_id']);
            $table->dropIndex(['shop_id', 'role']);
            $table->dropUnique(['mobile']);
            $table->dropColumn([
                'shop_id',
                'mobile',
                'mobile_verified_at',
                'role',
                'preferred_language',
                'is_active',
            ]);

            $table->string('email')->nullable(false)->change();
        });

        Schema::dropIfExists('shop_settings');
        Schema::dropIfExists('shops');
    }
};
