<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'role']);
            $table->dropUnique(['mobile']);
            $table->dropForeign(['shop_id']);
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
