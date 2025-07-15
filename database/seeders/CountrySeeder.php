<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            [
                'iso_code' => 'US',
                'name' => 'United States',
                'currency_code' => 'USD',
                'timezone' => 'America/New_York',
                'is_supported_for_payroll' => true,
                'is_active' => true,
                'regulatory_framework' => [
                    'tax_year_start' => '01-01',
                    'tax_year_end' => '12-31',
                    'payroll_frequency' => ['weekly', 'bi_weekly', 'monthly', 'semi_monthly'],
                    'required_deductions' => ['federal_tax', 'state_tax', 'social_security', 'medicare'],
                ]
            ],
            [
                'iso_code' => 'CA',
                'name' => 'Canada',
                'currency_code' => 'CAD',
                'timezone' => 'America/Toronto',
                'is_supported_for_payroll' => true,
                'is_active' => true,
                'regulatory_framework' => [
                    'tax_year_start' => '01-01',
                    'tax_year_end' => '12-31',
                    'payroll_frequency' => ['weekly', 'bi_weekly', 'monthly', 'semi_monthly'],
                    'required_deductions' => ['federal_tax', 'provincial_tax', 'cpp', 'ei'],
                ]
            ],
            [
                'iso_code' => 'GB',
                'name' => 'United Kingdom',
                'currency_code' => 'GBP',
                'timezone' => 'Europe/London',
                'is_supported_for_payroll' => true,
                'is_active' => true,
                'regulatory_framework' => [
                    'tax_year_start' => '04-06',
                    'tax_year_end' => '04-05',
                    'payroll_frequency' => ['weekly', 'monthly'],
                    'required_deductions' => ['income_tax', 'national_insurance'],
                ]
            ],
            [
                'iso_code' => 'AU',
                'name' => 'Australia',
                'currency_code' => 'AUD',
                'timezone' => 'Australia/Sydney',
                'is_supported_for_payroll' => true,
                'is_active' => true,
                'regulatory_framework' => [
                    'tax_year_start' => '07-01',
                    'tax_year_end' => '06-30',
                    'payroll_frequency' => ['weekly', 'fortnightly', 'monthly'],
                    'required_deductions' => ['income_tax', 'superannuation'],
                ]
            ],
            [
                'iso_code' => 'ZA',
                'name' => 'South Africa',
                'currency_code' => 'ZAR',
                'timezone' => 'Africa/Johannesburg',
                'is_supported_for_payroll' => true,
                'is_active' => true,
                'regulatory_framework' => [
                    'tax_year_start' => '03-01',
                    'tax_year_end' => '02-28',
                    'payroll_frequency' => ['weekly', 'monthly'],
                    'required_deductions' => ['paye', 'uif', 'sdl'],
                ]
            ],
            [
                'iso_code' => 'DE',
                'name' => 'Germany',
                'currency_code' => 'EUR',
                'timezone' => 'Europe/Berlin',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'FR',
                'name' => 'France',
                'currency_code' => 'EUR',
                'timezone' => 'Europe/Paris',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'BR',
                'name' => 'Brazil',
                'currency_code' => 'BRL',
                'timezone' => 'America/Sao_Paulo',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'IN',
                'name' => 'India',
                'currency_code' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'JP',
                'name' => 'Japan',
                'currency_code' => 'JPY',
                'timezone' => 'Asia/Tokyo',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'SG',
                'name' => 'Singapore',
                'currency_code' => 'SGD',
                'timezone' => 'Asia/Singapore',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'NL',
                'name' => 'Netherlands',
                'currency_code' => 'EUR',
                'timezone' => 'Europe/Amsterdam',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
        ];

        foreach ($countries as $countryData) {
            Country::create([
                'uuid' => Str::uuid(),
                'iso_code' => $countryData['iso_code'],
                'name' => $countryData['name'],
                'currency_code' => $countryData['currency_code'],
                'timezone' => $countryData['timezone'],
                'is_supported_for_payroll' => $countryData['is_supported_for_payroll'],
                'is_active' => $countryData['is_active'],
                'regulatory_framework' => $countryData['regulatory_framework'],
            ]);
        }
    }
}