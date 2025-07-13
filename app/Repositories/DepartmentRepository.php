<?php

namespace App\Repositories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class DepartmentRepository extends BaseRepository
{
    /**
     * Get paginated departments for company with filters.
     */
    public function getPaginatedDepartments(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Department::query()
            ->where('company_uuid', $user->company_uuid)
            ->with(['company', 'employees', 'head']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('cost_center', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Apply active status filter
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Filter by departments with/without heads
        if (isset($filters['has_head'])) {
            if ($filters['has_head']) {
                $query->whereNotNull('head_of_department_id');
            } else {
                $query->whereNull('head_of_department_id');
            }
        }

        // Default ordering
        $orderBy = $filters['order_by'] ?? 'name';
        $orderDirection = $filters['order_direction'] ?? 'asc';
        $query->orderBy($orderBy, $orderDirection);

        return $query->paginate($perPage);
    }

    /**
     * Get department by ID for company.
     */
    public function getDepartmentById(string $id, User $user): ?Department
    {
        return Department::where('id', $id)
            ->where('company_uuid', $user->company_uuid)
            ->with(['company', 'employees.position', 'head'])
            ->first();
    }

    /**
     * Create a new department.
     */
    public function createDepartment(array $data, User $user): Department
    {
        $data['company_uuid'] = $user->company_uuid;
        return Department::create($data);
    }

    /**
     * Update a department.
     */
    public function updateDepartment(Department $department, array $data): Department
    {
        $department->update($data);
        return $department->fresh(['company', 'employees', 'head']);
    }

    /**
     * Delete a department.
     */
    public function deleteDepartment(Department $department): bool
    {
        return $department->delete();
    }

    /**
     * Get active departments for dropdown.
     */
    public function getActiveDepartments(User $user): Collection
    {
        return Department::select(['id', 'name', 'cost_center'])
            ->where('company_uuid', $user->company_uuid)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get department statistics.
     */
    public function getDepartmentStats(User $user): array
    {
        $totalDepartments = Department::where('company_uuid', $user->company_uuid)->count();
        $activeDepartments = Department::where('company_uuid', $user->company_uuid)->where('is_active', true)->count();
        $departmentsWithHeads = Department::where('company_uuid', $user->company_uuid)->whereNotNull('head_of_department_id')->count();

        // Get departments with employee counts
        $departmentsWithEmployees = Department::where('company_uuid', $user->company_uuid)
            ->withCount(['employees' => function ($query) {
                $query->where('status', 'active');
            }])
            ->where('is_active', true)
            ->orderBy('employees_count', 'desc')
            ->get(['id', 'name', 'employees_count']);

        return [
            'total_departments' => $totalDepartments,
            'active_departments' => $activeDepartments,
            'departments_with_heads' => $departmentsWithHeads,
            'departments_with_employees' => $departmentsWithEmployees,
        ];
    }

    /**
     * Search departments.
     */
    public function searchDepartments(User $user, string $search, array $filters = []): Collection
    {
        $query = Department::where('company_uuid', $user->company_uuid)
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->limit(20)->get(['id', 'name', 'description', 'cost_center', 'is_active']);
    }

    /**
     * Bulk update department status.
     */
    public function bulkUpdateStatus(array $departmentIds, bool $isActive, User $user): int
    {
        return Department::where('company_uuid', $user->company_uuid)
            ->whereIn('id', $departmentIds)
            ->update(['is_active' => $isActive]);
    }
}