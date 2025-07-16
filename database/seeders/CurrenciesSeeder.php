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
                'code' => 'KES',
                'name' => 'Kenyan Shilling',
                'symbol' => 'KSh',
                'exchange_rate' => 128.50, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'NGN',
                'name' => 'Nigerian Naira',
                'symbol' => '₦',
                'exchange_rate' => 1500.00, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'XOF',
                'name' => 'West African CFA franc',
                'symbol' => 'CFA',
                'exchange_rate' => 605.00, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'BWP',
                'name' => 'Botswana Pula',
                'symbol' => 'P',
                'exchange_rate' => 13.50, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'ZMW',
                'name' => 'Zambian Kwacha',
                'symbol' => 'ZK',
                'exchange_rate' => 25.00, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'ZWL',
                'name' => 'Zimbabwean Dollar',
                'symbol' => 'Z$',
                'exchange_rate' => 10000.00, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'exchange_rate' => 0.85, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'GBP',
                'name' => 'British Pound Sterling',
                'symbol' => '£',
                'exchange_rate' => 0.75, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'GHS',
                'name' => 'Ghanaian Cedi',
                'symbol' => 'GH₵',
                'exchange_rate' => 15.00, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'code' => 'MUR',
                'name' => 'Mauritian Rupee',
                'symbol' => '₨',
                'exchange_rate' => 45.00, // Example rate
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

        ];

        // Store currency UUIDs for later reference
        $currencyUuids = [];
        foreach ($currencies as $currency) {
            $currencyUuids[$currency['code']] = $currency['uuid'];
        }

        DB::table('currencies')->insert($currencies);
    }
}
