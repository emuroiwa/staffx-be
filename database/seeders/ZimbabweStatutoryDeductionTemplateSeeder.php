<?php

namespace Database\Seeders;

use App\Models\StatutoryDeductionTemplate;
use App\Models\TaxJurisdiction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZimbabweStatutoryDeductionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $jurisdiction = TaxJurisdiction::where('name', 'Zimbabwe - National')->first();

        if (!$jurisdiction) {
            $this->command->error('Zimbabwe - National tax jurisdiction not found. Please run TaxJurisdictionSeeder first.');
            return;
        }

        // 1. PAYE
        StatutoryDeductionTemplate::create([
            'uuid' => (string) Str::uuid(),
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'income_tax',
            'code' => 'PAYE',
            'name' => 'Pay As You Earn (PAYE)',
            'description' => 'Zimbabwe PAYE based on progressive tax brackets',
            'calculation_method' => 'progressive_bracket',
            'rules' => [
                'tax_year' => '2024',
                'brackets' => [
                    ['min' => 0, 'max' => 360000, 'rate' => 0.00],
                    ['min' => 360001, 'max' => 1440000, 'rate' => 0.20],
                    ['min' => 1440001, 'max' => 3000000, 'rate' => 0.25],
                    ['min' => 3000001, 'max' => 6000000, 'rate' => 0.30],
                    ['min' => 6000001, 'max' => null, 'rate' => 0.35],
                ]
            ],
            'minimum_salary' => 0,
            'maximum_salary' => null,
            'employer_rate' => 0.0000,
            'employee_rate' => 0.0000,
            'effective_from' => '2024-01-01',
            'effective_to' => '2024-12-31',
            'is_mandatory' => true,
            'is_active' => true,
        ]);

        // 2. NSSA
        StatutoryDeductionTemplate::create([
            'uuid' => (string) Str::uuid(),
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'social_security',
            'code' => 'NSSA',
            'name' => 'NSSA Pension',
            'description' => 'National Social Security Authority pension contribution',
            'calculation_method' => 'percentage',
            'rules' => [
                'calculation_base' => 'gross_salary',
                'max_contribution' => 1335.00, // Adjust based on NSSA limits
            ],
            'minimum_salary' => 0,
            'maximum_salary' => null,
            'employer_rate' => 0.045,
            'employee_rate' => 0.045,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'is_mandatory' => true,
            'is_active' => true,
        ]);

        // 3. AIDS Levy
        StatutoryDeductionTemplate::create([
            'uuid' => (string) Str::uuid(),
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'aids_levy',
            'code' => 'AIDS',
            'name' => 'AIDS Levy',
            'description' => '3% AIDS Levy on calculated PAYE',
            'calculation_method' => 'percentage',
            'rules' => [
                'calculation_base' => 'paye',
            ],
            'minimum_salary' => 0,
            'maximum_salary' => null,
            'employer_rate' => 0.000,
            'employee_rate' => 0.030,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'is_mandatory' => true,
            'is_active' => true,
        ]);

        $this->command->info('Zimbabwe statutory deduction templates seeded successfully.');
    }
}
