<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payroll_item_categories', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('name'); // Income, Allowance, Benefit, Deduction, Tax, Employer Contribution
            $table->string('type'); // earning, deduction, tax, employer_cost
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_statutory')->default(false);
            $table->boolean('affects_gross')->default(true); // Whether this affects gross salary calculation
            $table->boolean('is_taxable')->default(true); // Default taxability
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['type', 'is_active']);
            $table->index('display_order');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payroll_item_categories');
    }
};