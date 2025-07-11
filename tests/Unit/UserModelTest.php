<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Company;
use PHPUnit\Framework\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_hca()
    {
        $user = new User(['role' => 'holding_company_admin']);
        $this->assertTrue($user->isHoldingCompanyAdmin());

        $user = new User(['role' => 'admin']);
        $this->assertFalse($user->isHoldingCompanyAdmin());
    }

    public function test_user_has_active_trial()
    {
        // User with future trial expiry
        $user = new User(['trial_expires_at' => now()->addWeek()]);
        $this->assertTrue($user->hasActiveTrial());

        // User with past trial expiry
        $user = new User(['trial_expires_at' => now()->subWeek()]);
        $this->assertFalse($user->hasActiveTrial());

        // User with no trial
        $user = new User(['trial_expires_at' => null]);
        $this->assertFalse($user->hasActiveTrial());
    }

    public function test_hca_permissions()
    {
        $hcaUser = new User(['role' => 'holding_company_admin']);
        $permissions = $hcaUser->getPermissions();

        $expectedPermissions = [
            'manage_companies',
            'create_companies',
            'view_all_companies',
            'manage_default_company',
            'manage_trial',
        ];

        foreach ($expectedPermissions as $permission) {
            $this->assertContains($permission, $permissions);
        }
    }

    public function test_admin_permissions()
    {
        $adminUser = new User(['role' => 'admin']);
        $permissions = $adminUser->getPermissions();

        $expectedPermissions = [
            'manage_employees',
            'manage_payroll',
            'view_reports',
            'manage_departments',
            'manage_settings',
        ];

        foreach ($expectedPermissions as $permission) {
            $this->assertContains($permission, $permissions);
        }
    }

    public function test_manager_permissions()
    {
        $managerUser = new User(['role' => 'manager']);
        $permissions = $managerUser->getPermissions();

        $expectedPermissions = [
            'view_employees',
            'manage_team',
            'view_payroll',
            'create_reports',
        ];

        foreach ($expectedPermissions as $permission) {
            $this->assertContains($permission, $permissions);
        }
    }

    public function test_hr_permissions()
    {
        $hrUser = new User(['role' => 'hr']);
        $permissions = $hrUser->getPermissions();

        $expectedPermissions = [
            'manage_employees',
            'view_payroll',
            'manage_recruitment',
            'manage_performance',
        ];

        foreach ($expectedPermissions as $permission) {
            $this->assertContains($permission, $permissions);
        }
    }

    public function test_employee_permissions()
    {
        $employeeUser = new User(['role' => 'employee']);
        $permissions = $employeeUser->getPermissions();

        $expectedPermissions = [
            'view_profile',
            'update_profile',
            'view_payslips',
            'request_leave',
        ];

        foreach ($expectedPermissions as $permission) {
            $this->assertContains($permission, $permissions);
        }
    }

    public function test_unknown_role_returns_empty_permissions()
    {
        $user = new User(['role' => 'unknown_role']);
        $permissions = $user->getPermissions();

        $this->assertEmpty($permissions);
    }

    public function test_user_can_have_companies_relationship()
    {
        // This would require database setup in a proper test environment
        // For now, just test that the relationship method exists
        $user = new User();
        $this->assertTrue(method_exists($user, 'companies'));
    }

    public function test_user_can_have_default_company_relationship()
    {
        // This would require database setup in a proper test environment
        // For now, just test that the relationship method exists
        $user = new User();
        $this->assertTrue(method_exists($user, 'defaultCompany'));
    }

    public function test_user_fillable_attributes()
    {
        $user = new User();
        $fillable = $user->getFillable();

        $expectedFillable = [
            'name',
            'email',
            'password',
            'role',
            'trial_expires_at',
            'default_company_id',
        ];

        foreach ($expectedFillable as $attribute) {
            $this->assertContains($attribute, $fillable);
        }
    }

    public function test_user_hidden_attributes()
    {
        $user = new User();
        $hidden = $user->getHidden();

        $expectedHidden = [
            'password',
            'remember_token',
        ];

        foreach ($expectedHidden as $attribute) {
            $this->assertContains($attribute, $hidden);
        }
    }

    public function test_user_casts()
    {
        $user = new User();
        $casts = $user->getCasts();

        $this->assertEquals('datetime', $casts['email_verified_at']);
        $this->assertEquals('datetime', $casts['trial_expires_at']);
        $this->assertEquals('hashed', $casts['password']);
    }
}