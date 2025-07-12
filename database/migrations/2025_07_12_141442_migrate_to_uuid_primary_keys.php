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
        // Step 1: Add new UUID columns alongside existing integer IDs
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id')->unique();
        });
        
        Schema::table('companies', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id')->unique();
        });
        
        // Step 2: Add UUID foreign key columns
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('company_uuid')->nullable()->after('company_id');
            $table->uuid('default_company_uuid')->nullable()->after('default_company_id');
        });
        
        Schema::table('companies', function (Blueprint $table) {
            $table->uuid('created_by_uuid')->nullable()->after('created_by');
        });
        
        // Step 3: Add UUID columns to other tables that reference users/companies
        // For now, we'll focus on the main models (users/companies) and add other table UUIDs later
        // if (Schema::hasTable('employees')) {
        //     Schema::table('employees', function (Blueprint $table) {
        //         $table->uuid('user_uuid')->nullable()->after('user_id');
        //         $table->uuid('company_uuid')->nullable()->after('company_id');
        //     });
        // }
        
        // if (Schema::hasTable('payrolls')) {
        //     Schema::table('payrolls', function (Blueprint $table) {
        //         $table->uuid('company_uuid')->nullable()->after('company_id');
        //         $table->uuid('employee_uuid')->nullable()->after('employee_id');
        //     });
        // }
        
        if (Schema::hasTable('user_settings')) {
            Schema::table('user_settings', function (Blueprint $table) {
                $table->uuid('user_uuid')->nullable()->after('user_id');
            });
        }
        
        // Step 4: Update sessions table to use UUID
        Schema::table('sessions', function (Blueprint $table) {
            $table->uuid('user_uuid')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove UUID columns
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'company_uuid', 'default_company_uuid']);
        });
        
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'created_by_uuid']);
        });
        
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn(['user_uuid', 'company_uuid']);
            });
        }
        
        if (Schema::hasTable('payrolls')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropColumn(['company_uuid', 'employee_uuid']);
            });
        }
        
        if (Schema::hasTable('user_settings')) {
            Schema::table('user_settings', function (Blueprint $table) {
                $table->dropColumn('user_uuid');
            });
        }
        
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_uuid');
        });
    }
};
