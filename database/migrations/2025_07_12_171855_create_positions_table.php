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
        Schema::create('positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_uuid');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('min_salary', 12, 2)->nullable();
            $table->decimal('max_salary', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->json('requirements')->nullable(); // Skills, qualifications, etc.
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade');
            
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
        Schema::dropIfExists('positions');
    }
};