<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use PHPUnit\Framework\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CompanyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_has_active_subscription()
    {
        // Company with future subscription expiry and active status
        $company = new Company([
            'is_active' => true,
            'subscription_expires_at' => now()->addMonth(),
        ]);
        $this->assertTrue($company->hasActiveSubscription());

        // Company with no subscription expiry (unlimited) and active status
        $company = new Company([
            'is_active' => true,
            'subscription_expires_at' => null,
        ]);
        $this->assertTrue($company->hasActiveSubscription());

        // Company with past subscription expiry
        $company = new Company([
            'is_active' => true,
            'subscription_expires_at' => now()->subMonth(),
        ]);
        $this->assertFalse($company->hasActiveSubscription());

        // Inactive company
        $company = new Company([
            'is_active' => false,
            'subscription_expires_at' => now()->addMonth(),
        ]);
        $this->assertFalse($company->hasActiveSubscription());
    }

    public function test_company_can_have_creator_relationship()
    {
        // This would require database setup in a proper test environment
        // For now, just test that the relationship method exists
        $company = new Company();
        $this->assertTrue(method_exists($company, 'creator'));
    }

    public function test_company_can_have_users_relationship()
    {
        // This would require database setup in a proper test environment
        // For now, just test that the relationship method exists
        $company = new Company();
        $this->assertTrue(method_exists($company, 'users'));
    }

    public function test_company_can_have_employees_relationship()
    {
        // This would require database setup in a proper test environment
        // For now, just test that the relationship method exists
        $company = new Company();
        $this->assertTrue(method_exists($company, 'employees'));
    }

    public function test_company_fillable_attributes()
    {
        $company = new Company();
        $fillable = $company->getFillable();

        $expectedFillable = [
            'created_by',
            'name',
            'slug',
            'email',
            'phone',
            'website',
            'tax_id',
            'address',
            'city',
            'state',
            'postal_code',
            'country',
            'is_active',
            'subscription_expires_at',
        ];

        foreach ($expectedFillable as $attribute) {
            $this->assertContains($attribute, $fillable);
        }
    }

    public function test_company_casts()
    {
        $company = new Company();
        $casts = $company->getCasts();

        $this->assertEquals('boolean', $casts['is_active']);
        $this->assertEquals('datetime', $casts['subscription_expires_at']);
    }

    public function test_company_uses_soft_deletes()
    {
        $company = new Company();
        $this->assertTrue(in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($company)));
    }

    public function test_company_slug_generation()
    {
        // Test that the company has a boot method or slug generation logic
        // This would be tested more thoroughly in a feature test with database
        $company = new Company();
        $this->assertTrue(method_exists($company, 'generateSlug') || 
                         method_exists($company, 'setSlugAttribute') ||
                         in_array('Cviebrock\EloquentSluggable\Sluggable', class_uses($company)));
    }

    public function test_company_scope_active()
    {
        $company = new Company();
        $this->assertTrue(method_exists($company, 'scopeActive'));
    }

    public function test_company_scope_owned_by()
    {
        $company = new Company();
        $this->assertTrue(method_exists($company, 'scopeOwnedBy'));
    }

    public function test_company_scope_search()
    {
        $company = new Company();
        $this->assertTrue(method_exists($company, 'scopeSearch'));
    }

    public function test_company_to_array_includes_relationships()
    {
        // This would be more thoroughly tested in integration tests
        // For now, just verify the structure exists
        $company = new Company([
            'name' => 'Test Company',
            'email' => 'test@company.com',
            'is_active' => true,
        ]);

        $array = $company->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('is_active', $array);
    }

    public function test_company_subscription_status_logic()
    {
        // Test various subscription scenarios
        $scenarios = [
            // Active company, no expiry (unlimited subscription)
            [
                'is_active' => true,
                'subscription_expires_at' => null,
                'expected' => true
            ],
            // Active company, future expiry
            [
                'is_active' => true,
                'subscription_expires_at' => now()->addDays(30),
                'expected' => true
            ],
            // Active company, past expiry
            [
                'is_active' => true,
                'subscription_expires_at' => now()->subDays(30),
                'expected' => false
            ],
            // Inactive company, future expiry
            [
                'is_active' => false,
                'subscription_expires_at' => now()->addDays(30),
                'expected' => false
            ],
            // Inactive company, no expiry
            [
                'is_active' => false,
                'subscription_expires_at' => null,
                'expected' => false
            ],
        ];

        foreach ($scenarios as $scenario) {
            $company = new Company([
                'is_active' => $scenario['is_active'],
                'subscription_expires_at' => $scenario['subscription_expires_at'],
            ]);

            $this->assertEquals(
                $scenario['expected'],
                $company->hasActiveSubscription(),
                sprintf(
                    'Failed for scenario: is_active=%s, subscription_expires_at=%s',
                    $scenario['is_active'] ? 'true' : 'false',
                    $scenario['subscription_expires_at'] ? $scenario['subscription_expires_at']->toDateString() : 'null'
                )
            );
        }
    }
}