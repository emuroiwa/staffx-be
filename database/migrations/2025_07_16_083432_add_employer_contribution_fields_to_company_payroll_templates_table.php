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
        Schema::table('company_payroll_templates', function (Blueprint $table) {
            // Update the type enum to include employer_contribution
            $table->enum('type', ['allowance', 'deduction', 'employer_contribution'])->default('allowance')->change();
            
            // Add employer contribution specific fields
            $table->string('contribution_type')->nullable()->after('type');
            $table->boolean('has_employee_match')->default(false)->after('contribution_type');
            $table->enum('match_logic', ['equal', 'percentage', 'custom'])->nullable()->after('has_employee_match');
            $table->decimal('employee_match_amount', 15, 2)->nullable()->after('match_logic');
            $table->decimal('employee_match_percentage', 5, 2)->nullable()->after('employee_match_amount');
            
            // Add indexes for performance
            $table->index(['type', 'contribution_type'], 'cpt_type_contribution_idx');
            $table->index(['has_employee_match'], 'cpt_has_match_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_payroll_templates', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('cpt_type_contribution_idx');
            $table->dropIndex('cpt_has_match_idx');
            
            // Drop the new columns
            $table->dropColumn([
                'contribution_type',
                'has_employee_match', 
                'match_logic',
                'employee_match_amount',
                'employee_match_percentage'
            ]);
            
            // Revert the type enum to original values
            $table->enum('type', ['allowance', 'deduction'])->default('allowance')->change();
        });
    }
};