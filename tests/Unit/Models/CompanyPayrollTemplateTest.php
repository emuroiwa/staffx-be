<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Company;
use App\Models\Employee;
use App\Models\CompanyPayrollTemplate;
use App\Models\PayrollItemCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CompanyPayrollTemplateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_company_payroll_template()
    {
        $company = Company::factory()->create();
        $category = PayrollItemCategory::factory()->create();
        
        $templateData = [
            'company_uuid' => $company->uuid,
            'category_uuid' => $category->uuid,
            'code' => 'TRANSPORT',
            'name' => 'Transport Allowance',
            'description' => 'Monthly transport allowance',
            'calculation_method' => 'fixed_amount',
            'default_amount' => 3000.00,
            'is_taxable' => false,
            'is_pensionable' => false,
            'is_active' => true,
            'requires_approval' => false
        ];

        $template = CompanyPayrollTemplate::create($templateData);

        $this->assertDatabaseHas('company_payroll_templates', [
            'company_uuid' => $company->uuid,
            'code' => 'TRANSPORT',
            'name' => 'Transport Allowance',
            'calculation_method' => 'fixed_amount'
        ]);

        $this->assertEquals('TRANSPORT', $template->code);
        $this->assertEquals(3000.00, $template->default_amount);
        $this->assertFalse($template->is_taxable);
        $this->assertFalse($template->is_pensionable);
    }

    /** @test */
    public function it_belongs_to_a_company()
    {
        $company = Company::factory()->create();
        $template = CompanyPayrollTemplate::factory()->create([
            'company_uuid' => $company->uuid
        ]);

        $this->assertEquals($company->uuid, $template->company->uuid);
        $this->assertEquals($company->name, $template->company->name);
    }

    /** @test */
    public function it_belongs_to_a_payroll_item_category()
    {
        $category = PayrollItemCategory::factory()->create();
        $template = CompanyPayrollTemplate::factory()->create([
            'category_uuid' => $category->uuid
        ]);

        $this->assertEquals($category->uuid, $template->category->uuid);
        $this->assertEquals($category->name, $template->category->name);
    }

    /** @test */
    public function it_can_calculate_fixed_amount_payroll_item()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);
        $template = CompanyPayrollTemplate::factory()->fixedAmount()->create([
            'default_amount' => 2500.00
        ]);

        $result = $template->calculateAmount($employee, 55000);
        
        $this->assertEquals(2500.00, $result);
    }

    /** @test */
    public function it_can_calculate_percentage_of_salary_payroll_item()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);
        $template = CompanyPayrollTemplate::factory()->percentageOfSalary()->create([
            'default_percentage' => 10.00
        ]);

        $grossSalary = 55000;
        $result = $template->calculateAmount($employee, $grossSalary);
        
        $this->assertEquals(5500.00, $result); // 55000 * 0.10
    }

    /** @test */
    public function it_can_calculate_percentage_of_basic_salary_payroll_item()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);
        $template = CompanyPayrollTemplate::factory()->percentageOfBasic()->create([
            'default_percentage' => 15.00
        ]);

        $result = $template->calculateAmount($employee, 60000);
        
        $this->assertEquals(7500.00, $result); // 50000 * 0.15 (basic salary)
    }

    /** @test */
    public function it_can_calculate_formula_based_payroll_item()
    {
        $employee = Employee::factory()->create([
            'salary' => 50000,
            'hire_date' => now()->subYears(5)->startOfDay() // Exactly 5 years ago
        ]);
        
        $template = CompanyPayrollTemplate::factory()->formula()->create([
            'formula_expression' => '{basic_salary} * 0.05'
        ]);

        $result = $template->calculateAmount($employee, 60000);
        
        // Expected: (50000 * 0.05) = 2500
        $this->assertEquals(2500.00, $result);
    }

    /** @test */
    public function it_returns_zero_for_manual_calculation_method()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);
        $template = CompanyPayrollTemplate::factory()->manual()->create();

        $result = $template->calculateAmount($employee, 60000);
        
        $this->assertEquals(0, $result); // Manual items require manual input
    }

    /** @test */
    public function it_handles_unsafe_formula_expressions()
    {
        $employee = Employee::factory()->create([
            'salary' => 50000,
            'years_of_service' => 5
        ]);
        
        $template = CompanyPayrollTemplate::factory()->formula()->create([
            'formula_expression' => 'exec("rm -rf /"); {basic_salary} * 0.05'
        ]);

        $result = $template->calculateAmount($employee, 60000);
        
        $this->assertEquals(0, $result); // Should return 0 for unsafe expressions
    }

    /** @test */
    public function it_can_check_employee_eligibility_by_department()
    {
        $employee = Employee::factory()->create([
            'department_uuid' => 'dept-123'
        ]);

        $template = CompanyPayrollTemplate::factory()->create([
            'eligibility_rules' => [
                'departments' => ['dept-123', 'dept-456']
            ]
        ]);

        $this->assertTrue($template->isApplicableToEmployee($employee));

        // Test with non-eligible department
        $template2 = CompanyPayrollTemplate::factory()->create([
            'eligibility_rules' => [
                'departments' => ['dept-999']
            ]
        ]);

        $this->assertFalse($template2->isApplicableToEmployee($employee));
    }

    /** @test */
    public function it_can_check_employee_eligibility_by_position()
    {
        $employee = Employee::factory()->create([
            'position_uuid' => 'pos-123'
        ]);

        $template = CompanyPayrollTemplate::factory()->create([
            'eligibility_rules' => [
                'positions' => ['pos-123', 'pos-456']
            ]
        ]);

        $this->assertTrue($template->isApplicableToEmployee($employee));
    }

    /** @test */
    public function it_can_check_employee_eligibility_by_employment_type()
    {
        $employee = Employee::factory()->create([
            'employment_type' => 'permanent'
        ]);

        $template = CompanyPayrollTemplate::factory()->create([
            'eligibility_rules' => [
                'employment_types' => ['permanent', 'contract']
            ]
        ]);

        $this->assertTrue($template->isApplicableToEmployee($employee));

        // Test with non-eligible employment type
        $employee2 = Employee::factory()->create([
            'employment_type' => 'intern'
        ]);

        $this->assertFalse($template->isApplicableToEmployee($employee2));
    }

    /** @test */
    public function it_can_check_employee_eligibility_by_salary_range()
    {
        $employee = Employee::factory()->create(['salary' => 25000]);

        $template = CompanyPayrollTemplate::factory()->create([
            'eligibility_rules' => [
                'min_salary' => 20000,
                'max_salary' => 50000
            ]
        ]);

        $this->assertTrue($template->isApplicableToEmployee($employee));

        // Test below minimum
        $template2 = CompanyPayrollTemplate::factory()->create([
            'eligibility_rules' => [
                'min_salary' => 30000
            ]
        ]);

        $this->assertFalse($template2->isApplicableToEmployee($employee));
    }

    /** @test */
    public function inactive_templates_are_not_applicable_to_employees()
    {
        $employee = Employee::factory()->create();
        $template = CompanyPayrollTemplate::factory()->inactive()->create();

        $this->assertFalse($template->isApplicableToEmployee($employee));
    }

    /** @test */
    public function it_can_scope_active_templates()
    {
        CompanyPayrollTemplate::factory()->create(['is_active' => true]);
        CompanyPayrollTemplate::factory()->create(['is_active' => true]);
        CompanyPayrollTemplate::factory()->create(['is_active' => false]);

        $activeTemplates = CompanyPayrollTemplate::active()->get();

        $this->assertCount(2, $activeTemplates);
        $this->assertTrue($activeTemplates->every(fn($template) => $template->is_active));
    }

    /** @test */
    public function it_can_scope_by_category()
    {
        $allowanceCategory = PayrollItemCategory::factory()->create(['name' => 'Allowances']);
        $deductionCategory = PayrollItemCategory::factory()->create(['name' => 'Deductions']);

        CompanyPayrollTemplate::factory()->create(['category_uuid' => $allowanceCategory->uuid]);
        CompanyPayrollTemplate::factory()->create(['category_uuid' => $allowanceCategory->uuid]);
        CompanyPayrollTemplate::factory()->create(['category_uuid' => $deductionCategory->uuid]);

        $allowanceTemplates = CompanyPayrollTemplate::byCategory('Allowances')->get();
        $deductionTemplates = CompanyPayrollTemplate::byCategory('Deductions')->get();

        $this->assertCount(2, $allowanceTemplates);
        $this->assertCount(1, $deductionTemplates);
    }

    /** @test */
    public function it_can_scope_for_employee()
    {
        $employee = Employee::factory()->create();
        
        $activeTemplate = CompanyPayrollTemplate::factory()->create(['is_active' => true]);
        $inactiveTemplate = CompanyPayrollTemplate::factory()->create(['is_active' => false]);

        $eligibleTemplates = CompanyPayrollTemplate::forEmployee($employee)->get();

        $this->assertCount(1, $eligibleTemplates);
        $this->assertTrue($eligibleTemplates->contains($activeTemplate));
        $this->assertFalse($eligibleTemplates->contains($inactiveTemplate));
    }

    /** @test */
    public function it_auto_generates_uuid_on_creation()
    {
        $company = Company::factory()->create();
        
        $template = CompanyPayrollTemplate::create([
            'company_uuid' => $company->uuid,
            'code' => 'TEST',
            'name' => 'Test Template',
            'calculation_method' => 'fixed_amount',
            'default_amount' => 1000
        ]);

        $this->assertNotEmpty($template->uuid);
        $this->assertIsString($template->uuid);
        $this->assertEquals(36, strlen($template->uuid));
    }
}