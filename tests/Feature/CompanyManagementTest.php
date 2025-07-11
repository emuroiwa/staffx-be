<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $hcaUser;
    private User $regularUser;
    private Company $company1;
    private Company $company2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create HCA user with trial
        $this->hcaUser = User::factory()->create([
            'role' => 'holding_company_admin',
            'trial_expires_at' => now()->addMonth(),
        ]);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'role' => 'admin',
        ]);

        // Create companies owned by HCA
        $this->company1 = Company::factory()->create([
            'created_by' => $this->hcaUser->id,
            'name' => 'Company One',
        ]);

        $this->company2 = Company::factory()->create([
            'created_by' => $this->hcaUser->id,
            'name' => 'Company Two',
        ]);
    }

    public function test_hca_can_view_their_companies()
    {
        $token = JWTAuth::fromUser($this->hcaUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/companies');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'created_by',
                            'is_active',
                            'created_at',
                            'updated_at'
                        ]
                    ],
                    'current_page',
                    'per_page',
                    'total',
                    'last_page'
                ]
            ]);

        $companies = $response->json('data.data');
        $this->assertCount(2, $companies);
        $this->assertEquals('Company One', $companies[0]['name']);
        $this->assertEquals('Company Two', $companies[1]['name']);
    }

    public function test_hca_can_create_company()
    {
        $token = JWTAuth::fromUser($this->hcaUser);

        $companyData = [
            'name' => 'New Test Company',
            'email' => 'test@newcompany.com',
            'phone' => '+1234567890',
            'address' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'Test State',
            'country' => 'Test Country',
            'is_active' => true,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/companies', $companyData);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'created_by',
                    'email',
                    'phone',
                    'address',
                    'city',
                    'state',
                    'country',
                    'is_active',
                    'slug'
                ]
            ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'New Test Company',
            'created_by' => $this->hcaUser->id,
            'email' => 'test@newcompany.com',
        ]);

        // Check that it was set as default company if user had none
        if (!$this->hcaUser->default_company_id) {
            $this->hcaUser->refresh();
            $this->assertNotNull($this->hcaUser->default_company_id);
        }
    }

    public function test_hca_can_update_their_company()
    {
        $token = JWTAuth::fromUser($this->hcaUser);

        $updateData = [
            'name' => 'Updated Company Name',
            'email' => 'updated@company.com',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/companies/{$this->company1->id}", $updateData);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->company1->id,
                    'name' => 'Updated Company Name',
                    'email' => 'updated@company.com',
                ]
            ]);

        $this->assertDatabaseHas('companies', [
            'id' => $this->company1->id,
            'name' => 'Updated Company Name',
            'email' => 'updated@company.com',
        ]);
    }

    public function test_hca_can_delete_their_company()
    {
        $token = JWTAuth::fromUser($this->hcaUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/companies/{$this->company1->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Company deleted successfully'
            ]);

        $this->assertSoftDeleted('companies', [
            'id' => $this->company1->id,
        ]);
    }

    public function test_hca_can_set_default_company()
    {
        $token = JWTAuth::fromUser($this->hcaUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/companies/{$this->company1->id}/set-default");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'default_company' => [
                        'id',
                        'name',
                        'created_by'
                    ]
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->hcaUser->id,
            'default_company_id' => $this->company1->id,
        ]);
    }

    public function test_hca_can_get_default_company()
    {
        // Set default company first
        $this->hcaUser->update(['default_company_id' => $this->company1->id]);

        $token = JWTAuth::fromUser($this->hcaUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/companies/default');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'default_company' => [
                        'id' => $this->company1->id,
                        'name' => $this->company1->name,
                    ]
                ]
            ]);
    }

    public function test_hca_cannot_access_companies_not_owned_by_them()
    {
        // Create company owned by different user
        $otherUser = User::factory()->create(['role' => 'holding_company_admin']);
        $otherCompany = Company::factory()->create(['created_by' => $otherUser->id]);

        $token = JWTAuth::fromUser($this->hcaUser);

        // Try to view other user's company
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/companies/{$otherCompany->id}");

        $response->assertNotFound();

        // Try to update other user's company
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/companies/{$otherCompany->id}", [
            'name' => 'Hacked Name'
        ]);

        $response->assertNotFound();

        // Try to delete other user's company
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/companies/{$otherCompany->id}");

        $response->assertNotFound();
    }

    public function test_non_hca_cannot_access_company_management_endpoints()
    {
        $token = JWTAuth::fromUser($this->regularUser);

        // Test create company
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/companies', [
            'name' => 'Unauthorized Company'
        ]);

        $response->assertForbidden();

        // Test update company
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson("/api/companies/{$this->company1->id}", [
            'name' => 'Unauthorized Update'
        ]);

        $response->assertForbidden();

        // Test delete company
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson("/api/companies/{$this->company1->id}");

        $response->assertForbidden();
    }

    public function test_company_creation_requires_valid_data()
    {
        $token = JWTAuth::fromUser($this->hcaUser);

        // Test missing required field
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/companies', [
            'email' => 'test@company.com'
            // Missing required 'name' field
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Test invalid email format
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/companies', [
            'name' => 'Test Company',
            'email' => 'invalid-email'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_company_filtering_and_search()
    {
        // Create additional companies for testing filters
        Company::factory()->create([
            'created_by' => $this->hcaUser->id,
            'name' => 'Active Search Company',
            'is_active' => true,
        ]);

        Company::factory()->create([
            'created_by' => $this->hcaUser->id,
            'name' => 'Inactive Search Company',
            'is_active' => false,
        ]);

        $token = JWTAuth::fromUser($this->hcaUser);

        // Test search functionality
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/companies?search=Search');

        $response->assertOk();
        $companies = $response->json('data.data');
        $this->assertCount(2, $companies);

        // Test active filter
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/companies?is_active=1');

        $response->assertOk();
        $companies = $response->json('data.data');
        foreach ($companies as $company) {
            $this->assertTrue($company['is_active']);
        }

        // Test inactive filter
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/companies?is_active=0');

        $response->assertOk();
        $companies = $response->json('data.data');
        foreach ($companies as $company) {
            $this->assertFalse($company['is_active']);
        }
    }

    public function test_pagination_works_correctly()
    {
        // Create additional companies to test pagination
        Company::factory()->count(15)->create([
            'created_by' => $this->hcaUser->id,
        ]);

        $token = JWTAuth::fromUser($this->hcaUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/companies?per_page=10');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data',
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'from',
                    'to'
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(10, $data['per_page']);
        $this->assertEquals(17, $data['total']); // 15 new + 2 existing
        $this->assertEquals(2, $data['last_page']);
    }

    public function test_hca_trial_status_affects_permissions()
    {
        // Set trial as expired
        $this->hcaUser->update(['trial_expires_at' => now()->subDay()]);

        $token = JWTAuth::fromUser($this->hcaUser);

        // HCA should still be able to view companies even with expired trial
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/companies');

        $response->assertOk();

        // But creating new companies might be restricted (depending on business logic)
        // This test can be adjusted based on actual trial restrictions implemented
    }

    public function test_set_default_company_requires_ownership()
    {
        // Create company owned by different user
        $otherUser = User::factory()->create(['role' => 'holding_company_admin']);
        $otherCompany = Company::factory()->create(['created_by' => $otherUser->id]);

        $token = JWTAuth::fromUser($this->hcaUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/companies/{$otherCompany->id}/set-default");

        $response->assertNotFound();
    }

    public function test_hca_can_search_companies()
    {
        $token = JWTAuth::fromUser($this->hcaUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/companies/search?q=Company');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'email',
                        'is_active'
                    ]
                ]
            ]);
    }

    public function test_hca_can_get_companies_for_selection()
    {
        $token = JWTAuth::fromUser($this->hcaUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/companies/selection');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'is_active'
                    ]
                ]
            ]);
    }

    public function test_hca_can_bulk_update_company_status()
    {
        $token = JWTAuth::fromUser($this->hcaUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/companies/bulk/status', [
            'company_ids' => [$this->company1->id, $this->company2->id],
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'updated_count',
                    'is_active'
                ]
            ]);

        $this->assertDatabaseHas('companies', [
            'id' => $this->company1->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('companies', [
            'id' => $this->company2->id,
            'is_active' => false,
        ]);
    }

    public function test_hca_can_get_dashboard_stats()
    {
        $token = JWTAuth::fromUser($this->hcaUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/companies/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_companies',
                    'active_companies',
                    'inactive_companies',
                    'default_company',
                    'trial_days_left',
                    'trial_status',
                    'company_limit',
                    'has_reached_limit'
                ]
            ]);
    }

    public function test_hca_cannot_create_companies_beyond_trial_limit()
    {
        // Create companies up to the limit
        $limit = config('app.trial_company_limit', 3);
        
        // We already have 2 companies, create one more to reach limit
        Company::factory()->create(['created_by' => $this->hcaUser->id]);
        
        $token = JWTAuth::fromUser($this->hcaUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/companies', [
            'name' => 'Exceeds Limit Company',
            'email' => 'exceed@company.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'success' => false
            ]);
    }

    public function test_hca_can_get_company_by_slug()
    {
        $token = JWTAuth::fromUser($this->hcaUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/companies/slug/{$this->company1->slug}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'created_by'
                ]
            ]);
    }

    public function test_registration_creates_company_and_sets_as_default()
    {
        $userData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@newcompany.com',
            'company' => 'Jane\'s Company',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertCreated();

        // Check user was created
        $user = User::where('email', 'jane@newcompany.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('holding_company_admin', $user->role);
        $this->assertNotNull($user->trial_expires_at);

        // Check company was created
        $company = Company::where('name', 'Jane\'s Company')->first();
        $this->assertNotNull($company);
        $this->assertEquals($user->id, $company->created_by);
        $this->assertTrue($company->is_active);

        // Check company was set as default
        $this->assertEquals($company->id, $user->default_company_id);

        // Check response structure includes both user and company
        $response->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'role', 'default_company_id'],
                'company' => ['id', 'name', 'slug', 'created_by'],
                'token'
            ]
        ]);
    }
}