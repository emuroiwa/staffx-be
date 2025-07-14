<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('iso_code', 3)->unique(); // ISO 3166-1 alpha-2/3
            $table->string('name');
            $table->string('currency_code', 3);
            $table->string('timezone');
            $table->json('regulatory_framework')->nullable();
            $table->boolean('is_supported_for_payroll')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['iso_code', 'is_active']);
            $table->index('is_supported_for_payroll');
        });
    }

    public function down()
    {
        Schema::dropIfExists('countries');
    }
};