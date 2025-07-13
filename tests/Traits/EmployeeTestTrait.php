<?php

namespace Tests\Traits;

use App\Models\User;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Department;
use App\Models\Currency;
use Illuminate\Support\Facades\Hash;

trait EmployeeTestTrait
{
    protected $testCompany;
    protected $testUser;
    protected $testPosition;
    protected $testDepartment;
    protected $testCurrency;
    protected $authToken;

    protected function setupTestEnvironment(): void
    {
        // Create test company
        $this->testCompany = Company::factory()->create([
            'name' => 'Test Company ' . uniqid(),
            'slug' => 'test-company-' . uniqid(),
            'subscription_expires_at' => now()->addYear(),
            'is_active' => true,
        ]);

        // Create test user with manage employees permission
        $this->testUser = User::factory()->create([
            'email' => 'admin' . uniqid() . '@testcompany.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'company_uuid' => $this->testCompany->uuid,
            'email_verified_at' => now(),
        ]);

        // Set company creator
        $this->testCompany->update(['created_by_uuid' => $this->testUser->uuid]);

        // Login and get token
        $response = $this->postJson('/api/auth/login', [
            'email' => $this->testUser->email,
            'password' => 'password123',
        ]);

        $this->authToken = $response->json('data.token');
        
        // Create test currency
        $this->testCurrency = Currency::create([
            'uuid' => \Str::uuid(),
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate' => 1.000000,
            'is_active' => true,
        ]);
        
        // Create test position
        $this->testPosition = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Software Engineer',
            'description' => 'Senior software engineer position',
            'min_salary' => 50000,
            'max_salary' => 100000,
            'currency_uuid' => $this->testCurrency->uuid,
            'is_active' => true,
            'requirements' => [
                'education' => 'Bachelor\'s degree in Computer Science',
                'experience' => '3+ years in software development',
                'skills' => ['PHP', 'Laravel', 'JavaScript'],
                'certifications' => ['AWS Certified Developer']
            ]
        ]);

        // Create test department
        $this->testDepartment = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'name' => 'Engineering',
            'description' => 'Software development department',
            'cost_center' => 'ENG001',
            'is_active' => true,
            'budget_info' => [
                'allocation' => 500000,
                'currency' => 'USD',
                'fiscal_year' => date('Y')
            ]
        ]);
    }

    protected function authenticatedHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->authToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    protected function createTestEmployee(array $overrides = []): Employee
    {
        $defaults = [
            'uuid' => \Str::uuid(),
            'company_uuid' => $this->testCompany->uuid,
            'employee_id' => 'EMP' . uniqid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe.' . uniqid() . '@testcompany.com',
            'phone' => '+1234567890',
            'address' => '123 Main St, City, State 12345',
            'dob' => '1990-01-01',
            'start_date' => now()->subMonths(6)->format('Y-m-d'),
            'hire_date' => now()->subMonths(6)->format('Y-m-d'),
            'status' => 'active',
            'employment_type' => 'full_time',
            'salary' => 75000,
            'currency_uuid' => $this->testCurrency->uuid,
            'tax_number' => 'TAX123456789',
            'pay_frequency' => 'monthly',
            'national_id' => 'ID123456789',
            'passport_number' => 'P123456789',
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_phone' => '+1234567891',
            'is_director' => false,
            'is_independent_contractor' => false,
            'is_uif_exempt' => false,
            'department_uuid' => $this->testDepartment->id,
            'position_uuid' => $this->testPosition->id,
            'bank_details' => [
                'bank_name' => 'Test Bank',
                'account_number' => '1234567890',
                'routing_number' => '123456789',
                'account_type' => 'checking'
            ],
            'benefits' => [
                'health_insurance' => true,
                'dental_insurance' => true,
                'retirement_plan' => true
            ]
        ];

        $employeeData = array_merge($defaults, $overrides);
        
        return Employee::create($employeeData);
    }

    protected function createManagerHierarchy(): array
    {
        // Create CEO
        $ceo = $this->createTestEmployee([
            'uuid' => \Str::uuid(),
            'employee_id' => 'CEO001',
            'first_name' => 'Chief',
            'last_name' => 'Executive',
            'email' => 'ceo@testcompany.com',
            'is_director' => true,
            'manager_uuid' => null,
        ]);

        // Create Manager reporting to CEO
        $manager = $this->createTestEmployee([
            'uuid' => \Str::uuid(),
            'employee_id' => 'MGR001',
            'first_name' => 'John',
            'last_name' => 'Manager',
            'email' => 'manager@testcompany.com',
            'manager_uuid' => $ceo->uuid,
        ]);

        // Create Employee reporting to Manager
        $employee = $this->createTestEmployee([
            'uuid' => \Str::uuid(),
            'employee_id' => 'EMP001',
            'first_name' => 'Jane',
            'last_name' => 'Employee',
            'email' => 'employee@testcompany.com',
            'manager_uuid' => $manager->uuid,
        ]);

        return [
            'ceo' => $ceo,
            'manager' => $manager,
            'employee' => $employee
        ];
    }

    protected function assertEmployeeJsonStructure(): array
    {
        return [
            'uuid',
            'employee_id',
            'first_name',
            'last_name',
            'display_name',
            'email',
            'phone',
            'address',
            'dob',
            'start_date',
            'hire_date',
            'status',
            'employment_type',
            'salary',
            'formatted_salary',
            'currency_uuid',
            'currency',
            'tax_number',
            'pay_frequency',
            'national_id',
            'passport_number',
            'emergency_contact_name',
            'emergency_contact_phone',
            'is_director',
            'is_independent_contractor',
            'is_uif_exempt',
            'age',
            'years_of_service',
            'is_manager',
            'is_department_head',
            'bank_details',
            'benefits',
            'created_at',
            'updated_at'
        ];
    }

    protected function assertPositionJsonStructure(): array
    {
        return [
            'id',
            'name',
            'description',
            'min_salary',
            'max_salary',
            'salary_range',
            'currency_uuid',
            'is_active',
            'created_at',
            'updated_at'
        ];
    }

    protected function assertDepartmentJsonStructure(): array
    {
        return [
            'id',
            'name',
            'description',
            'cost_center',
            'is_active',
            'created_at',
            'updated_at'
        ];
    }

    protected function cleanupTestData(): void
    {
        Employee::where('company_uuid', $this->testCompany->uuid)->delete();
        Position::where('company_uuid', $this->testCompany->uuid)->delete();
        Department::where('company_uuid', $this->testCompany->uuid)->delete();
        if ($this->testCurrency) {
            $this->testCurrency->delete();
        }
        $this->testUser->delete();
        $this->testCompany->delete();
    }
}