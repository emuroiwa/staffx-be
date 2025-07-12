<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Repositories\EmployeeRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeService
{
    public function __construct(
        protected EmployeeRepository $employeeRepository
    ) {}

    /**
     * Get paginated employees with filters.
     */
    public function getPaginatedEmployees(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->employeeRepository->getPaginatedEmployees($user, $filters, $perPage);
    }

    /**
     * Get employee by UUID.
     */
    public function getEmployeeByUuid(string $uuid, User $user): ?Employee
    {
        return $this->employeeRepository->getEmployeeByUuid($uuid, $user);
    }

    /**
     * Create a new employee with validation and business logic.
     */
    public function createEmployee(array $data, User $user): Employee
    {
        try {
            DB::beginTransaction();

            // Validate prerequisites
            $this->validatePrerequisites($user);

            // Create the employee
            $employee = $this->employeeRepository->createEmployee($data, $user);

            // Log the action
            Log::info('Employee created', [
                'employee_uuid' => $employee->uuid,
                'company_uuid' => $user->company_uuid,
                'created_by' => $user->uuid
            ]);

            DB::commit();
            
            return $employee->load(['department', 'position', 'manager', 'user']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create employee', [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_uuid' => $user->uuid
            ]);
            throw $e;
        }
    }

    /**
     * Update an employee.
     */
    public function updateEmployee(Employee $employee, array $data, User $user): Employee
    {
        try {
            DB::beginTransaction();

            // Handle manager change if provided
            if (isset($data['manager_uuid'])) {
                $this->validateManagerChange($employee, $data['manager_uuid'], $user);
            }

            // Handle department head assignment if department changed
            $oldDepartmentUuid = $employee->department_uuid;
            
            // Update the employee
            $updatedEmployee = $this->employeeRepository->updateEmployee($employee, $data);

            // Handle department head reassignment if department changed
            if (isset($data['department_uuid']) && $oldDepartmentUuid !== $data['department_uuid']) {
                $this->handleDepartmentHeadReassignment($employee, $oldDepartmentUuid, $data['department_uuid']);
            }

            // Handle status change implications
            if (isset($data['status'])) {
                $this->handleStatusChange($updatedEmployee, $data['status'], $user);
            }

            Log::info('Employee updated', [
                'employee_uuid' => $employee->uuid,
                'changes' => array_keys($data),
                'updated_by' => $user->uuid
            ]);

            DB::commit();
            
            return $updatedEmployee;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update employee', [
                'employee_uuid' => $employee->uuid,
                'error' => $e->getMessage(),
                'data' => $data,
                'user_uuid' => $user->uuid
            ]);
            throw $e;
        }
    }

    /**
     * Delete an employee with cascading effects.
     */
    public function deleteEmployee(Employee $employee, User $user): bool
    {
        try {
            DB::beginTransaction();

            // Handle manager reassignment for direct reports
            $this->reassignDirectReports($employee, $user);

            // Remove as department head if applicable
            $this->removeDepartmentHeadAssignment($employee);

            // Delete the employee
            $result = $this->employeeRepository->deleteEmployee($employee);

            Log::info('Employee deleted', [
                'employee_uuid' => $employee->uuid,
                'deleted_by' => $user->uuid
            ]);

            DB::commit();
            
            return $result;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete employee', [
                'employee_uuid' => $employee->uuid,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid
            ]);
            throw $e;
        }
    }

    /**
     * Get employees for dropdown selection.
     */
    public function getEmployeesForDropdown(User $user): Collection
    {
        return $this->employeeRepository->getEmployeesForDropdown($user);
    }

    /**
     * Get potential managers for an employee.
     */
    public function getPotentialManagers(User $user, ?Employee $employee = null): Collection
    {
        return $this->employeeRepository->getPotentialManagers($user, $employee);
    }

    /**
     * Get employee statistics.
     */
    public function getEmployeeStats(User $user): array
    {
        return $this->employeeRepository->getEmployeeStats($user);
    }

    /**
     * Search employees.
     */
    public function searchEmployees(User $user, string $search, array $filters = []): Collection
    {
        return $this->employeeRepository->searchEmployees($user, $search, $filters);
    }

    /**
     * Get organogram data for the company.
     */
    public function getOrganogramData(User $user): array
    {
        $topLevelEmployees = $this->employeeRepository->getOrganogramData($user);
        
        return $this->buildOrganogramTree($topLevelEmployees);
    }

    /**
     * Get employees by department.
     */
    public function getEmployeesByDepartment(string $departmentUuid, User $user): Collection
    {
        return $this->employeeRepository->getEmployeesByDepartment($departmentUuid, $user);
    }

    /**
     * Get employees by position.
     */
    public function getEmployeesByPosition(string $positionUuid, User $user): Collection
    {
        return $this->employeeRepository->getEmployeesByPosition($positionUuid, $user);
    }

    /**
     * Get direct reports for a manager.
     */
    public function getDirectReports(string $managerUuid, User $user): Collection
    {
        return $this->employeeRepository->getDirectReports($managerUuid, $user);
    }

    /**
     * Bulk update employee status.
     */
    public function bulkUpdateStatus(array $employeeUuids, string $status, User $user): int
    {
        try {
            DB::beginTransaction();

            $updated = $this->employeeRepository->bulkUpdateStatus($employeeUuids, $status, $user);

            // Handle cascading effects for each status change
            foreach ($employeeUuids as $employeeUuid) {
                $employee = $this->employeeRepository->getEmployeeByUuid($employeeUuid, $user);
                if ($employee) {
                    $this->handleStatusChange($employee, $status, $user);
                }
            }

            Log::info('Bulk employee status update', [
                'employee_uuids' => $employeeUuids,
                'status' => $status,
                'updated_count' => $updated,
                'updated_by' => $user->uuid
            ]);

            DB::commit();
            
            return $updated;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk update employee status', [
                'employee_uuids' => $employeeUuids,
                'status' => $status,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid
            ]);
            throw $e;
        }
    }

    /**
     * Get upcoming birthdays.
     */
    public function getUpcomingBirthdays(User $user): Collection
    {
        return $this->employeeRepository->getUpcomingBirthdays($user);
    }

    /**
     * Get upcoming work anniversaries.
     */
    public function getUpcomingAnniversaries(User $user): Collection
    {
        return $this->employeeRepository->getUpcomingAnniversaries($user);
    }

    /**
     * Validate that company has required setup before creating employees.
     */
    private function validatePrerequisites(User $user): void
    {
        $company = $user->company;
        
        if (!$company) {
            throw new \Exception('User must be associated with a company to create employees.');
        }

        // Check if company has departments
        if ($company->departments()->active()->count() === 0) {
            throw new \Exception('Company must have at least one active department before creating employees.');
        }

        // Check if company has positions
        if ($company->positions()->active()->count() === 0) {
            throw new \Exception('Company must have at least one active position before creating employees.');
        }
    }

    /**
     * Validate manager change.
     */
    private function validateManagerChange(Employee $employee, ?string $managerUuid, User $user): void
    {
        if (!$managerUuid) {
            return; // Removing manager is always valid
        }

        $manager = $this->employeeRepository->getEmployeeByUuid($managerUuid, $user);
        
        if (!$manager) {
            throw new \Exception('Selected manager does not exist.');
        }

        if ($manager->company_uuid !== $employee->company_uuid) {
            throw new \Exception('Manager must belong to the same company.');
        }

        if ($manager->status !== 'active') {
            throw new \Exception('Manager must be an active employee.');
        }

        // Check for circular reporting
        if ($employee->isSubordinateOf($manager)) {
            throw new \Exception('This manager assignment would create circular reporting.');
        }
    }

    /**
     * Handle department head reassignment when employee changes departments.
     */
    private function handleDepartmentHeadReassignment(Employee $employee, ?string $oldDepartmentUuid, string $newDepartmentUuid): void
    {
        // Remove as head of old department if applicable
        if ($oldDepartmentUuid && $employee->departmentsHeaded()->where('id', $oldDepartmentUuid)->exists()) {
            $employee->departmentsHeaded()->where('id', $oldDepartmentUuid)->update(['head_of_department_id' => null]);
        }
    }

    /**
     * Handle status change implications.
     */
    private function handleStatusChange(Employee $employee, string $newStatus, User $user): void
    {
        if ($newStatus === 'terminated' || $newStatus === 'inactive') {
            // Remove as department head
            $this->removeDepartmentHeadAssignment($employee);

            // Reassign direct reports if this employee is a manager
            if ($employee->directReports()->exists()) {
                $this->reassignDirectReports($employee, $user);
            }
        }
    }

    /**
     * Reassign direct reports when a manager is deleted or deactivated.
     */
    private function reassignDirectReports(Employee $employee, User $user): void
    {
        $directReports = $employee->directReports;
        
        foreach ($directReports as $report) {
            // Assign to the employee's manager (move up one level) or remove manager assignment
            $newManagerUuid = $employee->manager_uuid;
            $report->update(['manager_uuid' => $newManagerUuid]);
        }
    }

    /**
     * Remove department head assignment.
     */
    private function removeDepartmentHeadAssignment(Employee $employee): void
    {
        $employee->departmentsHeaded()->update(['head_of_department_id' => null]);
    }

    /**
     * Build organogram tree structure.
     */
    private function buildOrganogramTree(Collection $employees): array
    {
        return $employees->map(function ($employee) {
            return [
                'uuid' => $employee->uuid,
                'name' => $employee->display_name,
                'position' => $employee->position->name ?? 'Unassigned',
                'department' => $employee->department->name ?? 'Unassigned',
                'email' => $employee->email,
                'children' => $this->buildOrganogramTree($employee->directReports)
            ];
        })->toArray();
    }
}