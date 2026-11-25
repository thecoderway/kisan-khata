<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->string('state_code', 20)->nullable()->after('state');
            $table->string('district_code', 20)->nullable()->after('state_code');
            $table->string('subdistrict_code', 20)->nullable()->after('district_code');
            $table->string('village_code', 20)->nullable()->after('subdistrict_code');
            $table->index(['state_code', 'district_code', 'subdistrict_code'], 'shops_lgd_location_idx');
            $table->foreign('village_code')->references('villageCode')->on('villages')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropForeign(['village_code']);
            $table->dropIndex('shops_lgd_location_idx');
            $table->dropColumn(['state_code', 'district_code', 'subdistrict_code', 'village_code']);
        });
    }
};
