<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('statutory_deduction_templates', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('jurisdiction_uuid')->constrained('tax_jurisdictions', 'uuid')->onDelete('cascade');
            $table->string('deduction_type'); // income_tax, social_security, health_insurance, pension
            $table->string('code'); // PAYE, UIF, NHIF, NSSF, etc.
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('calculation_method'); // percentage, bracket, flat, salary_bracket
            $table->json('rules'); // calculation rules and parameters
            $table->decimal('minimum_salary', 12, 2)->nullable();
            $table->decimal('maximum_salary', 12, 2)->nullable();
            $table->decimal('employer_rate', 8, 4)->default(0); // Employer contribution rate
            $table->decimal('employee_rate', 8, 4)->default(0); // Employee deduction rate
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['jurisdiction_uuid', 'deduction_type'], 'sdt_jurisdiction_type_idx');
            $table->index(['effective_from', 'effective_to'], 'sdt_effective_period_idx');
            $table->unique(['jurisdiction_uuid', 'code', 'effective_from'], 'sdt_unique_template_period');
        });
    }

    public function down()
    {
        Schema::dropIfExists('statutory_deduction_templates');
    }
};