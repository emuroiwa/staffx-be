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
            // Add company relationship
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            
            // Add role for company-level permissions
            $table->enum('role', ['admin', 'manager', 'hr', 'employee'])->default('employee')->after('company_id');
            
            // Convert company string to company_id (we'll handle this in a seeder/data migration)
            // For now, we'll keep both fields until we migrate the data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn(['company_id', 'role']);
        });
    }
};
