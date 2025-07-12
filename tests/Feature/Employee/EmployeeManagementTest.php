<?php

namespace Tests\Feature\Employee;

use Tests\TestCase;
use Tests\Traits\EmployeeTestTrait;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker, EmployeeTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->setupTestEnvironment();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    /** @test */
    public function test_can_list_employees_with_pagination()
    {
        // Create multiple employees
        for ($i = 1; $i <= 15; $i++) {
            $this->createTestEmployee([
                'uuid' => \Str::uuid(),
                'employee_id' => "EMP00{$i}",
                'first_name' => "Employee{$i}",
                'email' => "employee{$i}@testcompany.com"
            ]);
        }

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/employees?per_page=10');

        $response->assertStatus(200);
    }

    /** @test */
    public function test_can_filter_employees_by_status()
    {
        // Create active employee
        $activeEmployee = $this->createTestEmployee([
            'uuid' => \Str::uuid(),
            'status' => 'active',
            'first_name' => 'Active',
            'email' => 'active@testcompany.com'
        ]);

        // Create inactive employee
        $inactiveEmployee = $this->createTestEmployee([
            'uuid' => \Str::uuid(),
            'status' => 'inactive',
            'first_name' => 'Inactive',
            'email' => 'inactive@testcompany.com'
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/employees?status=active');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.first_name', 'Active');
    }

    /** @test */
    public function test_can_search_employees_by_name()
    {
        $this->createTestEmployee([
            'uuid' => \Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john.smith@testcompany.com'
        ]);

        $this->createTestEmployee([
            'uuid' => \Str::uuid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@testcompany.com'
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/employees?search=John');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.first_name', 'John');
    }

    /** @test */
    public function test_can_create_employee_with_valid_data()
    {
        $employeeData = [
            'employee_id' => 'EMP999',
            'first_name' => 'New',
            'last_name' => 'Employee',
            'email' => 'new.employee@testcompany.com',
            'phone' => '+1234567890',
            'address' => '123 Test St',
            'dob' => '1985-06-15',
            'start_date' => now()->format('Y-m-d'),
            'hire_date' => now()->format('Y-m-d'),
            'status' => 'active',
            'employment_type' => 'full_time',
            'salary' => 80000,
            'currency' => 'USD',
            'tax_number' => 'TAX999',
            'pay_frequency' => 'monthly',
            'national_id' => 'ID999',
            'passport_number' => 'P999',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_phone' => '+1234567891',
            'department_uuid' => $this->testDepartment->id,
            'position_uuid' => $this->testPosition->id,
            'bank_details' => [
                'bank_name' => 'New Bank',
                'account_number' => '9999999999',
                'routing_number' => '999999999',
                'account_type' => 'checking'
            ]
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', $employeeData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => $this->assertEmployeeJsonStructure(),
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'employee_id' => 'EMP999',
                    'first_name' => 'New',
                    'last_name' => 'Employee',
                    'email' => 'new.employee@testcompany.com'
                ]
            ]);

        $this->assertDatabaseHas('employees', [
            'employee_id' => 'EMP999',
            'first_name' => 'New',
            'last_name' => 'Employee',
            'company_uuid' => $this->testCompany->uuid
        ]);
    }

    /** @test */
    public function test_cannot_create_employee_with_duplicate_employee_id()
    {
        $this->createTestEmployee(['employee_id' => 'EMP123']);

        $duplicateData = [
            'employee_id' => 'EMP123',
            'first_name' => 'Duplicate',
            'last_name' => 'Employee',
            'email' => 'duplicate@testcompany.com',
            'department_uuid' => $this->testDepartment->id,
            'position_uuid' => $this->testPosition->id
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', $duplicateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id']);
    }

    /** @test */
    public function test_cannot_create_employee_with_invalid_manager()
    {
        $employeeData = [
            'employee_id' => 'EMP888',
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => 'test@testcompany.com',
            'department_uuid' => $this->testDepartment->id,
            'position_uuid' => $this->testPosition->id,
            'manager_uuid' => 'invalid-uuid'
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', $employeeData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['manager_uuid']);
    }

    /** @test */
    public function test_can_show_employee_details()
    {
        $employee = $this->createTestEmployee();

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/employees/{$employee->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => array_merge($this->assertEmployeeJsonStructure(), [
                    'department',
                    'position',
                    'manager',
                    'direct_reports',
                    'user',
                    'company'
                ])
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'uuid' => $employee->uuid,
                    'employee_id' => $employee->employee_id,
                    'first_name' => $employee->first_name
                ]
            ]);
    }

    /** @test */
    public function test_can_update_employee()
    {
        $employee = $this->createTestEmployee();

        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'salary' => 90000
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->putJson("/api/employees/{$employee->uuid}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'first_name' => 'Updated',
                    'last_name' => 'Name',
                    'salary' => 90000
                ]
            ]);

        $this->assertDatabaseHas('employees', [
            'uuid' => $employee->uuid,
            'first_name' => 'Updated',
            'salary' => 90000
        ]);
    }

    /** @test */
    public function test_can_delete_employee()
    {
        $employee = $this->createTestEmployee();

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->deleteJson("/api/employees/{$employee->uuid}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Employee deleted successfully'
            ]);

        $this->assertDatabaseMissing('employees', [
            'uuid' => $employee->uuid
        ]);
    }

    /** @test */
    public function test_can_update_employee_status()
    {
        $employee = $this->createTestEmployee(['status' => 'active']);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->patchJson("/api/employees/{$employee->uuid}/status", [
                'status' => 'inactive',
                'termination_date' => now()->format('Y-m-d'),
                'termination_reason' => 'Resignation'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'inactive'
                ]
            ]);

        $this->assertDatabaseHas('employees', [
            'uuid' => $employee->uuid,
            'status' => 'inactive'
        ]);
    }

    /** @test */
    public function test_can_get_employee_statistics()
    {
        // Create employees with different statuses
        $this->createTestEmployee(['status' => 'active', 'is_director' => true]);
        $this->createTestEmployee(['status' => 'active', 'is_independent_contractor' => true]);
        $this->createTestEmployee(['status' => 'inactive']);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/employees/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_employees',
                    'active_employees',
                    'inactive_employees',
                    'directors',
                    'contractors',
                    'average_salary',
                    'total_payroll',
                    'departments_count',
                    'positions_count'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_employees' => 3,
                    'active_employees' => 2,
                    'inactive_employees' => 1,
                    'directors' => 1,
                    'contractors' => 1
                ]
            ]);
    }

    /** @test */
    public function test_can_get_organogram()
    {
        $hierarchy = $this->createManagerHierarchy();

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/employees/organogram');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'uuid',
                        'name',
                        'employee_id',
                        'position',
                        'email',
                        'is_director',
                        'manager_uuid',
                        'children'
                    ]
                ]
            ])
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function test_can_get_potential_managers()
    {
        $hierarchy = $this->createManagerHierarchy();
        $employee = $hierarchy['employee'];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/employees/potential-managers/{$employee->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'uuid',
                        'first_name',
                        'last_name',
                        'employee_id'
                    ]
                ]
            ])
            ->assertJson(['success' => true]);

        // Should not include the employee themselves or their subordinates
        $potentialManagers = $response->json('data');
        $employeeUuids = array_column($potentialManagers, 'uuid');
        
        $this->assertNotContains($employee->uuid, $employeeUuids);
    }

    /** @test */
    public function test_prevents_circular_reporting_structure()
    {
        $hierarchy = $this->createManagerHierarchy();
        $ceo = $hierarchy['ceo'];
        $manager = $hierarchy['manager'];

        // Try to make CEO report to Manager (would create circular reporting)
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->putJson("/api/employees/{$ceo->uuid}", [
                'manager_uuid' => $manager->uuid
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'This manager assignment would create circular reporting.'
            ]);
    }

    /** @test */
    public function test_employee_company_isolation()
    {
        // Create another company and employee
        $otherCompany = \App\Models\Company::factory()->create();
        $otherEmployee = Employee::create([
            'uuid' => \Str::uuid(),
            'company_uuid' => $otherCompany->uuid,
            'employee_id' => 'OTHER001',
            'first_name' => 'Other',
            'last_name' => 'Employee',
            'email' => 'other@othercompany.com'
        ]);

        // Try to access other company's employee
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/employees/{$otherEmployee->uuid}");

        $response->assertStatus(404);

        // Try to assign manager from other company
        $employee = $this->createTestEmployee();
        
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->putJson("/api/employees/{$employee->uuid}", [
                'manager_uuid' => $otherEmployee->uuid
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['manager_uuid']);
    }

    /** @test */
    public function test_unauthorized_access_returns_401()
    {
        $response = $this->getJson('/api/employees');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_validates_required_fields_on_creation()
    {
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'employee_id',
                'first_name',
                'last_name',
                'email'
            ]);
    }

    /** @test */
    public function test_validates_email_format()
    {
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', [
                'employee_id' => 'EMP001',
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'invalid-email-format'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function test_validates_salary_is_numeric()
    {
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', [
                'employee_id' => 'EMP001',
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
                'salary' => 'not-a-number'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['salary']);
    }

    /** @test */
    public function test_handles_department_head_assignment()
    {
        $employee = $this->createTestEmployee();

        // Update department to have this employee as head
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->putJson("/api/departments/{$this->testDepartment->id}", [
                'head_of_department_id' => $employee->uuid
            ]);

        $response->assertStatus(200);

        // Verify employee is marked as department head
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/employees/{$employee->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_department_head', true);
    }
}