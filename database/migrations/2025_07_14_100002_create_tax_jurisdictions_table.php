<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tax_jurisdictions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('country_uuid')->constrained('countries', 'uuid')->onDelete('cascade');
            $table->string('region_code')->nullable(); // State/Province code
            $table->string('name');
            $table->date('tax_year_start'); // e.g., 2025-03-01 for SA
            $table->date('tax_year_end');   // e.g., 2026-02-28 for SA
            $table->string('regulatory_authority'); // SARS, FIRS, KRA, etc.
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->json('settings')->nullable(); // Additional jurisdiction-specific settings
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['country_uuid', 'effective_from']);
            $table->index(['effective_from', 'effective_to']);
            $table->unique(['country_uuid', 'region_code', 'effective_from'], 'unique_jurisdiction_period');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tax_jurisdictions');
    }
};