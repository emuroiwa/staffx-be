<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Repositories\DepartmentRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepartmentService
{
    public function __construct(
        protected DepartmentRepository $departmentRepository
    ) {}

    /**
     * Get paginated departments with filters.
     */
    public function getPaginatedDepartments(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->departmentRepository->getPaginatedDepartments($user, $filters, $perPage);
    }

    /**
     * Get department by ID.
     */
    public function getDepartmentById(string $id, User $user): ?Department
    {
        return $this->departmentRepository->getDepartmentById($id, $user);
    }

    /**
     * Create a new department.
     */
    public function createDepartment(array $data, User $user): Department
    {
        try {
            DB::beginTransaction();

            $department = $this->departmentRepository->createDepartment($data, $user);

            Log::info('Department created', [
                'department_id' => $department->id,
                'name' => $department->name,
                'company_uuid' => $user->company_uuid,
                'created_by' => $user->uuid
            ]);

            DB::commit();
            
            return $department->load(['company']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create department', [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_uuid' => $user->uuid
            ]);
            throw $e;
        }
    }

    /**
     * Update a department.
     */
    public function updateDepartment(Department $department, array $data, User $user): Department
    {
        try {
            DB::beginTransaction();

            // Validate head assignment if provided
            if (isset($data['head_of_department_id'])) {
                $this->validateHeadAssignment($department, $data['head_of_department_id'], $user);
            }

            // Check if department has employees before deactivating
            if (isset($data['is_active']) && !$data['is_active']) {
                $this->validateDeactivation($department, $user);
            }

            $updatedDepartment = $this->departmentRepository->updateDepartment($department, $data);

            Log::info('Department updated', [
                'department_id' => $department->id,
                'changes' => array_keys($data),
                'updated_by' => $user->uuid
            ]);

            DB::commit();
            
            return $updatedDepartment;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update department', [
                'department_id' => $department->id,
                'error' => $e->getMessage(),
                'data' => $data,
                'user_uuid' => $user->uuid
            ]);
            throw $e;
        }
    }

    /**
     * Delete a department.
     */
    public function deleteDepartment(Department $department, User $user): bool
    {
        try {
            DB::beginTransaction();

            // Check if department has active employees
            $activeEmployeesCount = $department->employees()->where('status', 'active')->count();
            
            if ($activeEmployeesCount > 0) {
                throw new \Exception("Cannot delete department '{$department->name}' because it has {$activeEmployeesCount} active employee(s). Please reassign or terminate these employees first.");
            }

            $result = $this->departmentRepository->deleteDepartment($department);

            Log::info('Department deleted', [
                'department_id' => $department->id,
                'name' => $department->name,
                'deleted_by' => $user->uuid
            ]);

            DB::commit();
            
            return $result;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete department', [
                'department_id' => $department->id,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid
            ]);
            throw $e;
        }
    }

    /**
     * Get active departments for dropdown.
     */
    public function getActiveDepartments(User $user): Collection
    {
        return $this->departmentRepository->getActiveDepartments($user);
    }

    /**
     * Get department statistics.
     */
    public function getDepartmentStats(User $user): array
    {
        return $this->departmentRepository->getDepartmentStats($user);
    }

    /**
     * Search departments.
     */
    public function searchDepartments(User $user, string $search, array $filters = []): Collection
    {
        return $this->departmentRepository->searchDepartments($user, $search, $filters);
    }

    /**
     * Bulk update department status.
     */
    public function bulkUpdateStatus(array $departmentIds, bool $isActive, User $user): int
    {
        try {
            DB::beginTransaction();

            // If deactivating, check each department for active employees
            if (!$isActive) {
                foreach ($departmentIds as $departmentId) {
                    $department = $this->departmentRepository->getDepartmentById($departmentId, $user);
                    if ($department) {
                        $this->validateDeactivation($department, $user);
                    }
                }
            }

            $updated = $this->departmentRepository->bulkUpdateStatus($departmentIds, $isActive, $user);

            Log::info('Bulk department status update', [
                'department_ids' => $departmentIds,
                'is_active' => $isActive,
                'updated_count' => $updated,
                'updated_by' => $user->uuid
            ]);

            DB::commit();
            
            return $updated;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk update department status', [
                'department_ids' => $departmentIds,
                'is_active' => $isActive,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid
            ]);
            throw $e;
        }
    }

    /**
     * Validate head assignment.
     */
    private function validateHeadAssignment(Department $department, ?string $headEmployeeUuid, User $user): void
    {
        if (!$headEmployeeUuid) {
            return; // Removing head is always valid
        }

        $employee = \App\Models\Employee::where('uuid', $headEmployeeUuid)
            ->where('company_uuid', $user->company_uuid)
            ->first();

        if (!$employee) {
            throw new \Exception('Selected employee does not exist in your company.');
        }

        if ($employee->status !== 'active') {
            throw new \Exception('Department head must be an active employee.');
        }

        if ($employee->department_uuid !== $department->id) {
            throw new \Exception('Department head must be assigned to this department.');
        }
    }

    /**
     * Validate department deactivation.
     */
    private function validateDeactivation(Department $department, User $user): void
    {
        $activeEmployeesCount = $department->employees()->where('status', 'active')->count();
        
        if ($activeEmployeesCount > 0) {
            throw new \Exception("Cannot deactivate department '{$department->name}' because it has {$activeEmployeesCount} active employee(s). Please reassign these employees to other departments first.");
        }
    }
}