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
        Schema::table('company_payroll_templates', function (Blueprint $table) {
            // Change amount field precision to match other amount fields
            $table->decimal('amount', 15, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_payroll_templates', function (Blueprint $table) {
            // Revert to original precision
            $table->decimal('amount', 10, 2)->nullable()->change();
        });
    }
};