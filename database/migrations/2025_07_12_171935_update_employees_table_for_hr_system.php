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
        Schema::table('employees', function (Blueprint $table) {
            // Add new UUID foreign key columns
            $table->uuid('department_uuid')->nullable()->after('company_uuid');
            $table->uuid('position_uuid')->nullable()->after('department_uuid');
            $table->uuid('manager_uuid')->nullable()->after('position_uuid'); // Self-referencing for organogram
            
            // Add additional employee fields as per requirements
            $table->date('dob')->nullable()->after('phone'); // Date of birth
            $table->date('start_date')->nullable()->after('dob'); // Start date
            $table->boolean('is_director')->default(false)->after('employment_type');
            $table->boolean('is_independent_contractor')->default(false)->after('is_director');
            $table->boolean('is_uif_exempt')->default(false)->after('is_independent_contractor');
            $table->string('tax_number')->nullable()->after('currency');
            $table->json('bank_details')->nullable()->after('tax_number'); // Bank account info
            $table->enum('pay_frequency', ['weekly', 'bi_weekly', 'monthly', 'quarterly', 'annually'])->default('monthly')->after('bank_details');
            $table->string('national_id')->nullable()->after('pay_frequency');
            $table->string('passport_number')->nullable()->after('national_id');
            $table->string('emergency_contact_name')->nullable()->after('passport_number');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            
            // Remove old string-based department and position columns (they'll be replaced by UUIDs)
            // Note: We'll keep them for now and drop them in a separate migration after data migration
        });

        // Add foreign key constraints
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('department_uuid')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('position_uuid')->references('id')->on('positions')->onDelete('set null');
            $table->foreign('manager_uuid')->references('uuid')->on('employees')->onDelete('set null');
        });

        // Add indexes for performance
        Schema::table('employees', function (Blueprint $table) {
            $table->index(['company_uuid', 'department_uuid']);
            $table->index(['company_uuid', 'position_uuid']);
            $table->index(['company_uuid', 'manager_uuid']);
            $table->index(['department_uuid', 'status']);
            $table->index(['position_uuid', 'status']);
            $table->index(['national_id']);
            $table->index(['passport_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Drop foreign keys
            $table->dropForeign(['department_uuid']);
            $table->dropForeign(['position_uuid']);
            $table->dropForeign(['manager_uuid']);
            
            // Drop indexes
            $table->dropIndex(['company_uuid', 'department_uuid']);
            $table->dropIndex(['company_uuid', 'position_uuid']);
            $table->dropIndex(['company_uuid', 'manager_uuid']);
            $table->dropIndex(['department_uuid', 'status']);
            $table->dropIndex(['position_uuid', 'status']);
            $table->dropIndex(['national_id']);
            $table->dropIndex(['passport_number']);
            
            // Drop columns
            $table->dropColumn([
                'department_uuid',
                'position_uuid', 
                'manager_uuid',
                'dob',
                'start_date',
                'is_director',
                'is_independent_contractor',
                'is_uif_exempt',
                'tax_number',
                'bank_details',
                'pay_frequency',
                'national_id',
                'passport_number',
                'emergency_contact_name',
                'emergency_contact_phone'
            ]);
        });
    }
};