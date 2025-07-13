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
        // Add currency_uuid column to employees table
        Schema::table('employees', function (Blueprint $table) {
            $table->uuid('currency_uuid')->nullable()->after('currency');
            $table->foreign('currency_uuid')->references('uuid')->on('currencies')->onDelete('set null');
            $table->index(['currency_uuid']);
        });

        // Add currency_uuid column to payrolls table
        Schema::table('payrolls', function (Blueprint $table) {
            $table->uuid('currency_uuid')->nullable()->after('currency');
            $table->foreign('currency_uuid')->references('uuid')->on('currencies')->onDelete('set null');
            $table->index(['currency_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove currency_uuid from employees table
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['currency_uuid']);
            $table->dropIndex(['currency_uuid']);
            $table->dropColumn('currency_uuid');
        });

        // Remove currency_uuid from payrolls table
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['currency_uuid']);
            $table->dropIndex(['currency_uuid']);
            $table->dropColumn('currency_uuid');
        });
    }
};
