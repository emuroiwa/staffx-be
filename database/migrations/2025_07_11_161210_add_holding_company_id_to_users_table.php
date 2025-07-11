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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('holding_company_id')->nullable()->constrained()->onDelete('cascade');
            
            // Update role enum to include holding_company_admin
            $table->enum('role', ['holding_company_admin', 'admin', 'manager', 'hr', 'employee'])->default('employee')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['holding_company_id']);
            $table->dropColumn('holding_company_id');
            
            // Restore original role enum
            $table->enum('role', ['admin', 'manager', 'hr', 'employee'])->default('employee')->change();
        });
    }
};
