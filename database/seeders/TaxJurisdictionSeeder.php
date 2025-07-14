<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\TaxJurisdiction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TaxJurisdictionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or get South Africa country
        $southAfrica = Country::where('iso_code', 'ZA')->first();
        
        if (!$southAfrica) {
            $southAfrica = Country::factory()->southAfrica()->create();
        }

        // Create South African tax jurisdictions
        $this->createSouthAfricanJurisdictions($southAfrica);
    }

    /**
     * Create South African tax jurisdictions
     */
    private function createSouthAfricanJurisdictions(Country $country): void
    {
        // National jurisdiction for South Africa
        TaxJurisdiction::create([
            'uuid' => (string) Str::uuid(),
            'country_uuid' => $country->uuid,
            'region_code' => 'ZA-NAT',
            'name' => 'South Africa - National',
            'tax_year_start' => '2024-03-01',
            'tax_year_end' => '2025-02-28',
            'regulatory_authority' => 'South African Revenue Service (SARS)',
            'effective_from' => '2024-03-01 00:00:00',
            'effective_to' => null,
            'is_active' => true,
            'settings' => [
                'tax_tables' => [
                    'individual' => [
                        'tax_year' => '2024/2025',
                        'brackets' => [
                            ['min' => 0, 'max' => 237100, 'rate' => 0.18, 'rebate' => 17235],
                            ['min' => 237101, 'max' => 370500, 'rate' => 0.26, 'rebate' => 17235],
                            ['min' => 370501, 'max' => 512800, 'rate' => 0.31, 'rebate' => 17235],
                            ['min' => 512801, 'max' => 673000, 'rate' => 0.36, 'rebate' => 17235],
                            ['min' => 673001, 'max' => 857900, 'rate' => 0.39, 'rebate' => 17235],
                            ['min' => 857901, 'max' => 1817000, 'rate' => 0.41, 'rebate' => 17235],
                            ['min' => 1817001, 'max' => null, 'rate' => 0.45, 'rebate' => 17235]
                        ]
                    ]
                ],
                'deductions' => [
                    'paye' => [
                        'enabled' => true,
                        'description' => 'Pay As You Earn',
                        'calculation_method' => 'tax_table'
                    ],
                    'uif' => [
                        'enabled' => true,
                        'description' => 'Unemployment Insurance Fund',
                        'rate' => 0.01,
                        'max_annual_contribution' => 1776.24,
                        'employee_rate' => 0.01,
                        'employer_rate' => 0.01
                    ],
                    'skills_development_levy' => [
                        'enabled' => true,
                        'description' => 'Skills Development Levy',
                        'rate' => 0.01,
                        'employer_only' => true,
                        'payroll_threshold' => 500000
                    ]
                ],
                'allowances' => [
                    'medical_aid' => [
                        'enabled' => true,
                        'description' => 'Medical Aid Tax Credit',
                        'credits' => [
                            'main_member' => 347,
                            'first_dependant' => 347,
                            'additional_dependants' => 234
                        ]
                    ],
                    'travel_allowance' => [
                        'enabled' => true,
                        'description' => 'Travel Allowance',
                        'rate_per_km' => 4.02,
                        'max_annual_amount' => 88000
                    ]
                ],
                'compliance' => [
                    'efiling_required' => true,
                    'etax_registration' => true,
                    'uif_registration' => true,
                    'skills_development_levy_registration' => true
                ]
            ]
        ]);

        // Provincial jurisdictions for specific provincial taxes (if any)
        $provinces = [
            ['code' => 'ZA-WC', 'name' => 'Western Cape'],
            ['code' => 'ZA-EC', 'name' => 'Eastern Cape'],
            ['code' => 'ZA-NC', 'name' => 'Northern Cape'],
            ['code' => 'ZA-FS', 'name' => 'Free State'],
            ['code' => 'ZA-KZN', 'name' => 'KwaZulu-Natal'],
            ['code' => 'ZA-NW', 'name' => 'North West'],
            ['code' => 'ZA-GP', 'name' => 'Gauteng'],
            ['code' => 'ZA-MP', 'name' => 'Mpumalanga'],
            ['code' => 'ZA-LP', 'name' => 'Limpopo']
        ];

        foreach ($provinces as $province) {
            TaxJurisdiction::create([
                'uuid' => (string) Str::uuid(),
                'country_uuid' => $country->uuid,
                'region_code' => $province['code'],
                'name' => "South Africa - {$province['name']}",
                'tax_year_start' => '2024-03-01',
                'tax_year_end' => '2025-02-28',
                'regulatory_authority' => 'South African Revenue Service (SARS)',
                'effective_from' => '2024-03-01 00:00:00',
                'effective_to' => null,
                'is_active' => true,
                'settings' => [
                    'provincial_taxes' => [
                        'enabled' => false,
                        'description' => 'No provincial income tax currently applicable'
                    ],
                    'additional_compliance' => [
                        'provincial_registration' => false
                    ]
                ]
            ]);
        }
    }
}