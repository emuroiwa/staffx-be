<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Country;
use App\Models\TaxJurisdiction;
use App\Models\StatutoryDeductionTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StatutoryDeductionTemplateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_statutory_deduction_template()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        $templateData = [
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'income_tax',
            'code' => 'PAYE',
            'name' => 'Pay As You Earn',
            'description' => 'South African income tax deduction',
            'calculation_method' => 'progressive_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 237100, 'rate' => 0.18],
                    ['min' => 237101, 'max' => 370500, 'rate' => 0.26],
                    ['min' => 370501, 'max' => 512800, 'rate' => 0.31]
                ],
                'rebates' => [
                    'primary' => 16425,
                    'age_65' => 9000
                ]
            ],
            'minimum_salary' => 0,
            'maximum_salary' => null,
            'employer_rate' => 0,
            'employee_rate' => 0, // Variable based on brackets
            'effective_from' => now(),
            'effective_to' => null,
            'is_mandatory' => true,
            'is_active' => true
        ];

        $template = StatutoryDeductionTemplate::create($templateData);

        $this->assertDatabaseHas('statutory_deduction_templates', [
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'code' => 'PAYE',
            'name' => 'Pay As You Earn',
            'deduction_type' => 'income_tax'
        ]);

        $this->assertEquals('PAYE', $template->code);
        $this->assertEquals('progressive_bracket', $template->calculation_method);
        $this->assertTrue($template->is_mandatory);
        $this->assertIsArray($template->rules);
        $this->assertArrayHasKey('brackets', $template->rules);
    }

    /** @test */
    public function it_belongs_to_a_tax_jurisdiction()
    {
        $country = Country::factory()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        $template = StatutoryDeductionTemplate::factory()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $this->assertEquals($jurisdiction->uuid, $template->jurisdiction->uuid);
        $this->assertEquals($jurisdiction->name, $template->jurisdiction->name);
    }

    /** @test */
    public function it_can_create_south_african_uif_template()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        $uifTemplate = StatutoryDeductionTemplate::create([
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'unemployment_insurance',
            'code' => 'UIF',
            'name' => 'Unemployment Insurance Fund',
            'calculation_method' => 'percentage',
            'rules' => [
                'employee_rate' => 0.01,
                'employer_rate' => 0.01,
                'max_salary' => 17712 // Monthly cap for 2025
            ],
            'employer_rate' => 0.01,
            'employee_rate' => 0.01,
            'maximum_salary' => 17712,
            'effective_from' => now(),
            'is_mandatory' => true
        ]);

        $this->assertEquals('UIF', $uifTemplate->code);
        $this->assertEquals(0.01, $uifTemplate->employee_rate);
        $this->assertEquals(0.01, $uifTemplate->employer_rate);
        $this->assertEquals(17712, $uifTemplate->maximum_salary);
    }

    /** @test */
    public function it_can_create_kenyan_nhif_template()
    {
        $country = Country::factory()->kenya()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        $nhifTemplate = StatutoryDeductionTemplate::create([
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'health_insurance',
            'code' => 'NHIF',
            'name' => 'National Hospital Insurance Fund',
            'calculation_method' => 'salary_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 5999, 'amount' => 150],
                    ['min' => 6000, 'max' => 7999, 'amount' => 300],
                    ['min' => 8000, 'max' => 11999, 'amount' => 400],
                    ['min' => 12000, 'max' => 14999, 'amount' => 500],
                    ['min' => 15000, 'max' => 19999, 'amount' => 600],
                    ['min' => 20000, 'max' => 24999, 'amount' => 750],
                    ['min' => 25000, 'max' => 29999, 'amount' => 850],
                    ['min' => 30000, 'max' => 34999, 'amount' => 900],
                    ['min' => 35000, 'max' => 39999, 'amount' => 950],
                    ['min' => 40000, 'max' => 44999, 'amount' => 1000],
                    ['min' => 45000, 'max' => 49999, 'amount' => 1100],
                    ['min' => 50000, 'max' => 59999, 'amount' => 1200],
                    ['min' => 60000, 'max' => 69999, 'amount' => 1300],
                    ['min' => 70000, 'max' => 79999, 'amount' => 1400],
                    ['min' => 80000, 'max' => 89999, 'amount' => 1500],
                    ['min' => 90000, 'max' => 99999, 'amount' => 1600],
                    ['min' => 100000, 'max' => null, 'amount' => 1700]
                ]
            ],
            'employee_rate' => 0,
            'employer_rate' => 0,
            'effective_from' => now(),
            'is_mandatory' => true
        ]);

        $this->assertEquals('NHIF', $nhifTemplate->code);
        $this->assertEquals('salary_bracket', $nhifTemplate->calculation_method);
        $this->assertArrayHasKey('brackets', $nhifTemplate->rules);
        $this->assertCount(17, $nhifTemplate->rules['brackets']);
    }

    /** @test */
    public function it_can_calculate_percentage_based_deduction()
    {
        $template = StatutoryDeductionTemplate::factory()->create([
            'calculation_method' => 'percentage',
            'employee_rate' => 0.01,
            'maximum_salary' => 20000
        ]);

        // Test with salary below cap
        $result = $template->calculateDeduction(15000);
        $this->assertEquals(150, $result['employee_amount']); // 15000 * 0.01

        // Test with salary above cap
        $result = $template->calculateDeduction(25000);
        $this->assertEquals(200, $result['employee_amount']); // 20000 * 0.01 (capped)
    }

    /** @test */
    public function it_can_calculate_progressive_bracket_deduction()
    {
        $template = StatutoryDeductionTemplate::factory()->create([
            'calculation_method' => 'progressive_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 10000, 'rate' => 0.10],
                    ['min' => 10000, 'max' => 20000, 'rate' => 0.20],
                    ['min' => 20000, 'max' => null, 'rate' => 0.30]
                ]
            ]
        ]);

        // Test with salary in first bracket
        $result = $template->calculateDeduction(8000);
        $this->assertEquals(800, $result['employee_amount']); // 8000 * 0.10

        // Test with salary spanning multiple brackets
        $result = $template->calculateDeduction(25000);
        // Expected: (10000 * 0.10) + (10000 * 0.20) + (5000 * 0.30) = 1000 + 2000 + 1500 = 4500
        $this->assertEquals(4500, $result['employee_amount']);
    }

    /** @test */
    public function it_can_calculate_salary_bracket_deduction()
    {
        $template = StatutoryDeductionTemplate::factory()->create([
            'calculation_method' => 'salary_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 5999, 'amount' => 150],
                    ['min' => 6000, 'max' => 9999, 'amount' => 300],
                    ['min' => 10000, 'max' => null, 'amount' => 500]
                ]
            ]
        ]);

        // Test different salary ranges
        $this->assertEquals(150, $template->calculateDeduction(3000)['employee_amount']);
        $this->assertEquals(300, $template->calculateDeduction(7500)['employee_amount']);
        $this->assertEquals(500, $template->calculateDeduction(15000)['employee_amount']);
    }

    /** @test */
    public function it_can_calculate_flat_amount_deduction()
    {
        $template = StatutoryDeductionTemplate::factory()->create([
            'calculation_method' => 'flat_amount',
            'rules' => ['amount' => 500]
        ]);

        $result = $template->calculateDeduction(10000);
        $this->assertEquals(500, $result['employee_amount']);

        $result = $template->calculateDeduction(50000);
        $this->assertEquals(500, $result['employee_amount']);
    }

    /** @test */
    public function it_handles_employer_contributions()
    {
        $template = StatutoryDeductionTemplate::factory()->create([
            'calculation_method' => 'percentage',
            'employee_rate' => 0.08,
            'employer_rate' => 0.10,
            'deduction_type' => 'pension'
        ]);

        $result = $template->calculateDeduction(10000);
        
        $this->assertEquals(800, $result['employee_amount']); // 10000 * 0.08
        $this->assertEquals(1000, $result['employer_amount']); // 10000 * 0.10
    }

    /** @test */
    public function it_validates_effective_periods()
    {
        $jurisdiction = TaxJurisdiction::factory()->create();
        
        // Current template
        $currentTemplate = StatutoryDeductionTemplate::factory()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'code' => 'PAYE',
            'effective_from' => now()->subMonth(),
            'effective_to' => null
        ]);

        // Future template
        $futureTemplate = StatutoryDeductionTemplate::factory()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'code' => 'PAYE_NEW',
            'effective_from' => now()->addMonth(),
            'effective_to' => null
        ]);

        $this->assertTrue($currentTemplate->isEffectiveAt(now()));
        $this->assertFalse($futureTemplate->isEffectiveAt(now()));
        $this->assertTrue($futureTemplate->isEffectiveAt(now()->addMonths(2)));
    }

    /** @test */
    public function it_can_scope_by_deduction_type()
    {
        $jurisdiction = TaxJurisdiction::factory()->create();
        
        StatutoryDeductionTemplate::factory()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'income_tax'
        ]);
        
        StatutoryDeductionTemplate::factory()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'social_security'
        ]);
        
        StatutoryDeductionTemplate::factory()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'income_tax'
        ]);

        $incomeTaxTemplates = StatutoryDeductionTemplate::ofType('income_tax')->get();
        $socialSecurityTemplates = StatutoryDeductionTemplate::ofType('social_security')->get();

        $this->assertCount(2, $incomeTaxTemplates);
        $this->assertCount(1, $socialSecurityTemplates);
    }

    /** @test */
    public function it_can_scope_active_templates()
    {
        StatutoryDeductionTemplate::factory()->create(['is_active' => true]);
        StatutoryDeductionTemplate::factory()->create(['is_active' => true]);
        StatutoryDeductionTemplate::factory()->create(['is_active' => false]);

        $activeTemplates = StatutoryDeductionTemplate::active()->get();
        $inactiveTemplates = StatutoryDeductionTemplate::inactive()->get();

        $this->assertCount(2, $activeTemplates);
        $this->assertCount(1, $inactiveTemplates);
    }

    /** @test */
    public function it_auto_generates_uuid_on_creation()
    {
        $jurisdiction = TaxJurisdiction::factory()->create();
        
        $template = StatutoryDeductionTemplate::create([
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'deduction_type' => 'test',
            'code' => 'TEST',
            'name' => 'Test Template',
            'calculation_method' => 'flat_amount',
            'rules' => ['amount' => 100],
            'effective_from' => now()
        ]);

        $this->assertNotEmpty($template->uuid);
        $this->assertIsString($template->uuid);
        $this->assertEquals(36, strlen($template->uuid));
    }

    /** @test */
    public function it_annualizes_salary_correctly_for_different_pay_frequencies()
    {
        $template = StatutoryDeductionTemplate::factory()->create([
            'calculation_method' => 'progressive_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 100000, 'rate' => 0.18],
                    ['min' => 100000, 'max' => 200000, 'rate' => 0.26],
                    ['min' => 200000, 'max' => null, 'rate' => 0.31]
                ],
                'rebates' => ['primary' => 16425]
            ]
        ]);

        // Test different pay frequencies with equivalent annual salaries
        $testCases = [
            ['salary' => 20000, 'frequency' => 'monthly', 'expected_annual' => 240000],
            ['salary' => 4615.384615384615, 'frequency' => 'weekly', 'expected_annual' => 240000], // 240000/52
            ['salary' => 9230.769230769231, 'frequency' => 'bi_weekly', 'expected_annual' => 240000], // 240000/26
            ['salary' => 60000, 'frequency' => 'quarterly', 'expected_annual' => 240000],
            ['salary' => 240000, 'frequency' => 'annually', 'expected_annual' => 240000],
        ];

        foreach ($testCases as $case) {
            $result = $template->calculateDeduction($case['salary'], $case['frequency']);
            
            $this->assertEquals(
                $case['expected_annual'], 
                $result['calculation_details']['annual_salary_used'],
                "Annual salary should be correct for {$case['frequency']} frequency",
                0.5 // Allow 0.5 unit tolerance for rounding
            );
        }
    }

    /** @test */
    public function it_pro_rates_progressive_bracket_tax_correctly()
    {
        $template = StatutoryDeductionTemplate::factory()->create([
            'calculation_method' => 'progressive_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 100000, 'rate' => 0.18],
                    ['min' => 100000, 'max' => 200000, 'rate' => 0.26],
                    ['min' => 200000, 'max' => null, 'rate' => 0.31]
                ],
                'rebates' => ['primary' => 16425]
            ]
        ]);

        // Test with same annual salary (R240,000) across different frequencies
        $monthlySalary = 20000;
        $weeklySalary = 4615.38;

        $monthlyResult = $template->calculateDeduction($monthlySalary, 'monthly');
        $weeklyResult = $template->calculateDeduction($weeklySalary, 'weekly');

        // Both should calculate the same annual tax
        $this->assertEquals(
            $monthlyResult['calculation_details']['annual_tax_calculated'],
            $weeklyResult['calculation_details']['annual_tax_calculated'],
            'Annual tax should be the same regardless of pay frequency',
            0.01
        );

        // Pro-rating should be mathematically correct
        $expectedWeeklyFromMonthly = $monthlyResult['employee_amount'] / (52/12);
        $this->assertEquals(
            $expectedWeeklyFromMonthly,
            $weeklyResult['employee_amount'],
            'Weekly tax should be correctly pro-rated from monthly',
            0.01
        );
    }

    /** @test */
    public function it_includes_pay_frequency_details_in_progressive_calculation()
    {
        $template = StatutoryDeductionTemplate::factory()->create([
            'calculation_method' => 'progressive_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 100000, 'rate' => 0.18],
                    ['min' => 100000, 'max' => null, 'rate' => 0.26]
                ],
                'rebates' => ['primary' => 16425]
            ]
        ]);

        $result = $template->calculateDeduction(5000, 'weekly');

        $this->assertArrayHasKey('calculation_details', $result);
        $this->assertArrayHasKey('pay_frequency', $result['calculation_details']);
        $this->assertArrayHasKey('annual_salary_used', $result['calculation_details']);
        $this->assertArrayHasKey('annual_tax_calculated', $result['calculation_details']);
        $this->assertArrayHasKey('salary_used', $result['calculation_details']);

        $this->assertEquals('weekly', $result['calculation_details']['pay_frequency']);
        $this->assertEquals(5000, $result['calculation_details']['salary_used']);
        $this->assertEquals(260000, $result['calculation_details']['annual_salary_used']); // 5000 * 52
    }

    /** @test */
    public function it_handles_unknown_pay_frequency_with_default()
    {
        $template = StatutoryDeductionTemplate::factory()->create([
            'calculation_method' => 'progressive_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 100000, 'rate' => 0.18]
                ],
                'rebates' => ['primary' => 16425]
            ]
        ]);

        // Test with unknown frequency - should default to monthly
        $result = $template->calculateDeduction(25000, 'unknown_frequency');

        $this->assertArrayHasKey('calculation_details', $result);
        $this->assertEquals('unknown_frequency', $result['calculation_details']['pay_frequency']);
        $this->assertEquals(300000, $result['calculation_details']['annual_salary_used']); // 25000 * 12 (monthly default)
    }

    /** @test */
    public function it_maintains_backwards_compatibility_without_pay_frequency()
    {
        $template = StatutoryDeductionTemplate::factory()->create([
            'calculation_method' => 'progressive_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 100000, 'rate' => 0.18]
                ],
                'rebates' => ['primary' => 16425]
            ]
        ]);

        // Test without pay frequency parameter - should default to monthly
        $result = $template->calculateDeduction(25000);

        $this->assertArrayHasKey('calculation_details', $result);
        $this->assertEquals('monthly', $result['calculation_details']['pay_frequency']);
        $this->assertEquals(300000, $result['calculation_details']['annual_salary_used']); // 25000 * 12
    }

    /** @test */
    public function it_calculates_complex_progressive_bracket_with_rebates_correctly()
    {
        // South African PAYE 2025 tax brackets
        $template = StatutoryDeductionTemplate::factory()->create([
            'calculation_method' => 'progressive_bracket',
            'rules' => [
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
                    'primary' => 17235, // 2025 primary rebate
                    'secondary' => 9444, // 65+ rebate
                    'tertiary' => 3145 // 75+ rebate
                ]
            ]
        ]);

        // Test monthly salary of R30,000 (R360,000 annually)
        $result = $template->calculateDeduction(30000, 'monthly');

        // Manual calculation for verification:
        // Annual salary: R360,000
        // Tax brackets:
        // R0 - R237,100: R237,100 * 18% = R42,678
        // R237,101 - R360,000: R122,899 * 26% = R31,954
        // Total tax before rebates: R74,632
        // Less primary rebate: R74,632 - R17,235 = R57,397
        // Monthly tax: R57,397 / 12 = R4,783.08

        $this->assertEquals(360000, $result['calculation_details']['annual_salary_used']);
        $this->assertEquals(57397, $result['calculation_details']['annual_tax_calculated'], '', 2);
        $this->assertEquals(4783.08, $result['employee_amount'], '', 0.2);
    }

    /** @test */
    public function it_applies_only_primary_rebate_correctly()
    {
        $template = StatutoryDeductionTemplate::factory()->create([
            'calculation_method' => 'progressive_bracket',
            'rules' => [
                'brackets' => [
                    ['min' => 0, 'max' => 100000, 'rate' => 0.20]
                ],
                'rebates' => [
                    'primary' => 10000,
                    'secondary' => 5000, // Should not be applied
                    'tertiary' => 2000   // Should not be applied
                ]
            ]
        ]);

        $result = $template->calculateDeduction(5000, 'monthly'); // R60,000 annually

        // Annual tax: R60,000 * 20% = R12,000
        // Less primary rebate only: R12,000 - R10,000 = R2,000
        // Monthly: R2,000 / 12 = R166.67

        $this->assertEquals(60000, $result['calculation_details']['annual_salary_used']);
        $this->assertEquals(2000, $result['calculation_details']['annual_tax_calculated']);
        $this->assertEquals(166.67, $result['employee_amount'], '', 0.01);

        // Check that only primary rebate was applied
        $bracketCalculations = $result['calculation_details']['bracket_calculations'];
        $rebateCalculations = array_filter($bracketCalculations, function($calc) {
            return isset($calc['type']) && $calc['type'] === 'rebate';
        });
        
        $this->assertCount(1, $rebateCalculations, 'Only primary rebate should be applied');
        
        // Get the rebate calculation (array_filter preserves keys, so we need to get first value)
        $rebateCalc = array_values($rebateCalculations)[0];
        $this->assertEquals('primary', $rebateCalc['rebate_type']);
        $this->assertEquals(-10000, $rebateCalc['amount']);
    }
}