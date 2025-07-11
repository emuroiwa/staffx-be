<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_user_cannot_login_with_nonexistent_email()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_login_validation_errors()
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_requires_valid_email_format()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        $token = auth()->login($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout successful',
            ]);
    }

    public function test_unauthenticated_user_cannot_logout()
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_profile()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $token = auth()->login($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_get_profile()
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_refresh_token()
    {
        $user = User::factory()->create();
        $token = auth()->login($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Token refreshed successfully',
            ]);
    }

    public function test_hca_cannot_login_with_expired_subscription()
    {
        // Create company with expired subscription
        $company = Company::factory()->create([
            'subscription_expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        // Create HCA user linked to expired company
        $user = User::factory()->create([
            'email' => 'hca@example.com',
            'password' => Hash::make('password123'),
            'role' => 'holding_company_admin',
            'company_id' => $company->id,
            'default_company_id' => $company->id,
            'email_verified_at' => now(),
        ]);

        // Set user as creator of the company
        $company->update(['created_by' => $user->id]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'hca@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'subscription_expired' => true,
            ])
            ->assertJsonFragment([
                'message' => 'Your subscription has expired. Please upgrade to continue using the system.',
            ]);
    }

    public function test_hca_can_login_with_active_subscription()
    {
        // Create company with active subscription
        $company = Company::factory()->create([
            'subscription_expires_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        // Create HCA user linked to active company
        $user = User::factory()->create([
            'email' => 'hca@example.com',
            'password' => Hash::make('password123'),
            'role' => 'holding_company_admin',
            'company_id' => $company->id,
            'default_company_id' => $company->id,
            'email_verified_at' => now(),
        ]);

        // Set user as creator of the company
        $company->update(['created_by' => $user->id]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'hca@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ]);
    }

    public function test_regular_user_cannot_login_with_expired_company_subscription()
    {
        // Create company with expired subscription
        $company = Company::factory()->create([
            'subscription_expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        // Create regular user linked to expired company
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => Hash::make('password123'),
            'role' => 'employee',
            'company_id' => $company->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'employee@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'subscription_expired' => true,
            ])
            ->assertJsonFragment([
                'message' => 'Your company\'s subscription has expired. Please contact your administrator.',
            ]);
    }

    public function test_regular_user_can_login_with_active_company_subscription()
    {
        // Create company with active subscription
        $company = Company::factory()->create([
            'subscription_expires_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        // Create regular user linked to active company
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => Hash::make('password123'),
            'role' => 'employee',
            'company_id' => $company->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'employee@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ]);
    }

    public function test_hca_can_login_if_any_owned_company_has_active_subscription()
    {
        // Create HCA user
        $user = User::factory()->create([
            'email' => 'hca@example.com',
            'password' => Hash::make('password123'),
            'role' => 'holding_company_admin',
            'email_verified_at' => now(),
        ]);

        // Create expired company
        $expiredCompany = Company::factory()->create([
            'created_by' => $user->id,
            'subscription_expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        // Create active company
        $activeCompany = Company::factory()->create([
            'created_by' => $user->id,
            'subscription_expires_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        // Link user to expired company (their current assignment)
        $user->update([
            'company_id' => $expiredCompany->id,
            'default_company_id' => $expiredCompany->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'hca@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ]);
    }

    public function test_user_without_company_cannot_login()
    {
        // Create user without company assignment
        $user = User::factory()->create([
            'email' => 'orphan@example.com',
            'password' => Hash::make('password123'),
            'role' => 'employee',
            'company_id' => null,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'orphan@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'subscription_expired' => true,
            ]);
    }
}