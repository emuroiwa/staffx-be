<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\Payroll\PayrollCalculationService;
use App\Services\Payroll\StatutoryDeductionCalculator;
use App\Models\Employee;
use App\Models\Company;
use App\Models\Country;
use App\Models\TaxJurisdiction;
use App\Models\StatutoryDeductionTemplate;
use App\Models\CompanyPayrollTemplate;
use App\Models\EmployeePayrollItem;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PayrollCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCalculationService $service;
    private StatutoryDeductionCalculator $statutoryCalculator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->statutoryCalculator = new StatutoryDeductionCalculator();
        $this->service = new PayrollCalculationService($this->statutoryCalculator);
    }

    /** @test */
    public function it_can_calculate_employee_payroll_with_basic_salary_only()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        // Create PAYE template for statutory deductions
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create([
            'company_uuid' => $company->uuid,
            'salary' => 25000
        ]);

        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $result = $this->service->calculateEmployeePayroll($employee, $periodStart, $periodEnd);

        $this->assertEquals($employee->uuid, $result['employee_uuid']);
        $this->assertEquals(25000, $result['basic_salary']);
        $this->assertEquals(25000, $result['gross_salary']);
        $this->assertEquals(0, $result['total_allowances']);
        $this->assertGreaterThanOrEqual(0, $result['total_deductions']);
        $this->assertLessThanOrEqual($result['gross_salary'], $result['net_salary']);
        $this->assertArrayHasKey('payroll_items', $result);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_includes_company_allowances_in_calculation()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create([
            'company_uuid' => $company->uuid,
            'salary' => 30000
        ]);

        // Create company allowance template
        CompanyPayrollTemplate::factory()->allowance()->create([
            'company_uuid' => $company->uuid,
            'code' => 'TRANSPORT',
            'name' => 'Transport Allowance',
            'calculation_method' => 'fixed_amount',
            'amount' => 2000
        ]);

        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $result = $this->service->calculateEmployeePayroll($employee, $periodStart, $periodEnd);

        $this->assertEquals(2000, $result['total_allowances']);
        $this->assertEquals(32000, $result['gross_salary'] + $result['total_allowances']);
        $this->assertCount(1, $result['payroll_items']['allowances']['company']);
        $this->assertEquals('TRANSPORT', $result['payroll_items']['allowances']['company'][0]['code']);
    }

    /** @test */
    public function it_includes_employee_specific_items_in_calculation()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create([
            'company_uuid' => $company->uuid,
            'salary' => 35000
        ]);

        // Create employee-specific allowance
        EmployeePayrollItem::factory()->allowance()->create([
            'employee_uuid' => $employee->uuid,
            'code' => 'OVERTIME',
            'name' => 'Overtime Pay',
            'calculation_method' => 'fixed_amount',
            'amount' => 1500,
            'effective_from' => now()->startOfMonth()
        ]);

        // Create employee-specific deduction
        EmployeePayrollItem::factory()->deduction()->create([
            'employee_uuid' => $employee->uuid,
            'code' => 'LOAN',
            'name' => 'Staff Loan',
            'calculation_method' => 'fixed_amount',
            'amount' => 500,
            'effective_from' => now()->startOfMonth()
        ]);

        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $result = $this->service->calculateEmployeePayroll($employee, $periodStart, $periodEnd);

        $this->assertEquals(1500, $result['total_allowances']);
        $this->assertGreaterThanOrEqual(500, $result['total_deductions']);
        $this->assertCount(1, $result['payroll_items']['allowances']['employee']);
        $this->assertCount(1, $result['payroll_items']['deductions']['employee']);
    }

    /** @test */
    public function it_calculates_pro_rated_salary_for_partial_month()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create([
            'company_uuid' => $company->uuid,
            'salary' => 30000
        ]);

        // Calculate for 15 days out of 30-day month
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->startOfMonth()->addDays(14); // 15 days (0-14)

        $result = $this->service->calculateEmployeePayroll($employee, $periodStart, $periodEnd);

        $this->assertEquals(30000, $result['basic_salary']);
        $this->assertEquals(15000, $result['gross_salary']); // 30000 * (15/30)
    }

    /** @test */
    public function it_can_calculate_batch_payroll_for_multiple_employees()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        
        $employees = Employee::factory()->count(3)->create([
            'company_uuid' => $company->uuid,
            'salary' => 25000
        ]);

        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $result = $this->service->calculateBatchPayroll($employees, $periodStart, $periodEnd);

        $this->assertEquals(3, $result['summary']['total_employees']);
        $this->assertEquals(3, $result['summary']['successful_calculations']);
        $this->assertEquals(0, $result['summary']['failed_calculations']);
        $this->assertEquals(75000, $result['summary']['total_gross_salary']); // 3 * 25000
        $this->assertCount(3, $result['calculations']);
        $this->assertArrayHasKey('payroll_period_start', $result);
        $this->assertArrayHasKey('calculated_at', $result);
    }

    /** @test */
    public function it_handles_calculation_errors_in_batch_processing()
    {
        $country = Country::factory()->create([
            'iso_code' => 'XX',
            'is_supported_for_payroll' => false
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        
        $employees = Employee::factory()->count(2)->create([
            'company_uuid' => $company->uuid,
            'salary' => 25000
        ]);

        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $result = $this->service->calculateBatchPayroll($employees, $periodStart, $periodEnd);

        $this->assertEquals(2, $result['summary']['total_employees']);
        $this->assertEquals(2, $result['summary']['successful_calculations']);
        $this->assertEquals(0, $result['summary']['failed_calculations']);
        $this->assertNotEmpty($result['summary']['errors']); // Country not supported errors
    }

    /** @test */
    public function it_can_create_payroll_records_from_calculations()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create([
            'company_uuid' => $company->uuid,
            'salary' => 40000
        ]);

        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        // First calculate payroll
        $calculations = $this->service->calculateBatchPayroll(
            collect([$employee]), 
            $periodStart, 
            $periodEnd
        );

        // Create payroll records
        $payroll = $this->service->createPayrollRecords(
            $company,
            $calculations,
            $periodStart,
            $periodEnd
        );

        $this->assertInstanceOf(Payroll::class, $payroll);
        $this->assertEquals($company->uuid, $payroll->company_uuid);
        $this->assertEquals('draft', $payroll->status);
        $this->assertEquals(1, $payroll->total_employees);
        $this->assertEquals(40000, $payroll->total_gross_salary);
        $this->assertDatabaseHas('payrolls', ['uuid' => $payroll->uuid]);
        
        // Check that payroll items were created
        $this->assertGreaterThan(0, PayrollItem::where('payroll_uuid', $payroll->uuid)->count());
    }

    /** @test */
    public function it_can_approve_draft_payroll()
    {
        $user = User::factory()->create();
        $payroll = Payroll::factory()->draft()->create();

        $result = $this->service->approvePayroll($payroll, $user->uuid);

        $this->assertTrue($result);
        $this->assertEquals('approved', $payroll->fresh()->status);
        $this->assertEquals($user->uuid, $payroll->fresh()->approved_by);
        $this->assertNotNull($payroll->fresh()->approved_at);
    }

    /** @test */
    public function it_cannot_approve_non_draft_payroll()
    {
        $user = User::factory()->create();
        $payroll = Payroll::factory()->approved()->create();

        $result = $this->service->approvePayroll($payroll, $user->uuid);

        $this->assertFalse($result);
        $this->assertEquals('approved', $payroll->fresh()->status); // Unchanged
    }

    /** @test */
    public function it_can_process_approved_payroll()
    {
        $payroll = Payroll::factory()->approved()->create();

        $result = $this->service->processPayroll($payroll);

        $this->assertTrue($result);
        $this->assertEquals('processed', $payroll->fresh()->status);
        $this->assertNotNull($payroll->fresh()->processed_at);
    }

    /** @test */
    public function it_cannot_process_non_approved_payroll()
    {
        $payroll = Payroll::factory()->draft()->create();

        $result = $this->service->processPayroll($payroll);

        $this->assertFalse($result);
        $this->assertEquals('draft', $payroll->fresh()->status); // Unchanged
    }

    /** @test */
    public function it_handles_multiple_allowances_and_deductions()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);
        
        StatutoryDeductionTemplate::factory()->southAfricanUIF()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create([
            'company_uuid' => $company->uuid,
            'salary' => 50000
        ]);

        // Create multiple company templates
        CompanyPayrollTemplate::factory()->allowance()->create([
            'company_uuid' => $company->uuid,
            'code' => 'TRANSPORT',
            'name' => 'Transport Allowance',
            'amount' => 2000
        ]);

        CompanyPayrollTemplate::factory()->allowance()->create([
            'company_uuid' => $company->uuid,
            'code' => 'HOUSING',
            'name' => 'Housing Allowance',
            'amount' => 5000
        ]);

        CompanyPayrollTemplate::factory()->deduction()->create([
            'company_uuid' => $company->uuid,
            'code' => 'MEDICAL',
            'name' => 'Medical Aid',
            'amount' => 1200
        ]);

        // Create multiple employee items
        EmployeePayrollItem::factory()->allowance()->create([
            'employee_uuid' => $employee->uuid,
            'code' => 'BONUS',
            'name' => 'Performance Bonus',
            'amount' => 3000,
            'effective_from' => now()->startOfMonth()
        ]);

        EmployeePayrollItem::factory()->deduction()->create([
            'employee_uuid' => $employee->uuid,
            'code' => 'PARKING',
            'name' => 'Parking Fee',
            'amount' => 300,
            'effective_from' => now()->startOfMonth()
        ]);

        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $result = $this->service->calculateEmployeePayroll($employee, $periodStart, $periodEnd);

        $this->assertEquals(10000, $result['total_allowances']); // 2000 + 5000 + 3000
        $this->assertGreaterThanOrEqual(1500, $result['total_deductions']); // 1200 + 300 + statutory
        $this->assertCount(2, $result['payroll_items']['allowances']['company']);
        $this->assertCount(1, $result['payroll_items']['allowances']['employee']);
        $this->assertCount(1, $result['payroll_items']['deductions']['company']);
        $this->assertCount(1, $result['payroll_items']['deductions']['employee']);
        $this->assertGreaterThan(0, count($result['payroll_items']['deductions']['statutory']));
    }

    /** @test */
    public function it_excludes_inactive_and_non_effective_items()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create([
            'company_uuid' => $company->uuid,
            'salary' => 30000
        ]);

        // Create inactive company template
        CompanyPayrollTemplate::factory()->allowance()->create([
            'company_uuid' => $company->uuid,
            'code' => 'INACTIVE',
            'name' => 'Inactive Allowance',
            'amount' => 1000,
            'is_active' => false
        ]);

        // Create future-effective employee item
        EmployeePayrollItem::factory()->allowance()->create([
            'employee_uuid' => $employee->uuid,
            'code' => 'FUTURE',
            'name' => 'Future Allowance',
            'amount' => 500,
            'effective_from' => now()->addMonth()
        ]);

        // Create active, effective items
        CompanyPayrollTemplate::factory()->allowance()->create([
            'company_uuid' => $company->uuid,
            'code' => 'ACTIVE',
            'name' => 'Active Allowance',
            'amount' => 800
        ]);

        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $result = $this->service->calculateEmployeePayroll($employee, $periodStart, $periodEnd);

        $this->assertEquals(800, $result['total_allowances']); // Only active allowance
        $this->assertCount(1, $result['payroll_items']['allowances']['company']);
        $this->assertCount(0, $result['payroll_items']['allowances']['employee']);
        $this->assertEquals('ACTIVE', $result['payroll_items']['allowances']['company'][0]['code']);
    }

    /** @test */
    public function it_calculates_correct_net_salary()
    {
        $country = Country::factory()->southAfrica()->create();
        $jurisdiction = TaxJurisdiction::factory()->create(['country_uuid' => $country->uuid]);
        
        StatutoryDeductionTemplate::factory()->southAfricanPAYE()->create([
            'jurisdiction_uuid' => $jurisdiction->uuid
        ]);

        $company = Company::factory()->create(['country_uuid' => $country->uuid]);
        $employee = Employee::factory()->create([
            'company_uuid' => $company->uuid,
            'salary' => 20000
        ]);

        // Add specific allowance and deduction with known amounts
        CompanyPayrollTemplate::factory()->allowance()->create([
            'company_uuid' => $company->uuid,
            'amount' => 3000
        ]);

        CompanyPayrollTemplate::factory()->deduction()->create([
            'company_uuid' => $company->uuid,
            'amount' => 1000
        ]);

        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        $result = $this->service->calculateEmployeePayroll($employee, $periodStart, $periodEnd);

        $expectedNetSalary = $result['gross_salary'] + $result['total_allowances'] - $result['total_deductions'];
        $this->assertEquals($expectedNetSalary, $result['net_salary']);
        $this->assertEquals(20000, $result['gross_salary']);
        $this->assertEquals(3000, $result['total_allowances']);
        $this->assertGreaterThanOrEqual(1000, $result['total_deductions']); // Company deduction + any statutory
    }
}