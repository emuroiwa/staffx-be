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
        Schema::table('statutory_deduction_templates', function (Blueprint $table) {
            // Whether employer can optionally cover this deduction on behalf of employee
            $table->boolean('is_employer_payable')->default(false)->after('is_mandatory');
            
            // Whether employer covers the employee portion by default (can be overridden at company level)
            $table->boolean('employer_covers_employee_portion')->default(false)->after('is_employer_payable');
            
            // Whether employer-paid deduction is considered taxable benefit to employee
            $table->boolean('is_taxable_if_employer_paid')->default(false)->after('employer_covers_employee_portion');
            
            // Country reference for country-specific configuration
            $table->string('country_uuid')->nullable()->after('is_taxable_if_employer_paid');
            
            // Add index for better performance
            $table->index(['country_uuid', 'is_employer_payable'], 'idx_statutory_country_employer_payable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('statutory_deduction_templates', function (Blueprint $table) {
            $table->dropIndex('idx_statutory_country_employer_payable');
            $table->dropColumn([
                'is_employer_payable',
                'employer_covers_employee_portion', 
                'is_taxable_if_employer_paid',
                'country_uuid'
            ]);
        });
    }
};