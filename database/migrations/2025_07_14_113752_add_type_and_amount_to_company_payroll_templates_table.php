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
            $table->string('type')->default('allowance')->after('description');
            $table->decimal('amount', 10, 2)->nullable()->after('calculation_method');
            
            // Index for performance
            $table->index(['type'], 'company_payroll_templates_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_payroll_templates', function (Blueprint $table) {
            $table->dropIndex('company_payroll_templates_type_idx');
            $table->dropColumn(['type', 'amount']);
        });
    }
};
