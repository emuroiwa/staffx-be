<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
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
        // Get currency UUIDs for linking
        $currencies = Currency::whereIn('code', [
            'USD', 'ZAR', 'GBP', 'KES', 'NGN', 'XOF', 'GHS', 'BWP', 'ZMW', 'ZWL', 'MUR'
        ])->get()->keyBy('code');

        $countries = [
            [
                'iso_code' => 'ZA',
                'name' => 'South Africa',
                'currency_code' => 'ZAR',
                'currency_uuid' => $currencies['ZAR']->uuid ?? null,
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
                'currency_uuid' => $currencies['USD']->uuid ?? null,
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
                'currency_uuid' => $currencies['GBP']->uuid ?? null,
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
                'currency_uuid' => $currencies['KES']->uuid ?? null,
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
                'currency_uuid' => $currencies['NGN']->uuid ?? null,
                'timezone' => 'Africa/Lagos',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'CI',
                'name' => 'Ivory Coast',
                'currency_code' => 'XOF',
                'currency_uuid' => $currencies['XOF']->uuid ?? null,
                'timezone' => 'Africa/Abidjan',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'GH',
                'name' => 'Ghana',
                'currency_code' => 'GHS',
                'currency_uuid' => $currencies['GHS']->uuid ?? null,
                'timezone' => 'Africa/Accra',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'BW',
                'name' => 'Botswana',
                'currency_code' => 'BWP',
                'currency_uuid' => $currencies['BWP']->uuid ?? null,
                'timezone' => 'Africa/Gaborone',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],
            [
                'iso_code' => 'ZM',
                'name' => 'Zambia',
                'currency_code' => 'ZMW',
                'currency_uuid' => $currencies['ZMW']->uuid ?? null,
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
                'currency_uuid' => $currencies['ZWL']->uuid ?? null,
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
                'currency_uuid' => $currencies['MUR']->uuid ?? null,
                'timezone' => 'Indian/Mauritius',
                'is_supported_for_payroll' => false,
                'is_active' => true,
                'regulatory_framework' => []
            ],

        ];

        foreach ($countries as $countryData) {
            $country = Country::where('iso_code', $countryData['iso_code'])->first();
            
            if ($country) {
                // Update existing country with currency link
                $country->update([
                    'currency_uuid' => $countryData['currency_uuid'],
                    'currency_code' => $countryData['currency_code'],
                    'is_supported_for_payroll' => $countryData['is_supported_for_payroll'],
                    'regulatory_framework' => $countryData['regulatory_framework'],
                ]);
            } else {
                // Create new country
                Country::create([
                    'uuid' => Str::uuid(),
                    'iso_code' => $countryData['iso_code'],
                    'name' => $countryData['name'],
                    'currency_code' => $countryData['currency_code'],
                    'currency_uuid' => $countryData['currency_uuid'],
                    'timezone' => $countryData['timezone'],
                    'is_supported_for_payroll' => $countryData['is_supported_for_payroll'],
                    'is_active' => $countryData['is_active'],
                    'regulatory_framework' => $countryData['regulatory_framework'],
                ]);
            }
        }
    }
}