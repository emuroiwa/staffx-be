<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_authenticated_user_can_update_profile()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'company' => 'Old Company',
        ]);
        $token = auth()->login($user);

        $updateData = [
            'name' => 'Jane Smith',
            'company' => 'New Company',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/auth/profile', $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'company',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => [
                        'name' => 'Jane Smith',
                        'email' => 'john@example.com', // Email should not change
                        'company' => 'New Company',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Jane Smith',
            'email' => 'john@example.com',
            'company' => 'New Company',
        ]);
    }

    public function test_unauthenticated_user_cannot_update_profile()
    {
        $response = $this->putJson('/api/auth/profile', [
            'name' => 'Jane Smith',
            'company' => 'New Company',
        ]);

        $response->assertStatus(401);
    }

    public function test_profile_update_validation_errors()
    {
        $user = User::factory()->create();
        $token = auth()->login($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/auth/profile', [
            'name' => '', // Empty name should fail validation
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_user_cannot_update_email_through_profile()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
        ]);
        $token = auth()->login($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/auth/profile', [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com', // Should be ignored
            'company' => 'New Company',
        ]);

        $response->assertStatus(200);

        // Email should remain unchanged
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'email' => 'jane@example.com',
        ]);
    }
}