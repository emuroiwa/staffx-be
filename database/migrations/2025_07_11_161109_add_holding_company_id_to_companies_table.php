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
            $table->foreignId('holding_company_id')->constrained()->onDelete('cascade');
            
            // Remove subscription fields from companies as they now belong to holding company
            $table->dropColumn(['subscription_expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['holding_company_id']);
            $table->dropColumn('holding_company_id');
            
            // Restore subscription fields
            $table->timestamp('subscription_expires_at')->nullable();
        });
    }
};
