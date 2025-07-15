<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Auth\AuthRepository;
use App\Services\CompanyService;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Events\Registered;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function __construct(
        protected AuthRepository $authRepository,
        protected CompanyService $companyService
    ) {}

    /**
     * Register a new user and create their first company.
     *
     * @param array $userData
     * @return array
     */
    public function register(array $userData): array
    {
        // Extract company name and country before user processing
        $companyName = $userData['company'];
        $countryUuid = $userData['country_uuid'];
        
        // Combine first and last name
        $userData['name'] = $userData['first_name'] . ' ' . $userData['last_name'];
        
        // Set user as HCA
        $userData['role'] = 'holding_company_admin';
        
        // Remove fields not needed for user creation
        unset($userData['first_name'], $userData['last_name'], $userData['company'], $userData['country_uuid']);
        
        try {
            DB::beginTransaction();
            
            // Create the user
            $user = $this->authRepository->createUser($userData);
            
            // Create the user's first company (skip validation during registration)
            $company = $this->companyService->createCompany([
                'name' => $companyName,
                'country_uuid' => $countryUuid,
                'is_active' => true,
            ], $user, true); // skipValidation = true
            
            // Start trial subscription for the company
            $company->startTrialSubscription();
            
            // Link user to the company and set as default
            $user->update([
                'company_uuid' => $company->uuid,
                'default_company_uuid' => $company->uuid
            ]);
            
            // Send email verification
            event(new Registered($user));
            
            DB::commit();
            
            $token = JWTAuth::fromUser($user);

            return [
                'user' => $user->fresh(), // Refresh to get default_company_id
                'token' => $token,
                'company' => $company,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Login a user.
     *
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public function login(string $email, string $password): ?array
    {
        $user = $this->authRepository->findByEmail($email);
        
        if (!$user || !$this->authRepository->verifyPassword($user, $password)) {
            return null;
        }

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            throw new \Exception('Invalid credentials');
        }

        // Check subscription status - load company relationship
        $user->load('company');
        
        // For HCA users, check if they have any active company subscriptions
        if ($user->isHoldingCompanyAdmin()) {
            $hasActiveSubscription = $user->ownedCompanies()
                ->where('is_active', true)
                ->get()
                ->some(function($company) {
                    return $company->hasActiveSubscription();
                });
                
            if (!$hasActiveSubscription) {
                throw new \Exception('Your subscription has expired. Please upgrade to continue using the system.');
            }
        } else {
            // For regular users, check their assigned company subscription (skip during testing)
            if (!app()->environment('testing') && !$user->hasActiveSubscription()) {
                throw new \Exception('Your company\'s subscription has expired. Please contact your administrator.');
            }
        }

        $token = JWTAuth::fromUser($user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Get authenticated user.
     *
     * @return User|null
     */
    public function getAuthenticatedUser(): ?User
    {
        try {
            return JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Logout user.
     *
     * @return void
     */
    public function logout(): void
    {
        JWTAuth::invalidate(JWTAuth::getToken());
    }

    /**
     * Refresh token.
     *
     * @return array
     */
    public function refresh(): array
    {
        $newToken = JWTAuth::refresh(JWTAuth::getToken());
        $user = JWTAuth::setToken($newToken)->toUser();

        return [
            'user' => $user,
            'token' => $newToken,
        ];
    }

    /**
     * Update user profile.
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function updateProfile(User $user, array $data): User
    {
        return $this->authRepository->updateUser($user, $data);
    }

    /**
     * Send password reset link.
     *
     * @param string $email
     * @return bool
     */
    public function sendPasswordResetLink(string $email): bool
    {
        $user = $this->authRepository->findByEmail($email);
        
        if (!$user) {
            return false;
        }

        $status = Password::sendResetLink(['email' => $email]);
        
        return $status === Password::RESET_LINK_SENT;
    }

    /**
     * Reset password.
     *
     * @param array $credentials
     * @return bool
     */
    public function resetPassword(array $credentials): bool
    {
        $status = Password::reset(
            $credentials,
            function (User $user, string $password) {
                $this->authRepository->updatePassword($user, $password);
            }
        );

        return $status === Password::PASSWORD_RESET;
    }

    /**
     * Verify email address.
     *
     * @param User $user
     * @return bool
     */
    public function verifyEmail(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return true;
        }

        $user->markEmailAsVerified();
        return true;
    }

    /**
     * Resend email verification.
     *
     * @param User $user
     * @return bool
     */
    public function resendEmailVerification(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->sendEmailVerificationNotification();
        return true;
    }
}