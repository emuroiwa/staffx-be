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
        Schema::create('company_payroll_templates', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('company_uuid');
            $table->uuid('category_uuid')->nullable();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('calculation_method', [
                'fixed_amount', 
                'percentage_of_salary', 
                'percentage_of_basic', 
                'formula', 
                'manual'
            ]);
            $table->decimal('default_amount', 15, 2)->nullable();
            $table->decimal('default_percentage', 5, 2)->nullable();
            $table->text('formula_expression')->nullable();
            $table->decimal('minimum_amount', 15, 2)->nullable();
            $table->decimal('maximum_amount', 15, 2)->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_pensionable')->default(true);
            $table->json('eligibility_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_approval')->default(false);
            $table->timestamps();

            // Foreign keys - temporarily removed due to dependency issues
            // $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade');
            // $table->foreign('category_uuid')->references('uuid')->on('payroll_item_categories')->onDelete('set null');
            
            // Indexes
            $table->index(['company_uuid', 'is_active'], 'cpt_company_active_idx');
            $table->index(['calculation_method'], 'cpt_method_idx');
            $table->index(['code'], 'cpt_code_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_payroll_templates');
    }
};
