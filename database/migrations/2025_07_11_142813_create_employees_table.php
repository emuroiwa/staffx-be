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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('employee_id')->nullable(); // Company-specific employee ID
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('employment_type')->default('full_time'); // full_time, part_time, contract
            $table->decimal('salary', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'terminated'])->default('active');
            $table->json('benefits')->nullable();
            $table->json('documents')->nullable(); // Store file paths
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['company_id', 'employee_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'department']);
            $table->index(['email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
