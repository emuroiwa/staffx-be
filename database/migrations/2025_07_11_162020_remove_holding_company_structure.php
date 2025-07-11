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
        // Remove holding company foreign key from companies and add created_by
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['holding_company_id']);
            $table->dropColumn('holding_company_id');
            
            // Add created_by to track which HCA user created this company
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            // Re-add subscription_expires_at since companies can have individual subscriptions
            $table->timestamp('subscription_expires_at')->nullable();
        });

        // Remove holding company foreign key from users and add trial fields
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['holding_company_id']);
            $table->dropColumn('holding_company_id');
            
            // Add trial management for HCA users
            $table->timestamp('trial_expires_at')->nullable();
            
            // Add default company for HCA users
            $table->foreignId('default_company_id')->nullable()->constrained('companies')->onDelete('set null');
        });

        // Drop the holding companies table
        Schema::dropIfExists('holding_companies');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate holding companies table
        Schema::create('holding_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('website')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('trial_expires_at')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->string('subscription_plan')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        // Restore companies table structure
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'subscription_expires_at']);
            
            $table->foreignId('holding_company_id')->constrained()->onDelete('cascade');
        });

        // Restore users table structure
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_company_id']);
            $table->dropColumn(['trial_expires_at', 'default_company_id']);
            
            $table->foreignId('holding_company_id')->nullable()->constrained()->onDelete('cascade');
        });
    }
};
