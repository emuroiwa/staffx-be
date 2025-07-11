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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->string('payroll_number')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('base_salary', 12, 2);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('overtime_rate', 8, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('gross_salary', 12, 2);
            $table->json('deductions')->nullable(); // tax, insurance, etc.
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['draft', 'approved', 'processed', 'paid'])->default('draft');
            $table->date('pay_date')->nullable();
            $table->string('payment_method')->nullable(); // bank_transfer, cash, check
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'status']);
            $table->index(['period_start', 'period_end']);
            $table->index(['pay_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
