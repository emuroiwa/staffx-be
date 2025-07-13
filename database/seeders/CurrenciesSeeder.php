<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CurrenciesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'exchange_rate' => 1.000000, // Base currency
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'exchange_rate' => 0.850000, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'GBP',
                'name' => 'British Pound Sterling',
                'symbol' => '£',
                'exchange_rate' => 0.750000, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'ZAR',
                'name' => 'South African Rand',
                'symbol' => 'R',
                'exchange_rate' => 18.500000, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'CAD',
                'name' => 'Canadian Dollar',
                'symbol' => 'C$',
                'exchange_rate' => 1.350000, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'AUD',
                'name' => 'Australian Dollar',
                'symbol' => 'A$',
                'exchange_rate' => 1.500000, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'JPY',
                'name' => 'Japanese Yen',
                'symbol' => '¥',
                'exchange_rate' => 150.000000, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'CHF',
                'name' => 'Swiss Franc',
                'symbol' => 'CHF',
                'exchange_rate' => 0.900000, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'CNY',
                'name' => 'Chinese Yuan',
                'symbol' => '¥',
                'exchange_rate' => 7.200000, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'INR',
                'name' => 'Indian Rupee',
                'symbol' => '₹',
                'exchange_rate' => 83.000000, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('currencies')->insert($currencies);
    }
}
