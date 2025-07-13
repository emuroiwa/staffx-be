<?php

namespace App\Repositories;

use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PositionRepository extends BaseRepository
{
    /**
     * Get paginated positions for company with filters.
     */
    public function getPaginatedPositions(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Position::query()
            ->where('company_uuid', $user->company_uuid)
            ->with(['company', 'employees']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Apply active status filter
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Apply salary range filters
        if (!empty($filters['min_salary_from'])) {
            $query->where('min_salary', '>=', $filters['min_salary_from']);
        }

        if (!empty($filters['min_salary_to'])) {
            $query->where('min_salary', '<=', $filters['min_salary_to']);
        }

        if (!empty($filters['max_salary_from'])) {
            $query->where('max_salary', '>=', $filters['max_salary_from']);
        }

        if (!empty($filters['max_salary_to'])) {
            $query->where('max_salary', '<=', $filters['max_salary_to']);
        }

        // Apply currency filter
        if (!empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        // Default ordering
        $orderBy = $filters['order_by'] ?? 'name';
        $orderDirection = $filters['order_direction'] ?? 'asc';
        $query->orderBy($orderBy, $orderDirection);

        return $query->paginate($perPage);
    }

    /**
     * Get position by ID for company.
     */
    public function getPositionById(string $id, User $user): ?Position
    {
        return Position::where('id', $id)
            ->where('company_uuid', $user->company_uuid)
            ->with(['company', 'employees.department'])
            ->first();
    }

    /**
     * Create a new position.
     */
    public function createPosition(array $data, User $user): Position
    {
        // Ensure company UUID is set
        $data['company_uuid'] = $user->company_uuid;
        
        return Position::create($data);
    }

    /**
     * Update a position.
     */
    public function updatePosition(Position $position, array $data): Position
    {
        $position->update($data);
        return $position->fresh(['company', 'employees']);
    }

    /**
     * Delete a position.
     */
    public function deletePosition(Position $position): bool
    {
        return $position->delete();
    }

    /**
     * Get active positions for dropdown/selection.
     */
    public function getActivePositions(User $user): Collection
    {
        return Position::select(['id', 'name', 'min_salary', 'max_salary', 'currency'])
            ->where('company_uuid', $user->company_uuid)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get position statistics.
     */
    public function getPositionStats(User $user): array
    {
        $totalPositions = Position::where('company_uuid', $user->company_uuid)->count();
        $activePositions = Position::where('company_uuid', $user->company_uuid)->where('is_active', true)->count();
        $inactivePositions = Position::where('company_uuid', $user->company_uuid)->where('is_active', false)->count();

        // Get positions with employee counts
        $positionsWithEmployees = Position::where('company_uuid', $user->company_uuid)
            ->withCount(['employees' => function ($query) {
                $query->where('status', 'active');
            }])
            ->where('is_active', true)
            ->orderBy('employees_count', 'desc')
            ->take(10)
            ->get(['id', 'name', 'employees_count']);

        // Get salary statistics
        $salaryStats = Position::where('company_uuid', $user->company_uuid)
            ->selectRaw('
                AVG(min_salary) as avg_min_salary,
                AVG(max_salary) as avg_max_salary,
                MIN(min_salary) as lowest_min_salary,
                MAX(max_salary) as highest_max_salary
            ')
            ->where('is_active', true)
            ->whereNotNull('min_salary')
            ->whereNotNull('max_salary')
            ->first();

        // Get positions without employees (vacant)
        $vacantPositions = Position::where('company_uuid', $user->company_uuid)
            ->whereDoesntHave('employees', function ($query) {
                $query->where('status', 'active');
            })
            ->where('is_active', true)
            ->count();

        return [
            'total_positions' => $totalPositions,
            'active_positions' => $activePositions,
            'inactive_positions' => $inactivePositions,
            'vacant_positions' => $vacantPositions,
            'positions_with_employees' => $positionsWithEmployees,
            'salary_stats' => $salaryStats,
        ];
    }

    /**
     * Search positions.
     */
    public function searchPositions(User $user, string $search, array $filters = []): Collection
    {
        $query = Position::where('company_uuid', $user->company_uuid)
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });

        // Apply additional filters
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        return $query->limit(20)->get(['id', 'name', 'description', 'min_salary', 'max_salary', 'currency', 'is_active']);
    }

    /**
     * Get positions within salary range.
     */
    public function getPositionsInSalaryRange(User $user, float $minSalary, float $maxSalary): Collection
    {
        return Position::where('company_uuid', $user->company_uuid)
            ->where('is_active', true)
            ->where(function ($query) use ($minSalary, $maxSalary) {
                $query->whereBetween('min_salary', [$minSalary, $maxSalary])
                      ->orWhereBetween('max_salary', [$minSalary, $maxSalary])
                      ->orWhere(function ($subQuery) use ($minSalary, $maxSalary) {
                          $subQuery->where('min_salary', '<=', $minSalary)
                                   ->where('max_salary', '>=', $maxSalary);
                      });
            })
            ->orderBy('min_salary')
            ->get();
    }

    /**
     * Check if position exists for company.
     */
    public function positionExistsForCompany(string $positionId, User $user): bool
    {
        return Position::where('company_uuid', $user->company_uuid)
            ->where('id', $positionId)
            ->exists();
    }

    /**
     * Get positions that can be safely deleted (no active employees).
     */
    public function getDeletablePositions(User $user): Collection
    {
        return Position::where('company_uuid', $user->company_uuid)
            ->whereDoesntHave('employees', function ($query) {
                $query->where('status', 'active');
            })
            ->get(['id', 'name', 'is_active']);
    }

    /**
     * Bulk update position status.
     */
    public function bulkUpdateStatus(array $positionIds, bool $isActive, User $user): int
    {
        return Position::where('company_uuid', $user->company_uuid)
            ->whereIn('id', $positionIds)
            ->update(['is_active' => $isActive]);
    }

    /**
     * Get positions with requirements matching keywords.
     */
    public function getPositionsByRequirements(User $user, array $keywords): Collection
    {
        $query = Position::where('company_uuid', $user->company_uuid)
            ->where('is_active', true);

        foreach ($keywords as $keyword) {
            $query->orWhereJsonContains('requirements', $keyword);
        }

        return $query->get(['id', 'name', 'requirements']);
    }

    /**
     * Get position hierarchy (by salary levels).
     */
    public function getPositionHierarchy(User $user): Collection
    {
        return Position::where('company_uuid', $user->company_uuid)
            ->where('is_active', true)
            ->whereNotNull('min_salary')
            ->orderBy('min_salary', 'desc')
            ->with(['employees' => function ($query) {
                $query->where('status', 'active')->select(['uuid', 'first_name', 'last_name', 'position_uuid']);
            }])
            ->get(['id', 'name', 'min_salary', 'max_salary', 'currency']);
    }
}