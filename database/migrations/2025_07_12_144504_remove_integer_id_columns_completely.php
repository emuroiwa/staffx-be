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
        // First, we need to update all foreign key references to use UUIDs
        
        // Update employees table to use UUID foreign keys and primary key
        Schema::table('employees', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id')->unique();
            $table->uuid('user_uuid')->nullable()->after('user_id');
            $table->uuid('company_uuid')->nullable()->after('company_id');
        });
        
        // Generate UUIDs for existing employees
        $employees = DB::table('employees')->whereNull('uuid')->get();
        foreach ($employees as $employee) {
            DB::table('employees')->where('id', $employee->id)->update(['uuid' => \Illuminate\Support\Str::uuid()]);
        }
        
        // Populate the UUID foreign keys in employees
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
        
        // Update payrolls table to use UUID foreign keys and primary key
        Schema::table('payrolls', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id')->unique();
            $table->uuid('company_uuid')->nullable()->after('company_id');
            $table->uuid('employee_uuid')->nullable()->after('employee_id');
        });
        
        // Generate UUIDs for existing payrolls
        $payrolls = DB::table('payrolls')->whereNull('uuid')->get();
        foreach ($payrolls as $payroll) {
            DB::table('payrolls')->where('id', $payroll->id)->update(['uuid' => \Illuminate\Support\Str::uuid()]);
        }
        
        // Populate the UUID foreign keys in payrolls
        DB::statement('
            UPDATE payrolls p 
            INNER JOIN companies c ON p.company_id = c.id 
            SET p.company_uuid = c.uuid 
            WHERE p.company_id IS NOT NULL
        ');
        
        DB::statement('
            UPDATE payrolls p 
            INNER JOIN employees e ON p.employee_id = e.id 
            SET p.employee_uuid = e.uuid 
            WHERE p.employee_id IS NOT NULL
        ');
        
        // Update user_settings table to use UUID foreign keys
        if (Schema::hasTable('user_settings')) {
            if (!Schema::hasColumn('user_settings', 'user_uuid')) {
                Schema::table('user_settings', function (Blueprint $table) {
                    $table->uuid('user_uuid')->nullable()->after('user_id');
                });
            }
            
            DB::statement('
                UPDATE user_settings us 
                INNER JOIN users u ON us.user_id = u.id 
                SET us.user_uuid = u.uuid 
                WHERE us.user_id IS NOT NULL
            ');
        }
        
        // Update sessions table to use UUID foreign keys
        if (!Schema::hasColumn('sessions', 'user_uuid')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->uuid('user_uuid')->nullable()->after('user_id');
            });
        }
        
        DB::statement('
            UPDATE sessions s 
            INNER JOIN users u ON s.user_id = u.id 
            SET s.user_uuid = u.uuid 
            WHERE s.user_id IS NOT NULL
        ');
        
        // Now drop all foreign key constraints that reference integer IDs
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['company_id']);
        });
        
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['employee_id']);
        });
        
        if (Schema::hasTable('user_settings')) {
            Schema::table('user_settings', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }
        
        // Make UUID columns required and set as primary keys
        Schema::table('employees', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
        
        Schema::table('payrolls', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
        
        // Set UUID primary keys for employees and payrolls
        DB::statement('ALTER TABLE employees MODIFY id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE payrolls MODIFY id BIGINT UNSIGNED NOT NULL');
        
        Schema::table('employees', function (Blueprint $table) {
            $table->dropPrimary(['id']);
            $table->primary('uuid');
        });
        
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropPrimary(['id']);
            $table->primary('uuid');
        });
        
        // Now drop the integer ID columns
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('id');
        });
        
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('id');
        });
        
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('id');
        });
        
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('id');
        });
        
        // Drop the old integer foreign key columns (with error handling)
        try {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn(['user_id', 'company_id']);
            });
        } catch (\Exception $e) {
            // Columns might already be dropped
        }
        
        try {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropColumn(['company_id', 'employee_id']);
            });
        } catch (\Exception $e) {
            // Columns might already be dropped
        }
        
        if (Schema::hasTable('user_settings')) {
            try {
                Schema::table('user_settings', function (Blueprint $table) {
                    $table->dropColumn('user_id');
                });
            } catch (\Exception $e) {
                // Column might already be dropped
            }
        }
        
        try {
            Schema::table('sessions', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        } catch (\Exception $e) {
            // Column might already be dropped
        }
        
        // Add UUID foreign key constraints
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('set null');
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade');
        });
        
        Schema::table('payrolls', function (Blueprint $table) {
            $table->foreign('company_uuid')->references('uuid')->on('companies')->onDelete('cascade');
            $table->foreign('employee_uuid')->references('uuid')->on('employees')->onDelete('cascade');
        });
        
        if (Schema::hasTable('user_settings')) {
            Schema::table('user_settings', function (Blueprint $table) {
                $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            });
        }
        
        Schema::table('sessions', function (Blueprint $table) {
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a destructive migration - we won't implement rollback
        // as it would require recreating all the integer IDs and relationships
        throw new \Exception('This migration cannot be rolled back as it removes integer ID columns completely.');
    }
};
