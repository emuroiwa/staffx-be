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
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('payroll_uuid');
            $table->uuid('employee_uuid');
            $table->string('code');
            $table->string('name');
            $table->string('type'); // allowance, deduction, income_tax, etc.
            $table->decimal('amount', 12, 2);
            $table->json('calculation_details')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('payroll_uuid')->references('uuid')->on('payrolls')->onDelete('cascade');
            $table->foreign('employee_uuid')->references('uuid')->on('employees')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['payroll_uuid'], 'payroll_items_payroll_idx');
            $table->index(['employee_uuid'], 'payroll_items_employee_idx');
            $table->index(['type'], 'payroll_items_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
