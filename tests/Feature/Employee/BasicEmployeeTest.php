<?php

namespace Tests\Feature\Employee;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class BasicEmployeeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_basic_employee_creation()
    {
        // Create company
        $company = Company::create([
            'uuid' => \Str::uuid(),
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'subscription_expires_at' => now()->addYear(),
            'is_active' => true,
        ]);

        // Create position
        $position = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $company->uuid,
            'name' => 'Test Position',
            'is_active' => true,
        ]);

        // Create department
        $department = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $company->uuid,
            'name' => 'Test Department',
            'is_active' => true,
        ]);

        // Create employee
        $employee = Employee::create([
            'uuid' => \Str::uuid(),
            'company_uuid' => $company->uuid,
            'employee_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@testcompany.com',
            'department_uuid' => $department->id,
            'position_uuid' => $position->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('employees', [
            'uuid' => $employee->uuid,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
    }

    /** @test */
    public function test_employee_relationships()
    {
        // Create company
        $company = Company::create([
            'uuid' => \Str::uuid(),
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'subscription_expires_at' => now()->addYear(),
            'is_active' => true,
        ]);

        // Create and authenticate a user to satisfy global scopes
        $user = User::factory()->create([
            'email' => 'test' . uniqid() . '@testcompany.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'company_uuid' => $company->uuid,
            'email_verified_at' => now(),
        ]);

        // Authenticate the user
        $this->actingAs($user);

        // Create position
        $position = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $company->uuid,
            'name' => 'Test Position',
            'is_active' => true,
        ]);

        // Create department
        $department = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $company->uuid,
            'name' => 'Test Department',
            'is_active' => true,
        ]);

        // Create employee
        $employee = Employee::create([
            'uuid' => \Str::uuid(),
            'company_uuid' => $company->uuid,
            'employee_id' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@testcompany.com',
            'department_uuid' => $department->id,
            'position_uuid' => $position->id,
            'status' => 'active',
        ]);

        // Test relationships
        $this->assertEquals($company->uuid, $employee->company_uuid);
        $this->assertEquals($department->id, $employee->department_uuid);
        $this->assertEquals($position->id, $employee->position_uuid);
        
        // Enable query logging
        \DB::enableQueryLog();
        
        // Test that we can load the relationships
        $loadedEmployee = Employee::with(['department', 'position', 'company'])->find($employee->uuid);
        
        // Show the queries that were executed
        $queries = \DB::getQueryLog();
        foreach ($queries as $query) {
            dump("SQL: " . $query['query']);
            dump("Bindings: " . json_encode($query['bindings']));
        }
        
        // Debug output
        dump("Department UUID: " . $employee->department_uuid);
        dump("Position UUID: " . $employee->position_uuid);
        dump("Company UUID: " . $employee->company_uuid);
        
        // Check if records exist
        $deptExists = Department::where('id', $employee->department_uuid)->exists();
        $posExists = Position::where('id', $employee->position_uuid)->exists();
        $compExists = Company::where('uuid', $employee->company_uuid)->exists();
        
        dump("Department exists: " . ($deptExists ? 'Yes' : 'No'));
        dump("Position exists: " . ($posExists ? 'Yes' : 'No'));
        dump("Company exists: " . ($compExists ? 'Yes' : 'No'));
        
        // Try to load manually
        $manualDept = Department::find($employee->department_uuid);
        $manualPos = Position::find($employee->position_uuid);
        
        dump("Manual department loaded: " . ($manualDept ? 'Yes' : 'No'));
        dump("Manual position loaded: " . ($manualPos ? 'Yes' : 'No'));
        
        dump("Department loaded: " . ($loadedEmployee->department ? 'Yes' : 'No'));
        dump("Position loaded: " . ($loadedEmployee->position ? 'Yes' : 'No'));
        dump("Company loaded: " . ($loadedEmployee->company ? 'Yes' : 'No'));
        
        $this->assertNotNull($loadedEmployee->department);
        $this->assertNotNull($loadedEmployee->position);
        $this->assertNotNull($loadedEmployee->company);
        
        $this->assertEquals('Test Department', $loadedEmployee->department->name);
        $this->assertEquals('Test Position', $loadedEmployee->position->name);
        $this->assertEquals('Test Company', $loadedEmployee->company->name);
    }
}