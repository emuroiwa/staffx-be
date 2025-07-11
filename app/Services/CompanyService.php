<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Repositories\CompanyRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CompanyService
{
    public function __construct(
        private CompanyRepository $companyRepository
    ) {}

    /**
     * Get companies for HCA user with pagination and filters
     */
    public function getCompaniesForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        if (!$user->isHoldingCompanyAdmin()) {
            throw new \Exception('Only Holding Company Admins can access multiple companies');
        }

        return $this->companyRepository->getPaginatedCompanies($user, $filters, $perPage);
    }

    /**
     * Get single company by ID
     */
    public function getCompanyById(int $id, User $user): Company
    {
        $company = $this->companyRepository->getCompanyById($id, $user);

        if (!$company) {
            throw new \Exception('Company not found or you do not have permission to access it');
        }

        return $company;
    }

    /**
     * Create a new company
     */
    public function createCompany(array $data, User $user, bool $skipValidation = false): Company
    {
        if (!$user->isHoldingCompanyAdmin()) {
            throw new \Exception('Only Holding Company Admins can create companies');
        }

        // Skip validation during registration
        if (!$skipValidation) {
            // Check subscription status through user's company
            if (!$user->hasActiveSubscription()) {
                throw new \Exception('Your subscription has expired. Please upgrade to create new companies.');
            }

            // Validate company limit based on subscription
            $this->validateCompanyLimit($user);
        }

        try {
            DB::beginTransaction();

            // Create the company
            $company = $this->companyRepository->createCompany($data, $user);

            // Set as default company if user doesn't have one (skip during registration)
            if (!$skipValidation && !$user->default_company_id) {
                $this->setDefaultCompany($company->id, $user);
            }

            DB::commit();

            Log::info('Company created successfully', [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'company_name' => $company->name,
                'during_registration' => $skipValidation
            ]);

            return $company;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create company', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'data' => $data,
                'during_registration' => $skipValidation
            ]);
            throw $e;
        }
    }

    /**
     * Update a company
     */
    public function updateCompany(int $id, array $data, User $user): Company
    {
        $company = $this->getCompanyById($id, $user);

        try {
            DB::beginTransaction();

            $updatedCompany = $this->companyRepository->updateCompany($company, $data);

            DB::commit();

            Log::info('Company updated successfully', [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'changes' => array_keys($data)
            ]);

            return $updatedCompany;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update company', [
                'company_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete a company
     */
    public function deleteCompany(int $id, User $user): bool
    {
        $company = $this->getCompanyById($id, $user);

        // Check if this is the user's default company
        if ($user->default_company_id === $company->id) {
            // Set another company as default if available
            $otherCompany = $this->companyRepository->getCompaniesForUser($user)
                ->where('id', '!=', $company->id)
                ->first();

            if ($otherCompany) {
                $user->update(['default_company_id' => $otherCompany->id]);
            } else {
                $user->update(['default_company_id' => null]);
            }
        }

        try {
            DB::beginTransaction();

            $result = $this->companyRepository->deleteCompany($company);

            DB::commit();

            Log::info('Company deleted successfully', [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'company_name' => $company->name
            ]);

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete company', [
                'company_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Set default company for user
     */
    public function setDefaultCompany(int $companyId, User $user): Company
    {
        if (!$this->companyRepository->companyExistsForUser($companyId, $user)) {
            throw new \Exception('Company not found or you do not have permission to set it as default');
        }

        $company = $this->companyRepository->getCompanyById($companyId, $user);

        $user->update(['default_company_id' => $companyId]);

        Log::info('Default company set', [
            'company_id' => $companyId,
            'user_id' => $user->id,
            'company_name' => $company->name
        ]);

        return $company;
    }

    /**
     * Get default company for user
     */
    public function getDefaultCompany(User $user): ?Company
    {
        if (!$user->default_company_id) {
            return null;
        }

        return $this->companyRepository->getCompanyById($user->default_company_id, $user);
    }

    /**
     * Get company statistics
     */
    public function getCompanyStats(int $id, User $user): array
    {
        $company = $this->getCompanyById($id, $user);
        return $this->companyRepository->getCompanyStats($company);
    }

    /**
     * Search companies for user
     */
    public function searchCompanies(string $search, User $user, array $filters = []): Collection
    {
        if (!$user->isHoldingCompanyAdmin()) {
            throw new \Exception('Only Holding Company Admins can search companies');
        }

        return $this->companyRepository->searchCompanies($user, $search, $filters);
    }

    /**
     * Get companies for dropdown/selection
     */
    public function getCompaniesForSelection(User $user): Collection
    {
        if (!$user->isHoldingCompanyAdmin()) {
            throw new \Exception('Only Holding Company Admins can access multiple companies');
        }

        return $this->companyRepository->getCompaniesForUser($user);
    }

    /**
     * Bulk update company status
     */
    public function bulkUpdateCompanyStatus(array $companyIds, bool $isActive, User $user): int
    {
        if (!$user->isHoldingCompanyAdmin()) {
            throw new \Exception('Only Holding Company Admins can bulk update companies');
        }

        try {
            $updatedCount = $this->companyRepository->bulkUpdateStatus($companyIds, $user, $isActive);

            Log::info('Bulk company status update', [
                'user_id' => $user->id,
                'company_ids' => $companyIds,
                'is_active' => $isActive,
                'updated_count' => $updatedCount
            ]);

            return $updatedCount;

        } catch (\Exception $e) {
            Log::error('Failed to bulk update company status', [
                'user_id' => $user->id,
                'company_ids' => $companyIds,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get company by slug
     */
    public function getCompanyBySlug(string $slug, User $user): Company
    {
        $company = $this->companyRepository->getCompanyBySlug($slug, $user);

        if (!$company) {
            throw new \Exception('Company not found or you do not have permission to access it');
        }

        return $company;
    }

    /**
     * Check if user has reached company limit
     */
    public function hasReachedCompanyLimit(User $user): bool
    {
        $activeCompaniesCount = $this->companyRepository->getActiveCompaniesCount($user);
        
        // Define limits based on subscription status through user's company
        if (!$user->hasActiveSubscription()) {
            return $activeCompaniesCount >= config('app.trial_company_limit', 3);
        }

        // For subscribed users, you can define different limits or unlimited
        return false; // Unlimited for subscribed users
    }

    /**
     * Get companies with expired subscriptions
     */
    public function getExpiredSubscriptions(): Collection
    {
        return $this->companyRepository->getExpiredSubscriptions();
    }

    /**
     * Get companies with subscriptions expiring soon
     */
    public function getExpiringSoon(int $days = 7): Collection
    {
        return $this->companyRepository->getExpiringSoon($days);
    }

    /**
     * Validate company creation against limits
     */
    private function validateCompanyLimit(User $user): void
    {
        if ($this->hasReachedCompanyLimit($user)) {
            if (!$user->hasActiveSubscription()) {
                $limit = config('app.trial_company_limit', 3);
                throw new \Exception("Trial users are limited to {$limit} companies. Please upgrade to create more companies.");
            }
            
            throw new \Exception('You have reached your company limit. Please contact support to increase your limit.');
        }
    }

    /**
     * Get dashboard statistics for HCA user
     */
    public function getDashboardStats(User $user): array
    {
        if (!$user->isHoldingCompanyAdmin()) {
            throw new \Exception('Only Holding Company Admins can access dashboard statistics');
        }

        $totalCompanies = $this->companyRepository->getActiveCompaniesCount($user) + 
                         Company::where('created_by', $user->id)->where('is_active', false)->count();
        
        $activeCompanies = $this->companyRepository->getActiveCompaniesCount($user);
        $defaultCompany = $this->getDefaultCompany($user);
        
        // Calculate subscription info through user's company
        $subscriptionDaysLeft = 0;
        $subscriptionStatus = 'no_subscription';
        
        if ($user->company && $user->company->subscription_expires_at) {
            $subscriptionDaysLeft = $user->company->getDaysLeftInSubscription();
            $subscriptionStatus = $user->company->getSubscriptionStatus();
        }

        return [
            'total_companies' => $totalCompanies,
            'active_companies' => $activeCompanies,
            'inactive_companies' => $totalCompanies - $activeCompanies,
            'default_company' => $defaultCompany,
            'trial_days_left' => $subscriptionDaysLeft, // Keep for backward compatibility
            'trial_status' => $subscriptionStatus, // Keep for backward compatibility
            'subscription_days_left' => $subscriptionDaysLeft,
            'subscription_status' => $subscriptionStatus,
            'company_limit' => config('app.trial_company_limit', 3),
            'has_reached_limit' => $this->hasReachedCompanyLimit($user),
        ];
    }
}