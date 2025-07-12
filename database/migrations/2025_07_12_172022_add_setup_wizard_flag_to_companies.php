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
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('setup_wizard_completed')->default(false)->after('is_active');
            $table->timestamp('setup_completed_at')->nullable()->after('setup_wizard_completed');
            
            // Add index for efficient querying
            $table->index(['setup_wizard_completed', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['setup_wizard_completed', 'is_active']);
            $table->dropColumn(['setup_wizard_completed', 'setup_completed_at']);
        });
    }
};