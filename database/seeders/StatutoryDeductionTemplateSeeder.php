<?php

namespace Database\Seeders;

use App\Models\StatutoryDeductionTemplate;
use App\Models\TaxJurisdiction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StatutoryDeductionTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get South African tax jurisdiction
        $southAfricaNational = TaxJurisdiction::where('name', 'South Africa - National')->first();
        
        if (!$southAfricaNational) {
            $this->command->error('South Africa - National tax jurisdiction not found. Please run TaxJurisdictionSeeder first.');
            return;
        }

        $this->createSouthAfricanStatutoryDeductions($southAfricaNational);
    }

    /**
     * Create South African statutory deduction templates
     */
    private function createSouthAfricanStatutoryDeductions(TaxJurisdiction $jurisdiction): void
    {
        // 1. PAYE (Pay As You Earn) - Income Tax
        StatutoryDeductionTemplate::create([
            'uuid' => (string) Str::uuid(),
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'income_tax',
            'code' => 'PAYE',
            'name' => 'Pay As You Earn (PAYE)',
            'description' => 'Income tax deducted from employee salaries based on South African tax tables',
            'calculation_method' => 'progressive_bracket',
            'rules' => [
                'tax_year' => '2024/2025',
                'brackets' => [
                    ['min' => 0, 'max' => 237100, 'rate' => 0.18],
                    ['min' => 237101, 'max' => 370500, 'rate' => 0.26],
                    ['min' => 370501, 'max' => 512800, 'rate' => 0.31],
                    ['min' => 512801, 'max' => 673000, 'rate' => 0.36],
                    ['min' => 673001, 'max' => 857900, 'rate' => 0.39],
                    ['min' => 857901, 'max' => 1817000, 'rate' => 0.41],
                    ['min' => 1817001, 'max' => null, 'rate' => 0.45]
                ],
                'rebates' => [
                    'primary' => 17235,
                    'secondary' => 9444, // For taxpayers 65 and older
                    'tertiary' => 3145   // For taxpayers 75 and older
                ],
                'medical_aid_credits' => [
                    'main_member' => 347,
                    'first_dependant' => 347,
                    'additional_dependants' => 234
                ],
                'threshold' => 95750 // Tax threshold for 2024/2025
            ],
            'minimum_salary' => 0,
            'maximum_salary' => null,
            'employer_rate' => 0.0000,
            'employee_rate' => 0.0000, // Variable rate based on brackets
            'effective_from' => '2024-03-01',
            'effective_to' => '2025-02-28',
            'is_mandatory' => true,
            'is_active' => true
        ]);

        // 2. UIF (Unemployment Insurance Fund)
        StatutoryDeductionTemplate::create([
            'uuid' => (string) Str::uuid(),
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'unemployment_insurance',
            'code' => 'UIF',
            'name' => 'Unemployment Insurance Fund (UIF)',
            'description' => 'Unemployment insurance contributions for both employee and employer',
            'calculation_method' => 'percentage',
            'rules' => [
                'max_annual_earnings' => 177624, // R14,802 per month
                'max_annual_contribution' => 1776.24, // R148.02 per month
                'calculation_base' => 'gross_salary'
            ],
            'minimum_salary' => 0,
            'maximum_salary' => 177624,
            'employer_rate' => 0.0100, // 1%
            'employee_rate' => 0.0100, // 1%
            'effective_from' => '2024-03-01',
            'effective_to' => null,
            'is_mandatory' => true,
            'is_active' => true
        ]);

        // 3. Skills Development Levy (SDL)
        StatutoryDeductionTemplate::create([
            'uuid' => (string) Str::uuid(),
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'skills_development',
            'code' => 'SDL',
            'name' => 'Skills Development Levy (SDL)',
            'description' => 'Skills development levy paid by employers with annual payroll exceeding R500,000',
            'calculation_method' => 'percentage',
            'rules' => [
                'payroll_threshold' => 500000, // Only applies if annual payroll > R500,000
                'calculation_base' => 'gross_salary',
                'employer_only' => true
            ],
            'minimum_salary' => 0,
            'maximum_salary' => null,
            'employer_rate' => 0.0100, // 1%
            'employee_rate' => 0.0000, // Employer only
            'effective_from' => '2024-03-01',
            'effective_to' => null,
            'is_mandatory' => true,
            'is_active' => true
        ]);

        // 4. Additional UIF for High Earners (Optional - for completeness)
        StatutoryDeductionTemplate::create([
            'uuid' => (string) Str::uuid(),
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'unemployment_insurance',
            'code' => 'UIF_HIGH',
            'name' => 'UIF (High Earners)',
            'description' => 'UIF calculation for employees earning above the UIF cap',
            'calculation_method' => 'flat_amount',
            'rules' => [
                'fixed_amount' => 148.02, // Monthly cap
                'applies_when' => 'monthly_salary > 14802'
            ],
            'minimum_salary' => 14802,
            'maximum_salary' => null,
            'employer_rate' => 0.0000,
            'employee_rate' => 0.0000,
            'effective_from' => '2024-03-01',
            'effective_to' => null,
            'is_mandatory' => true,
            'is_active' => false // Disabled by default as it's handled by the main UIF template
        ]);

        $this->command->info('South African statutory deduction templates created successfully.');
    }
}