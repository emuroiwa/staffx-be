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
        // Set default currency for companies without one
        $zarCurrency = DB::table('currencies')->where('code', 'ZAR')->first();
        
        if ($zarCurrency) {
            DB::table('companies')
                ->whereNull('currency_uuid')
                ->update(['currency_uuid' => $zarCurrency->uuid]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the currency_uuid from companies
        DB::table('companies')->update(['currency_uuid' => null]);
    }
};