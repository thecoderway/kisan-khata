<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->string('customer_code', 50)->nullable();
            $table->string('name_original');
            $table->string('name_original_language', 10);
            $table->string('name_hi')->nullable();
            $table->string('name_en')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('state_code', 20)->nullable();
            $table->string('district_code', 20)->nullable();
            $table->string('subdistrict_code', 20)->nullable();
            $table->string('village_code', 20)->nullable();
            $table->foreign('village_code')->references('villageCode')->on('villages')->restrictOnDelete();
            $table->text('address_original')->nullable();
            $table->string('address_original_language', 10)->nullable();
            $table->text('address_hi')->nullable();
            $table->text('address_en')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['shop_id', 'customer_code'], 'customers_shop_code_unique');
            $table->index(['shop_id', 'mobile'], 'customers_shop_mobile_idx');
            $table->index(['shop_id', 'name_original'], 'customers_shop_name_idx');
            $table->index(['shop_id', 'status', 'name_original'], 'customers_shop_status_name_idx');
            $table->index(['state_code', 'district_code', 'subdistrict_code'], 'customers_lgd_location_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
