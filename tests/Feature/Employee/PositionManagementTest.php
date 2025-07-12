<?php

namespace Tests\Feature\Employee;

use Tests\TestCase;
use Tests\Traits\EmployeeTestTrait;
use App\Models\Position;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class PositionManagementTest extends TestCase
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
    public function test_can_list_positions_with_pagination()
    {
        // Create multiple positions
        for ($i = 1; $i <= 12; $i++) {
            Position::create([
                'id' => \Str::uuid(),
                'company_uuid' => $this->testCompany->uuid,
                'name' => "Position {$i}",
                'description' => "Description for position {$i}",
                'min_salary' => 40000 + ($i * 5000),
                'max_salary' => 60000 + ($i * 5000),
                'currency' => 'USD',
                'is_active' => true
            ]);
        }

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/positions?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => $this->assertPositionJsonStructure()
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
    public function test_can_filter_positions_by_status()
    {
        // Create active position
        $activePosition = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Active Position',
            'is_active' => true
        ]);

        // Create inactive position
        $inactivePosition = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Inactive Position',
            'is_active' => false
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/positions?is_active=1');

        $response->assertStatus(200);
        
        $positions = $response->json('data.data');
        $activePositions = array_filter($positions, fn($pos) => $pos['is_active'] === true);
        
        $this->assertGreaterThan(0, count($activePositions));
    }

    /** @test */
    public function test_can_search_positions_by_name()
    {
        Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Senior Developer',
            'description' => 'Senior software developer',
            'is_active' => true
        ]);

        Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Marketing Manager',
            'description' => 'Marketing team manager',
            'is_active' => true
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/positions?search=Developer');

        $response->assertStatus(200);
        
        $positions = $response->json('data.data');
        $developerPositions = array_filter($positions, fn($pos) => 
            str_contains(strtolower($pos['name']), 'developer')
        );
        
        $this->assertGreaterThan(0, count($developerPositions));
    }

    /** @test */
    public function test_can_create_position_with_valid_data()
    {
        $positionData = [
            'name' => 'Data Scientist',
            'description' => 'Senior data scientist position',
            'min_salary' => 70000,
            'max_salary' => 120000,
            'currency' => 'USD',
            'is_active' => true,
            'requirements' => [
                'education' => 'PhD in Data Science or related field',
                'experience' => '5+ years in data science',
                'skills' => ['Python', 'R', 'Machine Learning', 'SQL'],
                'certifications' => ['AWS Certified Data Analytics']
            ]
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', $positionData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => $this->assertPositionJsonStructure(),
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Data Scientist',
                    'min_salary' => 70000,
                    'max_salary' => 120000,
                    'currency' => 'USD'
                ]
            ]);

        $this->assertDatabaseHas('positions', [
            'name' => 'Data Scientist',
            'company_uuid' => $this->testCompany->uuid,
            'min_salary' => 70000,
            'max_salary' => 120000
        ]);
    }

    /** @test */
    public function test_cannot_create_position_with_duplicate_name()
    {
        Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Unique Position'
        ]);

        $duplicateData = [
            'name' => 'Unique Position',
            'description' => 'Another position with same name'
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', $duplicateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function test_validates_salary_range_on_creation()
    {
        $invalidData = [
            'name' => 'Invalid Salary Position',
            'min_salary' => 100000,
            'max_salary' => 50000 // Max less than min
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', $invalidData);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'max_salary' => ['Maximum salary must be greater than or equal to minimum salary.']
            ]);
    }

    /** @test */
    public function test_validates_unreasonable_salary_range()
    {
        $invalidData = [
            'name' => 'Wide Range Position',
            'min_salary' => 30000,
            'max_salary' => 500000 // More than 10x ratio
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', $invalidData);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'salary_range' => ['The salary range seems unusually wide. Maximum salary should not be more than 10 times the minimum salary.']
            ]);
    }

    /** @test */
    public function test_can_show_position_details()
    {
        $position = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Detail Position',
            'description' => 'Position for detail testing',
            'min_salary' => 60000,
            'max_salary' => 90000,
            'currency' => 'USD',
            'requirements' => [
                'education' => 'Bachelor degree',
                'skills' => ['PHP', 'Laravel']
            ]
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/positions/{$position->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => array_merge($this->assertPositionJsonStructure(), [
                    'requirements',
                    'employees_count',
                    'active_employees_count',
                    'average_salary',
                    'employees',
                    'company'
                ])
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $position->id,
                    'name' => 'Detail Position',
                    'requirements' => [
                        'education' => 'Bachelor degree',
                        'skills' => ['PHP', 'Laravel']
                    ]
                ]
            ]);
    }

    /** @test */
    public function test_can_update_position()
    {
        $position = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Original Position',
            'min_salary' => 50000,
            'max_salary' => 80000
        ]);

        $updateData = [
            'name' => 'Updated Position',
            'min_salary' => 55000,
            'max_salary' => 85000,
            'description' => 'Updated description'
        ];

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->putJson("/api/positions/{$position->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Position',
                    'min_salary' => 55000,
                    'max_salary' => 85000,
                    'description' => 'Updated description'
                ]
            ]);

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'name' => 'Updated Position',
            'min_salary' => 55000
        ]);
    }

    /** @test */
    public function test_can_delete_position_without_employees()
    {
        $position = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Deletable Position'
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->deleteJson("/api/positions/{$position->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Position deleted successfully'
            ]);

        $this->assertDatabaseMissing('positions', [
            'id' => $position->id
        ]);
    }

    /** @test */
    public function test_cannot_delete_position_with_employees()
    {
        $position = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Position With Employees'
        ]);

        // Create employee with this position
        $this->createTestEmployee([
            'position_uuid' => $position->id,
            'status' => 'active'
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->deleteJson("/api/positions/{$position->id}");

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Cannot delete position because it has active employees assigned. Please reassign these employees first.'
            ]);
    }

    /** @test */
    public function test_cannot_deactivate_position_with_active_employees()
    {
        $position = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Position With Active Employees',
            'is_active' => true
        ]);

        // Create active employee with this position
        $this->createTestEmployee([
            'position_uuid' => $position->id,
            'status' => 'active'
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->putJson("/api/positions/{$position->id}", [
                'is_active' => false
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'is_active' => ['Cannot deactivate position because it has 1 active employee(s). Please reassign these employees first.']
            ]);
    }

    /** @test */
    public function test_can_get_position_statistics()
    {
        // Create positions with different salary ranges
        $lowSalaryPosition = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Junior Position',
            'min_salary' => 30000,
            'max_salary' => 50000,
            'is_active' => true
        ]);

        $highSalaryPosition = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Senior Position',
            'min_salary' => 80000,
            'max_salary' => 120000,
            'is_active' => true
        ]);

        $inactivePosition = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Inactive Position',
            'is_active' => false
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/positions/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_positions',
                    'active_positions',
                    'inactive_positions',
                    'average_min_salary',
                    'average_max_salary',
                    'salary_range_stats' => [
                        'lowest_min_salary',
                        'highest_max_salary'
                    ],
                    'positions_with_employees',
                    'vacant_positions'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_positions' => 4, // 3 created + 1 from setup
                    'active_positions' => 3,
                    'inactive_positions' => 1
                ]
            ]);
    }

    /** @test */
    public function test_position_company_isolation()
    {
        // Create another company and position
        $otherCompany = \App\Models\Company::factory()->create();
        $otherPosition = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $otherCompany->uuid,
            'name' => 'Other Company Position'
        ]);

        // Try to access other company's position
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/positions/{$otherPosition->id}");

        $response->assertStatus(404);

        // List should only show current company's positions
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson('/api/positions');

        $response->assertStatus(200);
        
        $positions = $response->json('data.data');
        $otherCompanyPositions = array_filter($positions, fn($pos) => 
            $pos['id'] === $otherPosition->id
        );
        
        $this->assertEmpty($otherCompanyPositions);
    }

    /** @test */
    public function test_validates_required_fields_on_creation()
    {
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function test_validates_currency_format()
    {
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', [
                'name' => 'Test Position',
                'currency' => 'INVALID' // Must be 3 characters
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);
    }

    /** @test */
    public function test_validates_salary_is_numeric()
    {
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', [
                'name' => 'Test Position',
                'min_salary' => 'not-a-number'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min_salary']);
    }

    /** @test */
    public function test_validates_requirements_structure()
    {
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', [
                'name' => 'Test Position',
                'requirements' => [
                    'skills' => ['skill1', 'skill2'], // Valid
                    'education' => 'Valid education', // Valid
                    'certifications' => ['cert1'] // Valid
                ]
            ]);

        $response->assertStatus(201);

        // Test invalid requirements structure
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', [
                'name' => 'Test Position 2',
                'requirements' => [
                    'skills' => 'should-be-array' // Invalid - should be array
                ]
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['requirements.skills']);
    }

    /** @test */
    public function test_prepares_default_currency_on_creation()
    {
        $response = $this->withHeaders($this->authenticatedHeaders())
            ->postJson('/api/positions', [
                'name' => 'Position Without Currency'
                // No currency specified
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.currency', 'USD'); // Should default to USD
    }

    /** @test */
    public function test_unauthorized_access_returns_401()
    {
        $response = $this->getJson('/api/positions');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_shows_employees_in_position_detail()
    {
        $position = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Position With Employees'
        ]);

        // Create employees with this position
        $employee1 = $this->createTestEmployee([
            'position_uuid' => $position->id,
            'first_name' => 'Employee1',
            'salary' => 70000
        ]);

        $employee2 = $this->createTestEmployee([
            'position_uuid' => $position->id,
            'first_name' => 'Employee2',
            'salary' => 80000
        ]);

        $response = $this->withHeaders($this->authenticatedHeaders())
            ->getJson("/api/positions/{$position->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.employees')
            ->assertJsonPath('data.employees_count', 2)
            ->assertJsonPath('data.average_salary', 75000);
    }
}