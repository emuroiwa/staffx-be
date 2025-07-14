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
            $table->uuid('country_uuid')->nullable()->after('created_by_uuid');
            
            // Foreign key constraint - temporarily commented to avoid dependency issues
            // $table->foreign('country_uuid')->references('uuid')->on('countries')->onDelete('set null');
            
            // Index for performance
            $table->index(['country_uuid'], 'companies_country_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_country_idx');
            // $table->dropForeign(['country_uuid']);
            $table->dropColumn('country_uuid');
        });
    }
};
