<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_statutory_deduction_configurations', function (Blueprint $table) {
            $table->string('uuid')->primary();
            $table->string('company_uuid');
            $table->string('statutory_deduction_template_uuid');
            
            // Company-specific configuration
            $table->boolean('employer_covers_employee_portion')->default(false);
            $table->boolean('is_taxable_if_employer_paid')->default(false);
            $table->boolean('is_active')->default(true);
            
            // Optional overrides for company-specific calculations
            $table->decimal('employer_rate_override', 8, 4)->nullable();
            $table->decimal('employee_rate_override', 8, 4)->nullable();
            $table->decimal('minimum_salary_override', 15, 2)->nullable();
            $table->decimal('maximum_salary_override', 15, 2)->nullable();
            
            // Audit fields
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('company_uuid', 'fk_company_statutory_company')->references('uuid')->on('companies')->onDelete('cascade');
            $table->foreign('statutory_deduction_template_uuid', 'fk_company_statutory_template')->references('uuid')->on('statutory_deduction_templates')->onDelete('cascade');
            
            // Indexes
            $table->index(['company_uuid', 'is_active'], 'idx_company_statutory_active');
            $table->index(['statutory_deduction_template_uuid'], 'idx_company_statutory_template');
            $table->index(['effective_from', 'effective_to'], 'idx_company_statutory_effective');
            
            // Unique constraint to prevent duplicate configurations
            $table->unique(['company_uuid', 'statutory_deduction_template_uuid', 'effective_from'], 'unique_company_statutory_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_statutory_deduction_configurations');
    }
};