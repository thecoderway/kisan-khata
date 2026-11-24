<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villages', function (Blueprint $table): void {
            // Keep the source column names and order aligned with the LGD CSV
            // so the MP dataset can be imported directly through phpMyAdmin.
            // LGD codes are strings to preserve their source representation.
            $table->string('villageCode', 20)->primary();
            $table->string('villageNameEnglish', 255);
            $table->string('villageNameLocal', 255)->nullable();
            $table->string('villageCensus2011Code', 20)->nullable();
            $table->string('subdistrictCode', 20);
            $table->string('subdistrictNameEnglish', 255);
            $table->string('subdistrictNameLocal', 255)->nullable();
            $table->string('subdistrictCensus2011Code', 20)->nullable();
            $table->string('districtCode', 20);
            $table->string('districtNameEnglish', 255);
            $table->string('districtNameLocal', 255)->nullable();
            $table->string('districtCensus2011Code', 20)->nullable();
            $table->string('stateCode', 20);
            $table->string('stateNameEnglish', 255);
            $table->string('stateNameLocal', 255)->nullable();
            $table->string('stateCensus2011Code', 20)->nullable();

            // The source CSV uses DD-MM-YYYY (e.g. 06-01-2025).
            // Store it as supplied so direct CSV import does not fail on
            // MySQL DATE parsing or silently reinterpret the date.
            $table->string('data_gov_update_date', 20)->nullable();

            // Village names are deliberately non-unique. The same name can
            // exist in multiple tehsils/districts, including within one district.
            $table->index('subdistrictCode', 'villages_subdistrict_code_idx');
            $table->index('districtCode', 'villages_district_code_idx');
            $table->index('stateCode', 'villages_state_code_idx');
            $table->index(
                ['stateCode', 'districtCode', 'subdistrictCode'],
                'villages_admin_hierarchy_idx'
            );
            $table->index(
                ['villageNameEnglish', 'districtCode', 'subdistrictCode'],
                'villages_name_location_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
    }
};
