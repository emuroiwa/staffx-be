<?php

namespace Tests\Unit\Auth;

use App\Models\User;
use App\Repositories\Auth\AuthRepository;
use App\Services\Auth\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Mockery;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AuthService $authService;
    protected $authRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        
        $this->authRepository = Mockery::mock(AuthRepository::class);
        $this->authService = new AuthService($this->authRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_register_user()
    {
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'company' => 'Example Corp',
            'password' => 'password123',
        ];

        $expectedUser = new User([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'company' => 'Example Corp',
        ]);
        $expectedUser->id = 1;

        $this->authRepository
            ->shouldReceive('createUser')
            ->once()
            ->with([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'company' => 'Example Corp',
                'password' => 'password123',
            ])
            ->andReturn($expectedUser);

        $result = $this->authService->register($userData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals($expectedUser, $result['user']);
        $this->assertIsString($result['token']);
    }

    public function test_can_login_user_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->authRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->with('john@example.com')
            ->andReturn($user);

        $this->authRepository
            ->shouldReceive('verifyPassword')
            ->once()
            ->with($user, 'password123')
            ->andReturn(true);

        $result = $this->authService->login('john@example.com', 'password123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals($user, $result['user']);
        $this->assertIsString($result['token']);
    }

    public function test_login_fails_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->authRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->with('john@example.com')
            ->andReturn($user);

        $this->authRepository
            ->shouldReceive('verifyPassword')
            ->once()
            ->with($user, 'wrongpassword')
            ->andReturn(false);

        $result = $this->authService->login('john@example.com', 'wrongpassword');

        $this->assertNull($result);
    }

    public function test_login_fails_with_nonexistent_user()
    {
        $this->authRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->with('nonexistent@example.com')
            ->andReturn(null);

        $result = $this->authService->login('nonexistent@example.com', 'password123');

        $this->assertNull($result);
    }

    public function test_can_update_profile()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'company' => 'Old Company',
        ]);

        $updateData = [
            'name' => 'Jane Smith',
            'company' => 'New Company',
        ];

        $expectedUser = clone $user;
        $expectedUser->name = 'Jane Smith';
        $expectedUser->company = 'New Company';

        $this->authRepository
            ->shouldReceive('updateUser')
            ->once()
            ->with($user, $updateData)
            ->andReturn($expectedUser);

        $result = $this->authService->updateProfile($user, $updateData);

        $this->assertEquals($expectedUser, $result);
    }

    public function test_can_send_password_reset_link()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
        ]);

        $this->authRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->with('john@example.com')
            ->andReturn($user);

        $result = $this->authService->sendPasswordResetLink('john@example.com');

        $this->assertTrue($result);
    }

    public function test_password_reset_link_fails_for_nonexistent_user()
    {
        $this->authRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->with('nonexistent@example.com')
            ->andReturn(null);

        $result = $this->authService->sendPasswordResetLink('nonexistent@example.com');

        $this->assertFalse($result);
    }
}