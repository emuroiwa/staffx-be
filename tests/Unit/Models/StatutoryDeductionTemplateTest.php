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
                    ['min' => 10001, 'max' => 20000, 'rate' => 0.20],
                    ['min' => 20001, 'max' => null, 'rate' => 0.30]
                ]
            ]
        ]);

        // Test with salary in first bracket
        $result = $template->calculateDeduction(8000);
        $this->assertEquals(800, $result['employee_amount']); // 8000 * 0.10

        // Test with salary spanning multiple brackets
        $result = $template->calculateDeduction(25000);
        $expected = (10000 * 0.10) + (10000 * 0.20) + (5000 * 0.30); // 1000 + 2000 + 1500 = 4500
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
}