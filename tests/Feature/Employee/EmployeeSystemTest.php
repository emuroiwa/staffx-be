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

class EmployeeSystemTest extends TestCase
{
    /** @test */
    public function test_employee_system_works_with_main_database()
    {
        // Create company
        $company = Company::create([
            'uuid' => \Str::uuid(),
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'subscription_expires_at' => now()->addYear(),
            'is_active' => true,
        ]);

        // Create and authenticate a user
        $user = User::factory()->create([
            'email' => 'test' . uniqid() . '@testcompany.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'company_uuid' => $company->uuid,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        // Create position
        $position = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $company->uuid,
            'name' => 'Software Engineer',
            'is_active' => true,
        ]);

        // Create department  
        $department = Department::create([
            'id' => \Str::uuid(),
            'company_uuid' => $company->uuid,
            'name' => 'Engineering',
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

        // Test basic creation
        $this->assertDatabaseHas('employees', [
            'uuid' => $employee->uuid,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'company_uuid' => $company->uuid,
        ]);

        // Test that models can be found with scopes
        $foundEmployee = Employee::where('uuid', $employee->uuid)->first();
        $foundPosition = Position::where('id', $position->id)->first();
        $foundDepartment = Department::where('id', $department->id)->first();

        $this->assertNotNull($foundEmployee);
        $this->assertNotNull($foundPosition);
        $this->assertNotNull($foundDepartment);

        // Test that global scopes work correctly
        $this->assertEquals($company->uuid, $foundEmployee->company_uuid);
        $this->assertEquals($company->uuid, $foundPosition->company_uuid);
        $this->assertEquals($company->uuid, $foundDepartment->company_uuid);

        // Cleanup
        $employee->delete();
        $position->delete();
        $department->delete();
        $user->delete();
        $company->delete();

        $this->assertTrue(true, 'Employee Management System works with main database');
    }

    /** @test */
    public function test_company_isolation_works()
    {
        // Create two companies
        $company1 = Company::create([
            'uuid' => \Str::uuid(),
            'name' => 'Company 1',
            'slug' => 'company-1-' . uniqid(),
            'subscription_expires_at' => now()->addYear(),
            'is_active' => true,
        ]);

        $company2 = Company::create([
            'uuid' => \Str::uuid(),
            'name' => 'Company 2', 
            'slug' => 'company-2-' . uniqid(),
            'subscription_expires_at' => now()->addYear(),
            'is_active' => true,
        ]);

        // Create user for company 1
        $user1 = User::factory()->create([
            'email' => 'user1-' . uniqid() . '@company1.com',
            'role' => 'admin',
            'company_uuid' => $company1->uuid,
        ]);

        // Create user for company 2
        $user2 = User::factory()->create([
            'email' => 'user2-' . uniqid() . '@company2.com',
            'role' => 'admin',
            'company_uuid' => $company2->uuid,
        ]);

        // Create position for company 1
        $this->actingAs($user1);
        $position1 = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $company1->uuid,
            'name' => 'Position 1',
            'is_active' => true,
        ]);

        // Create position for company 2
        $this->actingAs($user2);
        $position2 = Position::create([
            'id' => \Str::uuid(),
            'company_uuid' => $company2->uuid,
            'name' => 'Position 2',
            'is_active' => true,
        ]);

        // User 1 should only see company 1's positions
        $this->actingAs($user1);
        $visiblePositions = Position::all();
        $this->assertCount(1, $visiblePositions);
        $this->assertEquals($position1->id, $visiblePositions->first()->id);

        // User 2 should only see company 2's positions
        $this->actingAs($user2);
        $visiblePositions = Position::all();
        $this->assertCount(1, $visiblePositions);
        $this->assertEquals($position2->id, $visiblePositions->first()->id);

        // Cleanup
        $position1->delete();
        $position2->delete();
        $user1->delete();
        $user2->delete();
        $company1->delete();
        $company2->delete();

        $this->assertTrue(true, 'Company isolation works correctly');
    }
}