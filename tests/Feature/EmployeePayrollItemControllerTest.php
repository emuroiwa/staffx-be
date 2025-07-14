<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeePayrollItem;
use App\Models\CompanyPayrollTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class EmployeePayrollItemControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Company $company;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_uuid' => $this->company->uuid]);
        $this->employee = Employee::factory()->create([
            'company_uuid' => $this->company->uuid,
            'salary' => 25000
        ]);
    }

    /** @test */
    public function it_can_list_employee_payroll_items()
    {
        EmployeePayrollItem::factory()->count(3)->create(['employee_uuid' => $this->employee->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/employee-payroll-items');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'uuid',
                            'employee_uuid',
                            'code',
                            'name',
                            'type',
                            'calculation_method',
                            'amount',
                            'status'
                        ]
                    ]
                ],
                'message'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(3, $response->json('data.data'));
    }

    /** @test */
    public function it_can_filter_items_by_employee()
    {
        $otherEmployee = Employee::factory()->create(['company_uuid' => $this->company->uuid]);
        
        EmployeePayrollItem::factory()->create(['employee_uuid' => $this->employee->uuid]);
        EmployeePayrollItem::factory()->create(['employee_uuid' => $otherEmployee->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/employee-payroll-items?employee_uuid={$this->employee->uuid}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals($this->employee->uuid, $response->json('data.data.0.employee_uuid'));
    }

    /** @test */
    public function it_can_filter_items_by_type()
    {
        EmployeePayrollItem::factory()->allowance()->create(['employee_uuid' => $this->employee->uuid]);
        EmployeePayrollItem::factory()->deduction()->create(['employee_uuid' => $this->employee->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/employee-payroll-items?type=allowance');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('allowance', $response->json('data.data.0.type'));
    }

    /** @test */
    public function it_can_create_employee_payroll_item()
    {
        $payload = [
            'employee_uuid' => $this->employee->uuid,
            'code' => 'OVERTIME',
            'name' => 'Overtime Pay',
            'type' => 'allowance',
            'calculation_method' => 'fixed_amount',
            'amount' => 1500,
            'effective_from' => now()->startOfMonth()->format('Y-m-d'),
            'is_recurring' => true
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/employee-payroll-items', $payload);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'uuid',
                    'employee_uuid',
                    'code',
                    'name',
                    'type',
                    'calculation_method',
                    'amount',
                    'status'
                ],
                'message'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('OVERTIME', $response->json('data.code'));
        $this->assertEquals('allowance', $response->json('data.type'));
        $this->assertEquals('active', $response->json('data.status'));

        $this->assertDatabaseHas('employee_payroll_items', [
            'employee_uuid' => $this->employee->uuid,
            'code' => 'OVERTIME',
            'type' => 'allowance',
            'amount' => 1500,
            'status' => 'active'
        ]);
    }

    /** @test */
    public function it_validates_payroll_item_creation_data()
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/employee-payroll-items', [
                'employee_uuid' => 'invalid-uuid',
                'code' => '', // Required
                'name' => '', // Required
                'type' => 'invalid', // Invalid value
                'calculation_method' => 'invalid', // Invalid value
                'effective_from' => 'invalid-date'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'employee_uuid',
                'code',
                'name',
                'type',
                'calculation_method',
                'effective_from'
            ]);
    }

    /** @test */
    public function it_prevents_duplicate_codes_for_same_employee()
    {
        EmployeePayrollItem::factory()->create([
            'employee_uuid' => $this->employee->uuid,
            'code' => 'DUPLICATE',
            'status' => 'active'
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/employee-payroll-items', [
                'employee_uuid' => $this->employee->uuid,
                'code' => 'DUPLICATE',
                'name' => 'Another Item',
                'type' => 'allowance',
                'calculation_method' => 'fixed_amount',
                'amount' => 1000,
                'effective_from' => now()->format('Y-m-d')
            ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('already exists', $response->json('message'));
    }

    /** @test */
    public function it_creates_pending_approval_items_when_required()
    {
        $payload = [
            'employee_uuid' => $this->employee->uuid,
            'code' => 'BONUS',
            'name' => 'Performance Bonus',
            'type' => 'allowance',
            'calculation_method' => 'fixed_amount',
            'amount' => 5000,
            'effective_from' => now()->format('Y-m-d'),
            'requires_approval' => true
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/employee-payroll-items', $payload);

        $response->assertCreated();
        $this->assertEquals('pending_approval', $response->json('data.status'));
    }

    /** @test */
    public function it_can_show_item_details()
    {
        $item = EmployeePayrollItem::factory()->create(['employee_uuid' => $this->employee->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/employee-payroll-items/{$item->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'uuid',
                    'employee_uuid',
                    'code',
                    'name',
                    'type',
                    'calculation_method',
                    'amount',
                    'status',
                    'employee'
                ],
                'message'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($item->uuid, $response->json('data.uuid'));
    }

    /** @test */
    public function it_returns_404_for_non_existent_item()
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/employee-payroll-items/non-existent-uuid');

        $response->assertNotFound();
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_prevents_access_to_other_company_items()
    {
        $otherCompany = Company::factory()->create();
        $otherEmployee = Employee::factory()->create(['company_uuid' => $otherCompany->uuid]);
        $otherItem = EmployeePayrollItem::factory()->create(['employee_uuid' => $otherEmployee->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/employee-payroll-items/{$otherItem->uuid}");

        $response->assertForbidden();
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_can_update_active_item()
    {
        $item = EmployeePayrollItem::factory()->create([
            'employee_uuid' => $this->employee->uuid,
            'status' => 'active'
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/employee-payroll-items/{$item->uuid}", [
                'name' => 'Updated Item Name',
                'amount' => 2500
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseHas('employee_payroll_items', [
            'uuid' => $item->uuid,
            'name' => 'Updated Item Name',
            'amount' => 2500
        ]);
    }

    /** @test */
    public function it_cannot_update_suspended_item()
    {
        $item = EmployeePayrollItem::factory()->create([
            'employee_uuid' => $this->employee->uuid,
            'status' => 'suspended'
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/employee-payroll-items/{$item->uuid}", [
                'name' => 'Should not update'
            ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_can_delete_item()
    {
        $item = EmployeePayrollItem::factory()->create(['employee_uuid' => $this->employee->uuid]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/employee-payroll-items/{$item->uuid}");

        $response->assertOk();
        $this->assertTrue($response->json('success'));

        $this->assertDatabaseMissing('employee_payroll_items', ['uuid' => $item->uuid]);
    }

    /** @test */
    public function it_can_approve_pending_item()
    {
        $item = EmployeePayrollItem::factory()->pendingApproval()->create([
            'employee_uuid' => $this->employee->uuid
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/employee-payroll-items/{$item->uuid}/approve", [
                'approval_notes' => 'Approved for good performance'
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertEquals('active', $response->json('data.status'));

        $this->assertDatabaseHas('employee_payroll_items', [
            'uuid' => $item->uuid,
            'status' => 'active',
            'approved_by' => $this->user->uuid
        ]);
    }

    /** @test */
    public function it_cannot_approve_non_pending_item()
    {
        $item = EmployeePayrollItem::factory()->approved()->create([
            'employee_uuid' => $this->employee->uuid
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/employee-payroll-items/{$item->uuid}/approve");

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    /** @test */
    public function it_can_suspend_active_item()
    {
        $item = EmployeePayrollItem::factory()->approved()->create([
            'employee_uuid' => $this->employee->uuid
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/employee-payroll-items/{$item->uuid}/suspend", [
                'suspension_reason' => 'Suspended due to policy change'
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertEquals('suspended', $response->json('data.status'));

        $this->assertDatabaseHas('employee_payroll_items', [
            'uuid' => $item->uuid,
            'status' => 'suspended'
        ]);
    }

    /** @test */
    public function it_validates_suspension_reason()
    {
        $item = EmployeePayrollItem::factory()->approved()->create([
            'employee_uuid' => $this->employee->uuid
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/employee-payroll-items/{$item->uuid}/suspend");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['suspension_reason']);
    }

    /** @test */
    public function it_can_calculate_preview_amount()
    {
        $item = EmployeePayrollItem::factory()->fixedAmount()->create([
            'employee_uuid' => $this->employee->uuid,
            'amount' => 1500
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/employee-payroll-items/{$item->uuid}/calculate-preview", [
                'gross_salary' => 30000
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'calculated_amount',
                    'gross_salary_used',
                    'calculation_date',
                    'calculation_method',
                    'is_effective'
                ],
                'message'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(1500, $response->json('data.calculated_amount'));
        $this->assertEquals('fixed_amount', $response->json('data.calculation_method'));
    }

    /** @test */
    public function it_can_create_percentage_based_item()
    {
        $payload = [
            'employee_uuid' => $this->employee->uuid,
            'code' => 'COMMISSION',
            'name' => 'Sales Commission',
            'type' => 'allowance',
            'calculation_method' => 'percentage_of_salary',
            'percentage' => 5,
            'effective_from' => now()->format('Y-m-d')
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/employee-payroll-items', $payload);

        $response->assertCreated();
        $this->assertTrue($response->json('success'));
        $this->assertEquals(5, $response->json('data.percentage'));
    }

    /** @test */
    public function it_can_create_formula_based_item()
    {
        $payload = [
            'employee_uuid' => $this->employee->uuid,
            'code' => 'FORMULA_ITEM',
            'name' => 'Formula Based Item',
            'type' => 'allowance',
            'calculation_method' => 'formula',
            'formula_expression' => '{basic_salary} * 0.02',
            'effective_from' => now()->format('Y-m-d')
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/employee-payroll-items', $payload);

        $response->assertCreated();
        $this->assertTrue($response->json('success'));
        $this->assertEquals('{basic_salary} * 0.02', $response->json('data.formula_expression'));
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/employee-payroll-items');
        $response->assertUnauthorized();
    }

    /** @test */
    public function it_handles_user_without_company()
    {
        $userWithoutCompany = User::factory()->create(['company_uuid' => null]);

        $response = $this->actingAs($userWithoutCompany, 'api')
            ->getJson('/api/employee-payroll-items');

        $response->assertForbidden();
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('User must belong to a company', $response->json('message'));
    }
}