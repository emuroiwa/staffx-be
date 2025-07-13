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
        Schema::create('currencies', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('code', 3)->unique(); // ISO 4217 currency code (USD, EUR, etc.)
            $table->string('name'); // Full currency name
            $table->string('symbol'); // Currency symbol ($, €, £, etc.)
            $table->decimal('exchange_rate', 12, 6)->default(1.000000); // Exchange rate to base currency
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['is_active']);
            $table->index(['code', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
