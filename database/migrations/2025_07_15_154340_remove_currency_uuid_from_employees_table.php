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
            $table->dropForeign(['currency_uuid']);
            $table->dropIndex(['currency_uuid']);
            $table->dropColumn('currency_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->uuid('currency_uuid')->nullable()->after('salary');
            $table->foreign('currency_uuid')->references('uuid')->on('currencies')->onDelete('set null');
            $table->index(['currency_uuid']);
        });
    }
};
