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
        Schema::table('payrolls', function (Blueprint $table) {
            // Batch payroll fields
            $table->date('payroll_period_start')->nullable()->after('payroll_number');
            $table->date('payroll_period_end')->nullable()->after('payroll_period_start');
            $table->integer('total_employees')->default(0)->after('payroll_period_end');
            $table->decimal('total_gross_salary', 12, 2)->default(0)->after('total_employees');
            $table->decimal('total_net_salary', 12, 2)->default(0)->after('total_gross_salary');
            $table->decimal('total_employer_contributions', 12, 2)->default(0)->after('total_deductions');
            
            // Approval and processing fields
            $table->timestamp('calculated_at')->nullable()->after('notes');
            $table->timestamp('approved_at')->nullable()->after('calculated_at');
            $table->uuid('approved_by')->nullable()->after('approved_at');
            $table->timestamp('processed_at')->nullable()->after('approved_by');
            $table->uuid('created_by')->nullable()->after('processed_at');
            
            // Indexes for performance
            $table->index(['status'], 'payrolls_status_idx');
            $table->index(['payroll_period_start', 'payroll_period_end'], 'payrolls_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex('payrolls_status_idx');
            $table->dropIndex('payrolls_period_idx');
            
            $table->dropColumn([
                'payroll_period_start',
                'payroll_period_end', 
                'total_employees',
                'total_gross_salary',
                'total_net_salary',
                'total_employer_contributions',
                'calculated_at',
                'approved_at',
                'approved_by',
                'processed_at',
                'created_by'
            ]);
        });
    }
};
