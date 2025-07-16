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
        Schema::table('employee_payroll_items', function (Blueprint $table) {
            // Update existing type enum to include garnishment
            $table->dropColumn('type');
        });

        Schema::table('employee_payroll_items', function (Blueprint $table) {
            $table->enum('type', ['allowance', 'deduction', 'benefit', 'statutory', 'garnishment'])->after('name');
            
            // Add garnishment-specific fields
            $table->string('court_order_number')->nullable()->after('notes');
            $table->enum('garnishment_type', [
                'wage_garnishment', 
                'child_support', 
                'tax_levy', 
                'student_loan', 
                'bankruptcy', 
                'other'
            ])->nullable()->after('court_order_number');
            $table->string('garnishment_authority')->nullable()->after('garnishment_type');
            $table->decimal('maximum_percentage', 5, 2)->nullable()->after('garnishment_authority');
            $table->integer('priority_order')->nullable()->after('maximum_percentage');
            $table->json('contact_information')->nullable()->after('priority_order');
            $table->text('legal_reference')->nullable()->after('contact_information');
            $table->date('garnishment_start_date')->nullable()->after('legal_reference');
            $table->date('garnishment_end_date')->nullable()->after('garnishment_start_date');
            $table->decimal('total_amount_to_garnish', 15, 2)->nullable()->after('garnishment_end_date');
            $table->decimal('amount_garnished_to_date', 15, 2)->default(0)->after('total_amount_to_garnish');
            
            // Add indexes for garnishment queries
            $table->index(['type', 'priority_order'], 'epi_garnishment_priority_idx');
            $table->index(['garnishment_start_date', 'garnishment_end_date'], 'epi_garnishment_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_payroll_items', function (Blueprint $table) {
            // Drop garnishment-specific fields
            $table->dropColumn([
                'court_order_number',
                'garnishment_type',
                'garnishment_authority',
                'maximum_percentage',
                'priority_order',
                'contact_information',
                'legal_reference',
                'garnishment_start_date',
                'garnishment_end_date',
                'total_amount_to_garnish',
                'amount_garnished_to_date'
            ]);
            
            // Drop garnishment indexes
            $table->dropIndex('epi_garnishment_priority_idx');
            $table->dropIndex('epi_garnishment_period_idx');
            
            // Restore original type enum
            $table->dropColumn('type');
        });

        Schema::table('employee_payroll_items', function (Blueprint $table) {
            $table->enum('type', ['allowance', 'deduction', 'benefit', 'statutory'])->after('name');
        });
    }
};