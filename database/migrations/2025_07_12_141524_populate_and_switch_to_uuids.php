<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Generate UUIDs for existing records
        $users = DB::table('users')->whereNull('uuid')->get();
        foreach ($users as $user) {
            DB::table('users')->where('id', $user->id)->update(['uuid' => Str::uuid()]);
        }
        
        $companies = DB::table('companies')->whereNull('uuid')->get();
        foreach ($companies as $company) {
            DB::table('companies')->where('id', $company->id)->update(['uuid' => Str::uuid()]);
        }
        
        // Update foreign key UUID columns based on existing relationships
        DB::statement('
            UPDATE users u 
            INNER JOIN companies c ON u.company_id = c.id 
            SET u.company_uuid = c.uuid 
            WHERE u.company_id IS NOT NULL
        ');
        
        DB::statement('
            UPDATE users u 
            INNER JOIN companies c ON u.default_company_id = c.id 
            SET u.default_company_uuid = c.uuid 
            WHERE u.default_company_id IS NOT NULL
        ');
        
        DB::statement('
            UPDATE companies c 
            INNER JOIN users u ON c.created_by = u.id 
            SET c.created_by_uuid = u.uuid 
            WHERE c.created_by IS NOT NULL
        ');
        
        // Update other tables if they exist and have UUID columns
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'user_uuid')) {
            DB::statement('
                UPDATE employees e 
                INNER JOIN users u ON e.user_id = u.id 
                SET e.user_uuid = u.uuid 
                WHERE e.user_id IS NOT NULL
            ');
            
            DB::statement('
                UPDATE employees e 
                INNER JOIN companies c ON e.company_id = c.id 
                SET e.company_uuid = c.uuid 
                WHERE e.company_id IS NOT NULL
            ');
        }
        
        if (Schema::hasTable('payrolls') && Schema::hasColumn('payrolls', 'company_uuid')) {
            DB::statement('
                UPDATE payrolls p 
                INNER JOIN companies c ON p.company_id = c.id 
                SET p.company_uuid = c.uuid 
                WHERE p.company_id IS NOT NULL
            ');
            
            if (Schema::hasColumn('payrolls', 'employee_uuid')) {
                DB::statement('
                    UPDATE payrolls p 
                    INNER JOIN employees e ON p.employee_id = e.id 
                    SET p.employee_uuid = e.uuid 
                    WHERE p.employee_id IS NOT NULL AND e.uuid IS NOT NULL
                ');
            }
        }
        
        if (Schema::hasTable('user_settings')) {
            DB::statement('
                UPDATE user_settings us 
                INNER JOIN users u ON us.user_id = u.id 
                SET us.user_uuid = u.uuid 
                WHERE us.user_id IS NOT NULL
            ');
        }
        
        // Update sessions table
        DB::statement('
            UPDATE sessions s 
            INNER JOIN users u ON s.user_id = u.id 
            SET s.user_uuid = u.uuid 
            WHERE s.user_id IS NOT NULL
        ');
        
        // Now make UUIDs required and switch primary keys
        
        // Step 1: Drop existing foreign key constraints
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['default_company_id']);
        });
        
        // Step 2: Make UUID columns required
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
        
        Schema::table('companies', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
        
        // Step 3: Drop old primary keys and set new ones
        // First, make id column not auto-incrementing 
        DB::statement('ALTER TABLE users MODIFY id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE companies MODIFY id BIGINT UNSIGNED NOT NULL');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropPrimary(['id']);
            $table->primary('uuid');
        });
        
        Schema::table('companies', function (Blueprint $table) {
            $table->dropPrimary(['id']);
            $table->primary('uuid');
        });
        
        // Step 4: Add foreign key constraints for UUID columns (skip for now to avoid circular references)
        // We'll add these constraints later once both tables are properly set up
        // Schema::table('users', function (Blueprint $table) {
        //     $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('set null');
        //     $table->foreign('default_company_uuid')->references('uuid')->on('companies')->onDelete('set null');
        // });
        
        // Schema::table('companies', function (Blueprint $table) {
        //     $table->foreign('created_by_uuid')->references('uuid')->on('users')->onDelete('set null');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop UUID foreign keys
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_uuid']);
            $table->dropForeign(['default_company_uuid']);
        });
        
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['created_by_uuid']);
        });
        
        // Restore integer primary keys
        Schema::table('users', function (Blueprint $table) {
            $table->dropPrimary(['uuid']);
            $table->primary('id');
        });
        
        Schema::table('companies', function (Blueprint $table) {
            $table->dropPrimary(['uuid']);
            $table->primary('id');
        });
        
        // Restore old foreign key constraints
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('default_company_id')->references('id')->on('companies')->onDelete('set null');
        });
    }
};
