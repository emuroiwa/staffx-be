<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For now, keep the old ID columns for backward compatibility with other tables
        // Make them nullable since they're no longer primary keys
        // The primary key is now UUID, but ID will still be used by foreign keys
        
        DB::statement('ALTER TABLE users MODIFY id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE companies MODIFY id BIGINT UNSIGNED NULL');
        
        // Drop the old foreign key columns that we're replacing with UUID versions
        try {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropForeign(['created_by']);
            });
        } catch (\Exception $e) {
            // Constraint might not exist
        }
        
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropForeign(['default_company_id']);
            });
        } catch (\Exception $e) {
            // Constraints might not exist
        }
        
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['company_id', 'default_company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the old integer columns
        Schema::table('users', function (Blueprint $table) {
            $table->id()->first();
            $table->foreignId('company_id')->nullable()->after('uuid');
            $table->foreignId('default_company_id')->nullable()->after('company_uuid');
        });
        
        Schema::table('companies', function (Blueprint $table) {
            $table->id()->first();
            $table->foreignId('created_by')->nullable()->after('uuid');
        });
    }
};
