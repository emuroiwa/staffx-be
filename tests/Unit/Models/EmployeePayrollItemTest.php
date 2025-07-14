<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Employee;
use App\Models\EmployeePayrollItem;
use App\Models\CompanyPayrollTemplate;
use App\Models\StatutoryDeductionTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class EmployeePayrollItemTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_an_employee_payroll_item()
    {
        $employee = Employee::factory()->create();
        
        $itemData = [
            'employee_uuid' => $employee->uuid,
            'code' => 'TRANSPORT',
            'name' => 'Transport Allowance',
            'type' => 'allowance',
            'calculation_method' => 'fixed_amount',
            'amount' => 3000.00,
            'effective_from' => now()->startOfMonth(),
            'is_recurring' => true,
            'status' => 'active'
        ];

        $item = EmployeePayrollItem::create($itemData);

        $this->assertDatabaseHas('employee_payroll_items', [
            'employee_uuid' => $employee->uuid,
            'code' => 'TRANSPORT',
            'name' => 'Transport Allowance',
            'type' => 'allowance'
        ]);

        $this->assertEquals('TRANSPORT', $item->code);
        $this->assertEquals(3000.00, $item->amount);
        $this->assertEquals('allowance', $item->type);
        $this->assertTrue($item->is_recurring);
    }

    /** @test */
    public function it_belongs_to_an_employee()
    {
        $employee = Employee::factory()->create();
        $item = EmployeePayrollItem::factory()->create([
            'employee_uuid' => $employee->uuid
        ]);

        $this->assertEquals($employee->uuid, $item->employee->uuid);
        $this->assertEquals($employee->first_name, $item->employee->first_name);
    }

    /** @test */
    public function it_can_belong_to_a_company_template()
    {
        $template = CompanyPayrollTemplate::factory()->create();
        $item = EmployeePayrollItem::factory()->fromTemplate()->create([
            'template_uuid' => $template->uuid
        ]);

        $this->assertEquals($template->uuid, $item->template->uuid);
        $this->assertEquals($template->name, $item->template->name);
    }

    /** @test */
    public function it_can_belong_to_a_statutory_template()
    {
        $statutoryTemplate = StatutoryDeductionTemplate::factory()->create();
        $item = EmployeePayrollItem::factory()->fromStatutoryTemplate()->create([
            'statutory_template_uuid' => $statutoryTemplate->uuid
        ]);

        $this->assertEquals($statutoryTemplate->uuid, $item->statutoryTemplate->uuid);
        $this->assertEquals($statutoryTemplate->name, $item->statutoryTemplate->name);
    }

    /** @test */
    public function it_can_calculate_fixed_amount()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);
        $item = EmployeePayrollItem::factory()->fixedAmount()->create([
            'employee_uuid' => $employee->uuid,
            'amount' => 2500.00
        ]);

        $result = $item->calculateAmount(55000);
        
        $this->assertEquals(2500.00, $result);
    }

    /** @test */
    public function it_can_calculate_percentage_of_salary()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);
        $item = EmployeePayrollItem::factory()->percentageOfSalary()->create([
            'employee_uuid' => $employee->uuid,
            'percentage' => 10.00
        ]);

        $grossSalary = 55000;
        $result = $item->calculateAmount($grossSalary);
        
        $this->assertEquals(5500.00, $result); // 55000 * 0.10
    }

    /** @test */
    public function it_can_calculate_percentage_of_basic_salary()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);
        $item = EmployeePayrollItem::factory()->percentageOfBasic()->create([
            'employee_uuid' => $employee->uuid,
            'percentage' => 15.00
        ]);

        $result = $item->calculateAmount(60000);
        
        $this->assertEquals(7500.00, $result); // 50000 * 0.15 (basic salary)
    }

    /** @test */
    public function it_can_calculate_formula_based_amount()
    {
        $employee = Employee::factory()->create([
            'salary' => 50000,
            'hire_date' => now()->subYears(3)
        ]);
        
        $item = EmployeePayrollItem::factory()->formula()->create([
            'employee_uuid' => $employee->uuid,
            'formula_expression' => '{basic_salary} * 0.05'
        ]);

        $result = $item->calculateAmount(60000);
        
        // Expected: (50000 * 0.05) = 2500
        $this->assertEquals(2500.00, $result);
    }

    /** @test */
    public function it_returns_manual_amount_for_manual_calculation()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);
        $item = EmployeePayrollItem::factory()->manual()->create([
            'employee_uuid' => $employee->uuid,
            'amount' => 1500.00
        ]);

        $result = $item->calculateAmount(60000);
        
        $this->assertEquals(1500.00, $result);
    }

    /** @test */
    public function it_handles_unsafe_formula_expressions()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);
        
        $item = EmployeePayrollItem::factory()->formula()->create([
            'employee_uuid' => $employee->uuid,
            'formula_expression' => 'exec("rm -rf /"); {basic_salary} * 0.05'
        ]);

        $result = $item->calculateAmount(60000);
        
        $this->assertEquals(0, $result); // Should return 0 for unsafe expressions
    }

    /** @test */
    public function it_checks_if_effective_for_date()
    {
        $item = EmployeePayrollItem::factory()->create([
            'effective_from' => now()->subMonth(),
            'effective_to' => now()->addMonth(),
            'status' => 'active'
        ]);

        $this->assertTrue($item->isEffectiveForDate(now()));
        $this->assertFalse($item->isEffectiveForDate(now()->subMonths(2)));
        $this->assertFalse($item->isEffectiveForDate(now()->addMonths(2)));
    }

    /** @test */
    public function it_returns_zero_for_inactive_items()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);
        $item = EmployeePayrollItem::factory()->fixedAmount()->create([
            'employee_uuid' => $employee->uuid,
            'amount' => 2500.00,
            'status' => 'suspended'
        ]);

        $result = $item->calculateAmount(55000);
        
        $this->assertEquals(0, $result);
    }

    /** @test */
    public function it_returns_zero_for_items_not_effective_on_payroll_date()
    {
        $employee = Employee::factory()->create(['salary' => 50000]);
        $item = EmployeePayrollItem::factory()->fixedAmount()->create([
            'employee_uuid' => $employee->uuid,
            'amount' => 2500.00,
            'effective_from' => now()->addMonth() // Starts next month
        ]);

        $result = $item->calculateAmount(55000, now());
        
        $this->assertEquals(0, $result);
    }

    /** @test */
    public function it_can_identify_statutory_items()
    {
        $statutoryItem = EmployeePayrollItem::factory()->fromStatutoryTemplate()->create();
        $companyItem = EmployeePayrollItem::factory()->fromTemplate()->create();
        $manualItem = EmployeePayrollItem::factory()->create();

        $this->assertTrue($statutoryItem->isStatutory());
        $this->assertFalse($companyItem->isStatutory());
        $this->assertFalse($manualItem->isStatutory());
    }

    /** @test */
    public function it_can_check_if_approval_is_required()
    {
        $template = CompanyPayrollTemplate::factory()->create(['requires_approval' => true]);
        $itemRequiringApproval = EmployeePayrollItem::factory()->fromTemplate()->create([
            'template_uuid' => $template->uuid
        ]);

        $template2 = CompanyPayrollTemplate::factory()->create(['requires_approval' => false]);
        $itemNotRequiringApproval = EmployeePayrollItem::factory()->fromTemplate()->create([
            'template_uuid' => $template2->uuid
        ]);

        $this->assertTrue($itemRequiringApproval->requiresApproval());
        $this->assertFalse($itemNotRequiringApproval->requiresApproval());
    }

    /** @test */
    public function it_can_approve_pending_items()
    {
        $user = User::factory()->create();
        $item = EmployeePayrollItem::factory()->pendingApproval()->create();

        $result = $item->approve($user, 'Approved for transport allowance');

        $this->assertTrue($result);
        $this->assertEquals('active', $item->fresh()->status);
        $this->assertEquals($user->uuid, $item->fresh()->approved_by);
        $this->assertNotNull($item->fresh()->approved_at);
    }

    /** @test */
    public function it_cannot_approve_non_pending_items()
    {
        $user = User::factory()->create();
        $item = EmployeePayrollItem::factory()->approved()->create();

        $result = $item->approve($user);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_can_suspend_items()
    {
        $user = User::factory()->create();
        $item = EmployeePayrollItem::factory()->approved()->create();

        $result = $item->suspend($user, 'Suspended due to policy change');

        $this->assertTrue($result);
        $this->assertEquals('suspended', $item->fresh()->status);
    }

    /** @test */
    public function it_can_cancel_items()
    {
        $user = User::factory()->create();
        $item = EmployeePayrollItem::factory()->approved()->create();

        $result = $item->cancel($user, 'Cancelled by employee request');

        $this->assertTrue($result);
        $this->assertEquals('cancelled', $item->fresh()->status);
        $this->assertNotNull($item->fresh()->effective_to);
    }

    /** @test */
    public function it_can_scope_active_items()
    {
        EmployeePayrollItem::factory()->create(['status' => 'active']);
        EmployeePayrollItem::factory()->create(['status' => 'active']);
        EmployeePayrollItem::factory()->create(['status' => 'suspended']);

        $activeItems = EmployeePayrollItem::active()->get();

        $this->assertCount(2, $activeItems);
        $this->assertTrue($activeItems->every(fn($item) => $item->status === 'active'));
    }

    /** @test */
    public function it_can_scope_by_type()
    {
        EmployeePayrollItem::factory()->allowance()->create();
        EmployeePayrollItem::factory()->allowance()->create();
        EmployeePayrollItem::factory()->deduction()->create();

        $allowances = EmployeePayrollItem::byType('allowance')->get();
        $deductions = EmployeePayrollItem::byType('deduction')->get();

        $this->assertCount(2, $allowances);
        $this->assertCount(1, $deductions);
    }

    /** @test */
    public function it_can_scope_effective_for_date()
    {
        $date = now();
        
        EmployeePayrollItem::factory()->create([
            'effective_from' => $date->copy()->subMonth(),
            'effective_to' => $date->copy()->addMonth()
        ]);
        
        EmployeePayrollItem::factory()->create([
            'effective_from' => $date->copy()->addMonth(),
            'effective_to' => null
        ]);

        $effectiveItems = EmployeePayrollItem::effectiveForDate($date)->get();

        $this->assertCount(1, $effectiveItems);
    }

    /** @test */
    public function it_can_scope_recurring_and_one_time_items()
    {
        EmployeePayrollItem::factory()->recurring()->create();
        EmployeePayrollItem::factory()->recurring()->create();
        EmployeePayrollItem::factory()->oneTime()->create();

        $recurringItems = EmployeePayrollItem::recurring()->get();
        $oneTimeItems = EmployeePayrollItem::oneTime()->get();

        $this->assertCount(2, $recurringItems);
        $this->assertCount(1, $oneTimeItems);
    }

    /** @test */
    public function it_can_scope_pending_approval_items()
    {
        EmployeePayrollItem::factory()->pendingApproval()->create();
        EmployeePayrollItem::factory()->pendingApproval()->create();
        EmployeePayrollItem::factory()->approved()->create();

        $pendingItems = EmployeePayrollItem::pendingApproval()->get();

        $this->assertCount(2, $pendingItems);
        $this->assertTrue($pendingItems->every(fn($item) => $item->status === 'pending_approval'));
    }

    /** @test */
    public function it_auto_generates_uuid_on_creation()
    {
        $employee = Employee::factory()->create();
        
        $item = EmployeePayrollItem::create([
            'employee_uuid' => $employee->uuid,
            'code' => 'TEST',
            'name' => 'Test Item',
            'type' => 'allowance',
            'calculation_method' => 'fixed_amount',
            'amount' => 1000,
            'effective_from' => now()
        ]);

        $this->assertNotEmpty($item->uuid);
        $this->assertIsString($item->uuid);
        $this->assertEquals(36, strlen($item->uuid));
    }
}