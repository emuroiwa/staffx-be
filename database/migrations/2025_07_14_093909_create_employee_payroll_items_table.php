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
        Schema::create('employee_payroll_items', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('employee_uuid');
            $table->uuid('template_uuid')->nullable();
            $table->uuid('statutory_template_uuid')->nullable();
            $table->string('code', 50);
            $table->string('name');
            $table->enum('type', ['allowance', 'deduction', 'benefit', 'statutory']);
            $table->enum('calculation_method', [
                'fixed_amount', 
                'percentage_of_salary', 
                'percentage_of_basic', 
                'formula', 
                'manual'
            ]);
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->text('formula_expression')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_recurring')->default(true);
            $table->enum('status', ['pending_approval', 'active', 'suspended', 'cancelled'])->default('active');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys - temporarily commented to avoid dependency issues
            // $table->foreign('employee_uuid')->references('uuid')->on('employees')->onDelete('cascade');
            // $table->foreign('template_uuid')->references('uuid')->on('company_payroll_templates')->onDelete('set null');
            // $table->foreign('statutory_template_uuid')->references('uuid')->on('statutory_deduction_templates')->onDelete('set null');
            // $table->foreign('approved_by')->references('uuid')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['employee_uuid', 'status'], 'epi_employee_status_idx');
            $table->index(['type', 'is_recurring'], 'epi_type_recurring_idx');
            $table->index(['effective_from', 'effective_to'], 'epi_effective_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_items');
    }
};
