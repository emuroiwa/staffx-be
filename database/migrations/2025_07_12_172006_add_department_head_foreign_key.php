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
        Schema::table('departments', function (Blueprint $table) {
            // Add foreign key constraint for head_of_department_id
            $table->foreign('head_of_department_id')->references('uuid')->on('employees')->onDelete('set null');
            
            // Add index
            $table->index(['company_uuid', 'head_of_department_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['head_of_department_id']);
            $table->dropIndex(['company_uuid', 'head_of_department_id']);
        });
    }
};