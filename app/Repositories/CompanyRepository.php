<?php

namespace App\Repositories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CompanyRepository
{
    /**
     * Get paginated companies for HCA user
     */
    public function getPaginatedCompanies(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Company::query()
            ->where('created_by_uuid', $user->uuid)
            ->with(['creator', 'users']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('slug', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Apply active status filter
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Apply subscription status filter
        if (!empty($filters['subscription_status'])) {
            switch ($filters['subscription_status']) {
                case 'active':
                    $query->where(function ($q) {
                        $q->where('is_active', true)
                          ->where(function ($subQ) {
                              $subQ->whereNull('subscription_expires_at')
                                   ->orWhere('subscription_expires_at', '>', now());
                          });
                    });
                    break;
                case 'expired':
                    $query->where('subscription_expires_at', '<', now())
                          ->whereNotNull('subscription_expires_at');
                    break;
                case 'expiring_soon':
                    $query->where('subscription_expires_at', '>', now())
                          ->where('subscription_expires_at', '<=', now()->addDays(30))
                          ->where('is_active', true);
                    break;
            }
        }

        // Default ordering
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get single company by ID for user
     */
    public function getCompanyByUuid(string $uuid, User $user): ?Company
    {
        return Company::where('uuid', $uuid)
            ->where('created_by_uuid', $user->uuid)
            ->with(['creator', 'users', 'employees'])
            ->first();
    }

    /**
     * Create a new company
     */
    public function createCompany(array $data, User $user): Company
    {
        // Generate unique slug
        $slug = $this->generateUniqueSlug($data['name']);
        
        $companyData = array_merge($data, [
            'created_by_uuid' => $user->uuid,
            'slug' => $slug,
        ]);

        return Company::create($companyData);
    }

    /**
     * Update a company
     */
    public function updateCompany(Company $company, array $data): Company
    {
        // Update slug if name changed
        if (isset($data['name']) && $data['name'] !== $company->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $company->uuid);
        }

        $company->update($data);
        return $company->fresh();
    }

    /**
     * Delete a company (soft delete)
     */
    public function deleteCompany(Company $company): bool
    {
        return $company->delete();
    }

    /**
     * Get company statistics
     */
    public function getCompanyStats(Company $company): array
    {
        // Get employees count
        $employeesCount = $company->employees()->count();
        $activeEmployeesCount = $company->employees()->where('is_active', true)->count();
        
        // Get users count
        $usersCount = $company->users()->count();
        $activeUsersCount = $company->users()->whereNotNull('email_verified_at')->count();

        // Get departments count (if departments table exists)
        $departmentsCount = 0;
        if (\Schema::hasTable('departments')) {
            $departmentsCount = $company->departments()->count() ?? 0;
        }

        return [
            'employees_count' => $employeesCount,
            'active_employees_count' => $activeEmployeesCount,
            'users_count' => $usersCount,
            'active_users_count' => $activeUsersCount,
            'departments_count' => $departmentsCount,
            'subscription_status' => $company->hasActiveSubscription() ? 'active' : 'expired',
            'subscription_expires_at' => $company->subscription_expires_at,
            'is_active' => $company->is_active,
            'created_at' => $company->created_at,
        ];
    }

    /**
     * Get companies owned by user (for dropdown/selection)
     */
    public function getCompaniesForUser(User $user): Collection
    {
        return Company::where('created_by_uuid', $user->uuid)
            ->where('is_active', true)
            ->select(['uuid', 'name', 'slug', 'is_active'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get active companies count for user
     */
    public function getActiveCompaniesCount(User $user): int
    {
        return Company::where('created_by_uuid', $user->uuid)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Search companies with filters
     */
    public function searchCompanies(User $user, string $search, array $filters = []): Collection
    {
        $query = Company::where('created_by_uuid', $user->uuid)
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('slug', 'like', '%' . $search . '%');
            });

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->limit(10)->get(['uuid', 'name', 'slug', 'email', 'is_active']);
    }

    /**
     * Check if company exists for user
     */
    public function companyExistsForUser(string $companyUuid, User $user): bool
    {
        return Company::where('uuid', $companyUuid)
            ->where('created_by_uuid', $user->uuid)
            ->exists();
    }

    /**
     * Generate unique slug for company
     */
    private function generateUniqueSlug(string $name, string $excludeUuid = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeUuid)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists
     */
    private function slugExists(string $slug, string $excludeUuid = null): bool
    {
        $query = Company::where('slug', $slug);
        
        if ($excludeUuid) {
            $query->where('uuid', '!=', $excludeUuid);
        }

        return $query->exists();
    }

    /**
     * Get companies with expired subscriptions
     */
    public function getExpiredSubscriptions(): Collection
    {
        return Company::where('subscription_expires_at', '<', now())
            ->whereNotNull('subscription_expires_at')
            ->where('is_active', true)
            ->with(['creator'])
            ->get();
    }

    /**
     * Get companies with subscriptions expiring soon
     */
    public function getExpiringSoon(int $days = 7): Collection
    {
        return Company::where('subscription_expires_at', '>', now())
            ->where('subscription_expires_at', '<=', now()->addDays($days))
            ->where('is_active', true)
            ->with(['creator'])
            ->get();
    }

    /**
     * Bulk update company status
     */
    public function bulkUpdateStatus(array $companyUuids, User $user, bool $isActive): int
    {
        return Company::whereIn('uuid', $companyUuids)
            ->where('created_by_uuid', $user->uuid)
            ->update(['is_active' => $isActive]);
    }

    /**
     * Get company by slug for user
     */
    public function getCompanyBySlug(string $slug, User $user): ?Company
    {
        return Company::where('slug', $slug)
            ->where('created_by_uuid', $user->uuid)
            ->with(['creator', 'users', 'employees'])
            ->first();
    }
}