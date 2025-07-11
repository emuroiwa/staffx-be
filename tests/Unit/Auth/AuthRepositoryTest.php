<?php

namespace Tests\Unit\Auth;

use App\Models\User;
use App\Repositories\Auth\AuthRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected AuthRepository $authRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->authRepository = new AuthRepository();
    }

    public function test_can_create_user()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'company' => 'Example Corp',
            'password' => 'password123',
        ];

        $user = $this->authRepository->createUser($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('Example Corp', $user->company);
        $this->assertTrue(Hash::check('password123', $user->password));

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'company' => 'Example Corp',
        ]);
    }

    public function test_can_find_user_by_email()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
        ]);

        $foundUser = $this->authRepository->findByEmail('john@example.com');

        $this->assertInstanceOf(User::class, $foundUser);
        $this->assertEquals($user->id, $foundUser->id);
        $this->assertEquals('john@example.com', $foundUser->email);
    }

    public function test_returns_null_when_user_not_found_by_email()
    {
        $foundUser = $this->authRepository->findByEmail('nonexistent@example.com');

        $this->assertNull($foundUser);
    }

    public function test_can_update_user()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'company' => 'Old Company',
        ]);

        $updateData = [
            'name' => 'Jane Smith',
            'company' => 'New Company',
        ];

        $updatedUser = $this->authRepository->updateUser($user, $updateData);

        $this->assertInstanceOf(User::class, $updatedUser);
        $this->assertEquals('Jane Smith', $updatedUser->name);
        $this->assertEquals('New Company', $updatedUser->company);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Jane Smith',
            'company' => 'New Company',
        ]);
    }

    public function test_can_verify_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $this->assertTrue($this->authRepository->verifyPassword($user, 'password123'));
        $this->assertFalse($this->authRepository->verifyPassword($user, 'wrongpassword'));
    }

    public function test_can_update_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);

        $updatedUser = $this->authRepository->updatePassword($user, 'newpassword123');

        $this->assertInstanceOf(User::class, $updatedUser);
        $this->assertTrue(Hash::check('newpassword123', $updatedUser->password));
        $this->assertFalse(Hash::check('oldpassword', $updatedUser->password));
    }
}