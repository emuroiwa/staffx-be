<?php

namespace Tests\Feature\Employee;

use Tests\TestCase;
use Tests\Traits\EmployeeTestTrait;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class DepartmentManagementTest extends TestCase
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
    public function test_can_list_departments_with_pagination()
    {
        // Create multiple departments
        for ($i = 1; $i <= 12; $i++) {
            Department::create([
                'id' => \Str::uuid(),
                'company_uuid' => $this->testCompany->uuid,
                'name' => "Department {$i}",
                'description' => "Description for department {$i}",
                'cost_center' => "CC{$i}",
                'is_active' => true
            ]);
        }

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/departments?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => $this->assertDepartmentJsonStructure()
                    ],
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'per_page' => 10,
                    'current_page' => 1,
                    'total' => 13 // 12 created + 1 from setup
                ]
            ]);
    }

    /** @test */
    public function test_can_filter_departments_by_status()
    {
        // Create active department
        $activeDepartment = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Active Department',
            'is_active' => true
        ]);

        // Create inactive department
        $inactiveDepartment = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Inactive Department',
            'is_active' => false
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/departments?is_active=1');

        $response->assertStatus(200);
        
        $departments = $response->json('data.data');
        $activeDepartments = array_filter($departments, fn($dept) => $dept['is_active'] === true);
        
        $this->assertGreaterThan(0, count($activeDepartments));
    }

    /** @test */
    public function test_can_search_departments_by_name()
    {
        Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Human Resources',
            'description' => 'HR department',
            'is_active' => true
        ]);

        Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Finance',
            'description' => 'Finance department',
            'is_active' => true
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/departments?search=Human');

        $response->assertStatus(200);
        
        $departments = $response->json('data.data');
        $hrDepartments = array_filter($departments, fn($dept) => 
            str_contains(strtolower($dept['name']), 'human')
        );
        
        $this->assertGreaterThan(0, count($hrDepartments));
    }

    /** @test */
    public function test_can_create_department_with_valid_data()
    {
        $departmentData = [
            'name' => 'Research & Development',
            'description' => 'Product research and development department',
            'cost_center' => 'RND001',
            'is_active' => true,
            'budget_info' => [
                'allocation' => 750000,
                'currency' => 'USD',
                'fiscal_year' => date('Y')
            ]
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/departments', $departmentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => $this->assertDepartmentJsonStructure(),
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Research & Development',
                    'cost_center' => 'RND001',
                    'is_active' => true
                ]
            ]);

        $this->assertDatabaseHas('departments', [
            'name' => 'Research & Development',
            'company_uuid' => $this->testCompany->uuid,
            'cost_center' => 'RND001'
        ]);
    }

    /** @test */
    public function test_cannot_create_department_with_duplicate_name()
    {
        Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Unique Department'
        ]);

        $duplicateData = [
            'name' => 'Unique Department',
            'description' => 'Another department with same name'
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/departments', $duplicateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function test_validates_budget_allocation()
    {
        $invalidData = [
            'name' => 'Invalid Budget Department',
            'budget_info' => [
                'allocation' => -50000 // Negative budget
            ]
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/departments', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budget_info.allocation']);
    }

    /** @test */
    public function test_validates_fiscal_year_range()
    {
        $invalidData = [
            'name' => 'Invalid Year Department',
            'budget_info' => [
                'fiscal_year' => 1999 // Before allowed range
            ]
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/departments', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budget_info.fiscal_year']);
    }

    /** @test */
    public function test_can_show_department_details()
    {
        $department = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Detail Department',
            'description' => 'Department for detail testing',
            'cost_center' => 'DET001',
            'budget_info' => [
                'allocation' => 300000,
                'currency' => 'USD',
                'fiscal_year' => date('Y')
            ]
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/departments/{$department->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => array_merge($this->assertDepartmentJsonStructure(), [
                    'budget_info',
                    'head_of_department',
                    'statistics' => [
                        'total_employees',
                        'active_employees',
                        'inactive_employees',
                        'average_salary',
                        'total_payroll',
                        'directors_count',
                        'contractors_count'
                    ],
                    'employees',
                    'positions',
                    'company'
                ])
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $department->id,
                    'name' => 'Detail Department',
                    'cost_center' => 'DET001'
                ]
            ]);
    }

    /** @test */
    public function test_can_update_department()
    {
        $department = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Original Department',
            'cost_center' => 'ORIG001'
        ]);

        $updateData = [
            'name' => 'Updated Department',
            'cost_center' => 'UPD001',
            'description' => 'Updated description',
            'budget_info' => [
                'allocation' => 400000,
                'currency' => 'USD'
            ]
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->putJson("/api/departments/{$department->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Department',
                    'cost_center' => 'UPD001',
                    'description' => 'Updated description'
                ]
            ]);

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Updated Department',
            'cost_center' => 'UPD001'
        ]);
    }

    /** @test */
    public function test_can_assign_department_head()
    {
        $department = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Department With Head'
        ]);

        // Create employee in this department
        $employee = $this->createTestEmployee([
            'department_uuid' => $department->id,
            'status' => 'active'
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->putJson("/api/departments/{$department->id}", [
                'head_of_department_id' => $employee->uuid
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'head_of_department_id' => $employee->uuid
        ]);
    }

    /** @test */
    public function test_cannot_assign_head_from_different_department()
    {
        $department1 = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Department 1'
        ]);

        $department2 = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Department 2'
        ]);

        // Create employee in department 2
        $employee = $this->createTestEmployee([
            'department_uuid' => $department2->id,
            'status' => 'active'
        ]);

        // Try to assign as head of department 1
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->putJson("/api/departments/{$department1->id}", [
                'head_of_department_id' => $employee->uuid
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['head_of_department_id']);
    }

    /** @test */
    public function test_can_delete_department_without_employees()
    {
        $department = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Deletable Department'
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->deleteJson("/api/departments/{$department->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Department deleted successfully'
            ]);

        $this->assertDatabaseMissing('departments', [
            'id' => $department->id
        ]);
    }

    /** @test */
    public function test_cannot_delete_department_with_employees()
    {
        $department = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Department With Employees'
        ]);

        // Create employee in this department
        $this->createTestEmployee([
            'department_uuid' => $department->id,
            'status' => 'active'
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->deleteJson("/api/departments/{$department->id}");

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Cannot delete department because it has active employees. Please reassign these employees first.'
            ]);
    }

    /** @test */
    public function test_cannot_deactivate_department_with_active_employees()
    {
        $department = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Department With Active Employees',
            'is_active' => true
        ]);

        // Create active employee in this department
        $this->createTestEmployee([
            'department_uuid' => $department->id,
            'status' => 'active'
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->putJson("/api/departments/{$department->id}", [
                'is_active' => false
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'is_active' => ['Cannot deactivate department because it has 1 active employee(s). Please reassign these employees first.']
            ]);
    }

    /** @test */
    public function test_can_get_department_statistics()
    {
        // Create departments with different characteristics
        $dept1 = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Large Department',
            'is_active' => true,
            'budget_info' => ['allocation' => 500000]
        ]);

        $dept2 = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Small Department',
            'is_active' => true,
            'budget_info' => ['allocation' => 200000]
        ]);

        $inactiveDept = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Inactive Department',
            'is_active' => false
        ]);

        // Create employees in departments
        $this->createTestEmployee(['department_uuid' => $dept1->id, 'salary' => 80000]);
        $this->createTestEmployee(['department_uuid' => $dept1->id, 'salary' => 70000]);
        $this->createTestEmployee(['department_uuid' => $dept2->id, 'salary' => 60000]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/departments/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_departments',
                    'active_departments',
                    'inactive_departments',
                    'departments_with_employees',
                    'vacant_departments',
                    'total_budget_allocation',
                    'average_budget_per_department',
                    'department_sizes' => [
                        'small',
                        'medium',
                        'large'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_departments' => 4, // 3 created + 1 from setup
                    'active_departments' => 3,
                    'inactive_departments' => 1,
                    'departments_with_employees' => 3
                ]
            ]);
    }

    /** @test */
    public function test_department_company_isolation()
    {
        // Create another company and department
        $otherCompany = \App\Models\Company::factory()->create();
        $otherDepartment = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $otherCompany->uuid,
            'name' => 'Other Company Department'
        ]);

        // Try to access other company's department
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/departments/{$otherDepartment->id}");

        $response->assertStatus(404);

        // List should only show current company's departments
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/departments');

        $response->assertStatus(200);
        
        $departments = $response->json('data.data');
        $otherCompanyDepartments = array_filter($departments, fn($dept) => 
            $dept['id'] === $otherDepartment->id
        );
        
        $this->assertEmpty($otherCompanyDepartments);
    }

    /** @test */
    public function test_validates_required_fields_on_creation()
    {
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/departments', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function test_validates_head_of_department_exists()
    {
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/departments', [
                'name' => 'Test Department',
                'head_of_department_id' => 'non-existent-uuid'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['head_of_department_id']);
    }

    /** @test */
    public function test_unauthorized_access_returns_401()
    {
        $response = $this->getJson('/api/departments');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_shows_department_statistics_with_employees()
    {
        $department = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Statistics Department'
        ]);

        // Create employees with different characteristics
        $this->createTestEmployee([
            'department_uuid' => $department->id,
            'salary' => 70000,
            'is_director' => true,
            'status' => 'active'
        ]);

        $this->createTestEmployee([
            'department_uuid' => $department->id,
            'salary' => 60000,
            'is_independent_contractor' => true,
            'status' => 'active'
        ]);

        $this->createTestEmployee([
            'department_uuid' => $department->id,
            'salary' => 80000,
            'status' => 'inactive'
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/departments/{$department->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.statistics.total_employees', 3)
            ->assertJsonPath('data.statistics.active_employees', 2)
            ->assertJsonPath('data.statistics.inactive_employees', 1)
            ->assertJsonPath('data.statistics.directors_count', 1)
            ->assertJsonPath('data.statistics.contractors_count', 1)
            ->assertJsonPath('data.statistics.average_salary', 65000)
            ->assertJsonPath('data.statistics.total_payroll', 130000);
    }

    /** @test */
    public function test_shows_positions_used_in_department()
    {
        $department = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Multi-Position Department'
        ]);

        // Create employees with different positions
        $this->createTestEmployee([
            'department_uuid' => $department->id,
            'position_uuid' => $this->testPosition->id
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/departments/{$department->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'positions' => [
                        '*' => [
                            'id',
                            'name',
                            'employees_count'
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function test_validates_budget_currency_format()
    {
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/departments', [
                'name' => 'Test Department',
                'budget_info' => [
                    'currency' => 'INVALID' // Should be 3 characters
                ]
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budget_info.currency']);
    }
}