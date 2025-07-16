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
                'iso_code' => 'KE',
                'name' => 'Kenya',
                'currency_code' => 'KES',
                'timezone' => 'Africa/Nairobi',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                    'regulatory_framework' => [
                        'tax_year_start' => '01-01',
                        'tax_year_end' => '12-31',
                        'payroll_frequency' => ['monthly'],
                        'required_deductions' => [
                            'paye',
                            'nhif', // National Hospital Insurance Fund
                            'nssf', // National Social Security Fund
                            'housing_levy'
                        ]
                    ]
            ],
            [
                'iso_code' => 'NG',
                'name' => 'Nigeria',
                'currency_code' => 'NGN',
                'timezone' => 'Africa/Lagos',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'CI',
                'name' => 'Ivory Coast',
                'currency_code' => 'XOF',
                'timezone' => 'Africa/Abidjan',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'GH',
                'name' => 'Ghana',
                'currency_code' => 'GHS',
                'timezone' => 'Africa/Accra',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'BW',
                'name' => 'Botswana',
                'currency_code' => 'BWP',
                'timezone' => 'Africa/Gaborone',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'ZM',
                'name' => 'Zambia',
                'currency_code' => 'ZMW',
                'timezone' => 'Africa/Lusaka',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => [
                    'tax_year_start' => '01-01',
                    'tax_year_end' => '12-31',
                    'payroll_frequency' => ['monthly'],
                    'required_deductions' => [
                        'paye',
                        'napsa', // National Pension Scheme Authority
                        'nhima'  // National Health Insurance Management Authority
                    ]
                ]

            ],
            [
                'iso_code' => 'ZW',
                'name' => 'Zimbabwe',
                'currency_code' => 'ZWL',
                'timezone' => 'Africa/Harare',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => [
                    'tax_year_start' => '01-01',
                    'tax_year_end' => '12-31',
                    'payroll_frequency' => ['monthly'],
                    'required_deductions' => [
                        'paye', // Pay As You Earn
                        'nssa', // National Social Security Authority
                        'aids_levy'
                    ]
            ]

            ],
            [
                'iso_code' => 'MU',
                'name' => 'Mauritius',
                'currency_code' => 'MUR',
                'timezone' => 'Indian/Mauritius',
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