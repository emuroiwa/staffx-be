<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\Payroll\StatutoryDeductionCalculator;
use App\Models\Employee;
use App\Models\Company;
use App\Models\Country;
use App\Models\TaxJurisdiction;
use App\Models\StatutoryDeductionTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class StatutoryDeductionCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private StatutoryDeductionCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new StatutoryDeductionCalculator();
    }

    /** @test */
    public function it_can_calculate_all_statutory_deductions_for_south_african_employee()
    {
        // Create South African setup
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        // Create PAYE template
        $payeTemplate = StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);
        
        // Create UIF template
        $uifTemplate = StatutoryDeductionTemplate::factory()->southAfricanUIF()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        // Create employee with company in South Africa
        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create([
            'company_uuid' => $company->uuid,
            'salary' => 25000
        ]);

        $result = $this->calculator->calculateForEmployee($employee, 30000);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_employee_deductions', $result);
        $this->assertArrayHasKey('total_employer_contributions', $result);
        $this->assertArrayHasKey('deductions', $result);
        $this->assertArrayHasKey('errors', $result);
        
        $this->assertCount(2, $result['deductions']);
        $this->assertEmpty($result['errors']);
        $this->assertGreaterThan(0, $result['total_employee_deductions']);
    }

    /** @test */
    public function it_calculates_paye_for_south_african_employee()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create([
            'company_uuid' => $company->uuid,
            'salary' => 50000
        ]);

        $result = $this->calculator->calculatePAYE($employee, 300000); // Higher salary to trigger PAYE

        $this->assertNotNull($result);
        $this->assertEquals('PAYE', $result['code']);
        $this->assertEquals('income_tax', $result['type']);
        $this->assertGreaterThan(0, $result['employee_amount']);
    }

    /** @test */
    public function it_calculates_uif_for_south_african_employee()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        StatutoryDeductionTemplate::factory()->southAfricanUIF()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create([
            'company_uuid' => $company->uuid,
            'salary' => 15000
        ]);

        $result = $this->calculator->calculateUIF($employee, 18000);

        $this->assertNotNull($result);
        $this->assertEquals('UIF', $result['code']);
        $this->assertEquals('unemployment_insurance', $result['type']);
        $this->assertEquals(177.12, $result['employee_amount']); // 17712 * 0.01 (capped at 17712)
        $this->assertEquals(177.12, $result['employer_amount']); // 17712 * 0.01 (capped)
    }

    /** @test */
    public function it_calculates_nhif_for_kenyan_employee()
    {
        $country = Country::factory()->kenya()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        StatutoryDeductionTemplate::factory()->kenyanNHIF()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create([
            'company_uuid' => $company->uuid,
            'salary' => 25000
        ]);

        $result = $this->calculator->calculateHealthInsurance($employee, 30000);

        $this->assertNotNull($result);
        $this->assertEquals('NHIF', $result['code']);
        $this->assertEquals('health_insurance', $result['type']);
        $this->assertEquals(900, $result['employee_amount']); // Bracket for 30000-34999
    }

    /** @test */
    public function it_returns_empty_result_for_unsupported_country()
    {
        $country = Country::factory()->create([
            'iso_code' => 'XX',
            'is_supported_for_payroll' => false
        ]);
        
        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create(['company_uuid' => $company->uuid]);

        $result = $this->calculator->calculateForEmployee($employee, 50000);

        $this->assertEquals(0, $result['total_employee_deductions']);
        $this->assertEquals(0, $result['total_employer_contributions']);
        $this->assertEmpty($result['deductions']);
        $this->assertContains('Country not supported for payroll processing', $result['errors']);
    }

    /** @test */
    public function it_returns_empty_result_when_no_tax_jurisdiction_exists()
    {
        $country = Country::factory()->southAfrica()->create();
        // No tax jurisdiction created
        
        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create(['company_uuid' => $company->uuid]);

        $result = $this->calculator->calculateForEmployee($employee, 50000);

        $this->assertEquals(0, $result['total_employee_deductions']);
        $this->assertEquals(0, $result['total_employer_contributions']);
        $this->assertEmpty($result['deductions']);
        $this->assertContains('No active tax jurisdiction found for country', $result['errors']);
    }

    /** @test */
    public function it_handles_calculation_errors_gracefully()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        // Create a template with invalid rules that will cause calculation errors
        StatutoryDeductionTemplate::factory()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'code' => 'INVALID',
            'name' => 'Invalid Template',
            'deduction_type' => 'test',
            'calculation_method' => 'progressive_bracket',
            'rules' => ['brackets' => 'invalid_data'], // Invalid brackets
            'is_mandatory' => true
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create(['company_uuid' => $company->uuid]);

        $result = $this->calculator->calculateForEmployee($employee, 50000);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Failed to calculate Invalid Template', $result['errors'][0]);
    }

    /** @test */
    public function it_validates_country_configuration()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        // Create only PAYE template, missing UIF and other mandatory deductions
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $validation = $this->calculator->validateCountryConfiguration($country);

        $this->assertFalse($validation['valid']);
        $this->assertContains('PAYE', $validation['configured_deductions']);
        $this->assertContains('UIF', $validation['missing_deductions']);
        $this->assertNotEmpty($validation['warnings']);
    }

    /** @test */
    public function it_validates_complete_country_configuration()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        // Create all mandatory templates
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);
        
        StatutoryDeductionTemplate::factory()->southAfricanUIF()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);
        
        StatutoryDeductionTemplate::factory()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'code' => 'Skills Development Levy',
            'deduction_type' => 'skills_levy',
            'is_mandatory' => true
        ]);

        $validation = $this->calculator->validateCountryConfiguration($country);

        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['missing_deductions']);
        $this->assertEmpty($validation['warnings']);
    }

    /** @test */
    public function it_provides_preview_calculations_for_country()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);
        
        StatutoryDeductionTemplate::factory()->southAfricanUIF()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $result = $this->calculator->previewCalculations(
            'ZA', 
            50000, 
            ['basic_salary' => 45000]
        );

        $this->assertArrayHasKey('total_employee_deductions', $result);
        $this->assertArrayHasKey('deductions', $result);
        $this->assertGreaterThan(0, count($result['deductions']));
        $this->assertGreaterThan(0, $result['total_employee_deductions']);
    }

    /** @test */
    public function it_returns_error_for_invalid_country_in_preview()
    {
        $result = $this->calculator->previewCalculations('XX', 50000);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Country not found', $result['error']);
        $this->assertEmpty($result['deductions']);
    }

    /** @test */
    public function it_returns_null_for_non_existent_deduction_type()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create(['company_uuid' => $company->uuid]);

        $result = $this->calculator->calculateByType($employee, 'non_existent_type', 50000);

        $this->assertNull($result);
    }

    /** @test */
    public function it_calculates_with_effective_dates()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        // Create template effective from next month
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid,
            'effective_from' => now()->addMonth(),
            'effective_to' => null
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create(['company_uuid' => $company->uuid]);

        // Calculate for current date (should return no deductions)
        $result = $this->calculator->calculateForEmployee($employee, 50000, now());
        $this->assertEmpty($result['deductions']);

        // Calculate for next month (should include the deduction)
        $result = $this->calculator->calculateForEmployee($employee, 50000, now()->addMonth());
        $this->assertNotEmpty($result['deductions']);
    }

    /** @test */
    public function it_returns_year_to_date_structure()
    {
        $employee = Employee::factory()->create();
        
        $result = $this->calculator->calculateYearToDate($employee, 2025);

        $this->assertEquals(2025, $result['year']);
        $this->assertEquals($employee->uuid, $result['employee_uuid']);
        $this->assertArrayHasKey('ytd_totals', $result);
        $this->assertArrayHasKey('deduction_breakdown', $result);
        $this->assertArrayHasKey('income_tax', $result['deduction_breakdown']);
        $this->assertArrayHasKey('unemployment_insurance', $result['deduction_breakdown']);
    }

    /** @test */
    public function it_uses_current_year_when_no_year_specified()
    {
        $employee = Employee::factory()->create();
        
        $result = $this->calculator->calculateYearToDate($employee);

        $this->assertEquals(now()->year, $result['year']);
    }
}