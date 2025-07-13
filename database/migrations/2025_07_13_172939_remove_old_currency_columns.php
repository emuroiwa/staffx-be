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
        // Remove old currency columns from employees table
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        // Remove old currency columns from payrolls table
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore old currency columns to employees table
        Schema::table('employees', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('salary');
        });

        // Restore old currency columns to payrolls table
        Schema::table('payrolls', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('net_salary');
        });
    }
};
