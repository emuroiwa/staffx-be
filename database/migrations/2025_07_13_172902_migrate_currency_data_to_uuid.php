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
        // Migrate existing currency data from string to UUID reference
        
        // For employees table
        $employeesWithCurrency = DB::table('employees')->whereNotNull('currency')->get();
        foreach ($employeesWithCurrency as $employee) {
            $currency = DB::table('currencies')->where('code', $employee->currency)->first();
            if ($currency) {
                DB::table('employees')
                    ->where('uuid', $employee->uuid)
                    ->update(['currency_uuid' => $currency->uuid]);
            }
        }

        // For payrolls table
        $payrollsWithCurrency = DB::table('payrolls')->whereNotNull('currency')->get();
        foreach ($payrollsWithCurrency as $payroll) {
            $currency = DB::table('currencies')->where('code', $payroll->currency)->first();
            if ($currency) {
                DB::table('payrolls')
                    ->where('uuid', $payroll->uuid)
                    ->update(['currency_uuid' => $currency->uuid]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore currency codes from UUID references
        
        // For employees table
        $employeesWithCurrencyUuid = DB::table('employees')->whereNotNull('currency_uuid')->get();
        foreach ($employeesWithCurrencyUuid as $employee) {
            $currency = DB::table('currencies')->where('uuid', $employee->currency_uuid)->first();
            if ($currency) {
                DB::table('employees')
                    ->where('uuid', $employee->uuid)
                    ->update(['currency' => $currency->code]);
            }
        }

        // For payrolls table
        $payrollsWithCurrencyUuid = DB::table('payrolls')->whereNotNull('currency_uuid')->get();
        foreach ($payrollsWithCurrencyUuid as $payroll) {
            $currency = DB::table('currencies')->where('uuid', $payroll->currency_uuid)->first();
            if ($currency) {
                DB::table('payrolls')
                    ->where('uuid', $payroll->uuid)
                    ->update(['currency' => $currency->code]);
            }
        }
    }
};
