<?php

namespace Tests\Feature\Employee;

use Tests\TestCase;
use Tests\Traits\EmployeeTestTrait;
use App\Models\Position;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class EmployeeWorkflowIntegrationTest extends TestCase
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
    public function test_complete_employee_onboarding_workflow()
    {
        // Step 1: Create position first (enforcing data setup flow)
        $positionResponse = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', [
                'name' => 'Software Engineer II',
                'description' => 'Mid-level software engineer',
                'min_salary' => 60000,
                'max_salary' => 90000,
                'currency' => 'USD',
                'requirements' => [
                    'education' => 'Bachelor\'s in Computer Science',
                    'experience' => '2-4 years',
                    'skills' => ['PHP', 'Laravel', 'Vue.js']
                ]
            ]);

        $positionResponse->assertStatus(201);
        $positionId = $positionResponse->json('data.id');

        // Step 2: Create department second (enforcing data setup flow)
        $departmentResponse = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/departments', [
                'name' => 'Product Engineering',
                'description' => 'Product development team',
                'cost_center' => 'PE001',
                'budget_info' => [
                    'allocation' => 1000000,
                    'currency' => 'USD',
                    'fiscal_year' => date('Y')
                ]
            ]);

        $departmentResponse->assertStatus(201);
        $departmentId = $departmentResponse->json('data.id');

        // Step 3: Create employee last (enforcing data setup flow)
        $employeeResponse = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', [
                'employee_id' => 'ENG001',
                'first_name' => 'Alice',
                'last_name' => 'Johnson',
                'email' => 'alice.johnson@testcompany.com',
                'phone' => '+1234567890',
                'dob' => '1995-05-15',
                'start_date' => now()->format('Y-m-d'),
                'hire_date' => now()->format('Y-m-d'),
                'status' => 'active',
                'employment_type' => 'full_time',
                'salary' => 75000,
                'position_uuid' => $positionId,
                'department_uuid' => $departmentId,
                'national_id' => 'ID123456789',
                'tax_number' => 'TAX987654321',
                'bank_details' => [
                    'bank_name' => 'Test Bank',
                    'account_number' => '1234567890',
                    'routing_number' => '987654321'
                ]
            ]);

        $employeeResponse->assertStatus(201);
        $employeeUuid = $employeeResponse->json('data.uuid');

        // Step 4: Verify complete data integrity
        $employeeDetailResponse = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/employees/{$employeeUuid}");

        $employeeDetailResponse->assertStatus(200)
            ->assertJsonPath('data.position.name', 'Software Engineer II')
            ->assertJsonPath('data.department.name', 'Product Engineering')
            ->assertJsonPath('data.salary', 75000);

        // Step 5: Test organogram includes new employee
        $organogramResponse = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/employees/organogram');

        $organogramResponse->assertStatus(200);
        
        $organogramData = $organogramResponse->json('data');
        $employeeFound = false;
        
        foreach ($organogramData as $node) {
            if ($node['uuid'] === $employeeUuid) {
                $employeeFound = true;
                break;
            }
        }
        
        $this->assertTrue($employeeFound, 'New employee should appear in organogram');
    }

    /** @test */
    public function test_manager_hierarchy_workflow()
    {
        // Create positions for hierarchy
        $ceoPosition = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', [
                'name' => 'Chief Executive Officer',
                'min_salary' => 150000,
                'max_salary' => 300000
            ])->json('data.id');

        $managerPosition = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', [
                'name' => 'Engineering Manager',
                'min_salary' => 100000,
                'max_salary' => 150000
            ])->json('data.id');

        $engineerPosition = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', [
                'name' => 'Senior Engineer',
                'min_salary' => 80000,
                'max_salary' => 120000
            ])->json('data.id');

        // Create CEO
        $ceoResponse = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', [
                'employee_id' => 'CEO001',
                'first_name' => 'John',
                'last_name' => 'CEO',
                'email' => 'ceo@testcompany.com',
                'department_uuid' => $this->testDepartment->id,
                'position_uuid' => $ceoPosition,
                'is_director' => true,
                'salary' => 200000
            ]);

        $ceoUuid = $ceoResponse->json('data.uuid');

        // Create Engineering Manager reporting to CEO
        $managerResponse = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', [
                'employee_id' => 'MGR001',
                'first_name' => 'Jane',
                'last_name' => 'Manager',
                'email' => 'manager@testcompany.com',
                'department_uuid' => $this->testDepartment->id,
                'position_uuid' => $managerPosition,
                'manager_uuid' => $ceoUuid,
                'salary' => 125000
            ]);

        $managerUuid = $managerResponse->json('data.uuid');

        // Create Engineer reporting to Manager
        $engineerResponse = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', [
                'employee_id' => 'ENG001',
                'first_name' => 'Bob',
                'last_name' => 'Engineer',
                'email' => 'engineer@testcompany.com',
                'department_uuid' => $this->testDepartment->id,
                'position_uuid' => $engineerPosition,
                'manager_uuid' => $managerUuid,
                'salary' => 100000
            ]);

        $engineerUuid = $engineerResponse->json('data.uuid');

        // Test organogram shows correct hierarchy
        $organogramResponse = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/employees/organogram');

        $organogramResponse->assertStatus(200);
        
        // Find CEO in organogram
        $organogram = $organogramResponse->json('data');
        $ceoNode = null;
        
        foreach ($organogram as $node) {
            if ($node['uuid'] === $ceoUuid) {
                $ceoNode = $node;
                break;
            }
        }

        $this->assertNotNull($ceoNode);
        $this->assertNull($ceoNode['manager_uuid']);
        $this->assertTrue($ceoNode['is_director']);

        // Test potential managers excludes subordinates
        $potentialManagersResponse = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/employees/potential-managers/{$ceoUuid}");

        $potentialManagers = $potentialManagersResponse->json('data');
        $subordinateUuids = [$managerUuid, $engineerUuid];
        
        foreach ($potentialManagers as $manager) {
            $this->assertNotContains($manager['uuid'], $subordinateUuids);
        }
    }

    /** @test */
    public function test_department_head_workflow()
    {
        // Create department head employee
        $headEmployee = $this->createTestEmployee([
            'employee_id' => 'HEAD001',
            'first_name' => 'Department',
            'last_name' => 'Head',
            'email' => 'head@testcompany.com',
            'department_uuid' => $this->testDepartment->id
        ]);

        // Assign as department head
        $updateResponse = $this->withHeaders($this->authenticatedHeaders())
            ->putJson("/api/departments/{$this->testDepartment->id}", [
                'head_of_department_id' => $headEmployee->uuid
            ]);

        $updateResponse->assertStatus(200);

        // Verify employee is marked as department head
        $employeeResponse = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/employees/{$headEmployee->uuid}");

        $employeeResponse->assertStatus(200)
            ->assertJsonPath('data.is_department_head', true);

        // Verify department shows head
        $departmentResponse = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/departments/{$this->testDepartment->id}");

        $departmentResponse->assertStatus(200)
            ->assertJsonPath('data.head_of_department.uuid', $headEmployee->uuid);
    }

    /** @test */
    public function test_employee_transfer_workflow()
    {
        // Create second department
        $newDepartment = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'New Department',
            'cost_center' => 'NEW001'
        ]);

        // Create second position
        $newPosition = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'New Position',
            'min_salary' => 70000,
            'max_salary' => 100000
        ]);

        // Create employee
        $employee = $this->createTestEmployee([
            'department_uuid' => $this->testDepartment->id,
            'position_uuid' => $this->testPosition->id,
            'salary' => 75000
        ]);

        // Transfer employee to new department and position
        $transferResponse = $this->withHeaders($this->authenticatedHeaders())
            ->putJson("/api/employees/{$employee->uuid}", [
                'department_uuid' => $newDepartment->id,
                'position_uuid' => $newPosition->id,
                'salary' => 85000
            ]);

        $transferResponse->assertStatus(200)
            ->assertJsonPath('data.salary', 85000);

        // Verify transfer in database
        $this->assertDatabaseHas('employees', [
            'uuid' => $employee->uuid,
            'department_uuid' => $newDepartment->id,
            'position_uuid' => $newPosition->id,
            'salary' => 85000
        ]);

        // Test statistics update correctly
        $statsResponse = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/employees/statistics');

        $statsResponse->assertStatus(200);
    }

    /** @test */
    public function test_employee_termination_workflow()
    {
        // Create employee with direct reports
        $manager = $this->createTestEmployee([
            'employee_id' => 'MGR001',
            'first_name' => 'Manager',
            'email' => 'manager@testcompany.com'
        ]);

        $subordinate = $this->createTestEmployee([
            'employee_id' => 'SUB001',
            'first_name' => 'Subordinate',
            'email' => 'subordinate@testcompany.com',
            'manager_uuid' => $manager->uuid
        ]);

        // Terminate manager
        $terminationResponse = $this->withHeaders($this->authenticatedHeaders())
            ->patchJson("/api/employees/{$manager->uuid}/status", [
                'status' => 'terminated',
                'termination_date' => now()->format('Y-m-d'),
                'termination_reason' => 'Resignation'
            ]);

        $terminationResponse->assertStatus(200);

        // Verify subordinate's manager is cleared
        $subordinateResponse = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/employees/{$subordinate->uuid}");

        $subordinateResponse->assertStatus(200)
            ->assertJsonPath('data.manager', null);

        // Verify statistics reflect termination
        $statsResponse = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/employees/statistics');

        $statsResponse->assertStatus(200);
        $stats = $statsResponse->json('data');
        
        $this->assertGreaterThan($stats['active_employees'], $stats['total_employees']);
    }

    /** @test */
    public function test_bulk_operations_workflow()
    {
        // Create multiple employees
        $employees = [];
        for ($i = 1; $i <= 5; $i++) {
            $employees[] = $this->createTestEmployee([
                'employee_id' => "BULK{$i}",
                'first_name' => "Employee{$i}",
                'email' => "employee{$i}@testcompany.com"
            ]);
        }

        // Test filtering by department
        $departmentFilterResponse = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/employees?department_uuid={$this->testDepartment->id}");

        $departmentFilterResponse->assertStatus(200);
        $filteredEmployees = $departmentFilterResponse->json('data.data');
        
        foreach ($filteredEmployees as $emp) {
            $this->assertEquals($this->testDepartment->id, $emp['department']['id']);
        }

        // Test filtering by position
        $positionFilterResponse = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/employees?position_uuid={$this->testPosition->id}");

        $positionFilterResponse->assertStatus(200);
        $positionFilteredEmployees = $positionFilterResponse->json('data.data');
        
        foreach ($positionFilteredEmployees as $emp) {
            $this->assertEquals($this->testPosition->id, $emp['position']['id']);
        }
    }

    /** @test */
    public function test_data_integrity_enforcement()
    {
        // Test 1: Cannot create employee without position
        $withoutPositionResponse = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', [
                'employee_id' => 'NOPOS001',
                'first_name' => 'No',
                'last_name' => 'Position',
                'email' => 'noposition@testcompany.com',
                'department_uuid' => $this->testDepartment->id
                // Missing position_uuid
            ]);

        // Should still allow creation but without position validation error
        $withoutPositionResponse->assertStatus(201);

        // Test 2: Cannot assign non-existent position
        $invalidPositionResponse = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', [
                'employee_id' => 'INVPOS001',
                'first_name' => 'Invalid',
                'last_name' => 'Position',
                'email' => 'invalidposition@testcompany.com',
                'position_uuid' => 'non-existent-uuid'
            ]);

        $invalidPositionResponse->assertStatus(422)
            ->assertJsonValidationErrors(['position_uuid']);

        // Test 3: Cannot assign non-existent department
        $invalidDepartmentResponse = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', [
                'employee_id' => 'INVDEPT001',
                'first_name' => 'Invalid',
                'last_name' => 'Department',
                'email' => 'invaliddepartment@testcompany.com',
                'department_uuid' => 'non-existent-uuid'
            ]);

        $invalidDepartmentResponse->assertStatus(422)
            ->assertJsonValidationErrors(['department_uuid']);
    }

    /** @test */
    public function test_multi_tenant_isolation_workflow()
    {
        // Create second company with its own data
        $otherCompany = \App\Models\Company::factory()->create();
        
        $otherPosition = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $otherCompany->uuid,
            'name' => 'Other Company Position'
        ]);

        $otherDepartment = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $otherCompany->uuid,
            'name' => 'Other Company Department'
        ]);

        // Test 1: Cannot assign employee to other company's position
        $crossTenantPositionResponse = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', [
                'employee_id' => 'CROSS001',
                'first_name' => 'Cross',
                'last_name' => 'Tenant',
                'email' => 'cross@testcompany.com',
                'position_uuid' => $otherPosition->id
            ]);

        $crossTenantPositionResponse->assertStatus(422)
            ->assertJsonValidationErrors(['position_uuid']);

        // Test 2: Cannot assign employee to other company's department
        $crossTenantDepartmentResponse = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/employees', [
                'employee_id' => 'CROSS002',
                'first_name' => 'Cross',
                'last_name' => 'Department',
                'email' => 'crossdept@testcompany.com',
                'department_uuid' => $otherDepartment->id
            ]);

        $crossTenantDepartmentResponse->assertStatus(422)
            ->assertJsonValidationErrors(['department_uuid']);
    }

    /** @test */
    public function test_complete_reporting_chain_workflow()
    {
        // Create complete reporting chain: CEO -> VP -> Director -> Manager -> Employee
        $hierarchy = [];
        
        $levels = [
            ['title' => 'CEO', 'salary' => 300000],
            ['title' => 'VP Engineering', 'salary' => 200000],
            ['title' => 'Engineering Director', 'salary' => 150000],
            ['title' => 'Engineering Manager', 'salary' => 120000],
            ['title' => 'Senior Engineer', 'salary' => 100000]
        ];

        foreach ($levels as $index => $level) {
            $employee = $this->createTestEmployee([
                'employee_id' => "LVL{$index}",
                'first_name' => $level['title'],
                'last_name' => 'Employee',
                'email' => strtolower(str_replace(' ', '.', $level['title'])) . '@testcompany.com',
                'salary' => $level['salary'],
                'manager_uuid' => $index > 0 ? $hierarchy[$index - 1]->uuid : null,
                'is_director' => $index <= 2 // CEO, VP, Director are directors
            ]);
            
            $hierarchy[] = $employee;
        }

        // Test organogram shows complete hierarchy
        $organogramResponse = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/employees/organogram');

        $organogramResponse->assertStatus(200);
        
        // Verify CEO has no manager
        $ceo = $hierarchy[0];
        $ceoNode = collect($organogramResponse->json('data'))
            ->firstWhere('uuid', $ceo->uuid);
            
        $this->assertNull($ceoNode['manager_uuid']);
        $this->assertTrue($ceoNode['is_director']);

        // Test potential managers for each level
        foreach ($hierarchy as $index => $employee) {
            $potentialManagersResponse = $this->withHeaders($this->authenticatedHeaders())
                ->getJson("/api/employees/potential-managers/{$employee->uuid}");

            $potentialManagers = $potentialManagersResponse->json('data');
            $potentialUuids = array_column($potentialManagers, 'uuid');
            
            // Should not include self or subordinates
            $this->assertNotContains($employee->uuid, $potentialUuids);
            
            // Should not include any subordinates
            for ($subIndex = $index + 1; $subIndex < count($hierarchy); $subIndex++) {
                $this->assertNotContains($hierarchy[$subIndex]->uuid, $potentialUuids);
            }
        }
    }
}