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
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_uuid');
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('head_of_department_id')->nullable(); // Employee who heads this department
            $table->string('cost_center')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('budget_info')->nullable(); // Budget allocation, limits, etc.
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade');
            
            // Note: head_of_department_id foreign key will be added after employees table is created
            
            // Indexes
            $table->index(['company_uuid', 'is_active']);
            $table->index(['company_uuid', 'name']);
            
            // Unique constraint within company
            $table->unique(['company_uuid', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};