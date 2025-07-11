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
            // Check if trial_expires_at exists and remove it
            if (Schema::hasColumn('users', 'trial_expires_at')) {
                $table->dropColumn('trial_expires_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add back trial_expires_at
            $table->timestamp('trial_expires_at')->nullable()->after('default_company_id');
        });
    }
};
