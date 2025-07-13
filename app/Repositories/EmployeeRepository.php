<?php

namespace App\Repositories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EmployeeRepository extends BaseRepository
{
    /**
     * Get paginated employees for company with filters and relationships.
     */
    public function getPaginatedEmployees(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Employee::query()
            ->where('company_uuid', $user->company_uuid)
            ->with(['department', 'position', 'manager', 'user', 'company']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('employee_id', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Apply department filter
        if (!empty($filters['department_uuid'])) {
            $query->where('department_uuid', $filters['department_uuid']);
        }

        // Apply position filter
        if (!empty($filters['position_uuid'])) {
            $query->where('position_uuid', $filters['position_uuid']);
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        // Apply employment type filter
        if (!empty($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }

        // Apply manager filter
        if (!empty($filters['manager_uuid'])) {
            $query->where('manager_uuid', $filters['manager_uuid']);
        }

        // Apply date filters
        if (!empty($filters['hire_date_from'])) {
            $query->where('hire_date', '>=', $filters['hire_date_from']);
        }

        if (!empty($filters['hire_date_to'])) {
            $query->where('hire_date', '<=', $filters['hire_date_to']);
        }

        // Apply salary range filter
        if (!empty($filters['salary_min'])) {
            $query->where('salary', '>=', $filters['salary_min']);
        }

        if (!empty($filters['salary_max'])) {
            $query->where('salary', '<=', $filters['salary_max']);
        }

        // Default ordering
        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        return $query->paginate($perPage);
    }

    /**
     * Get employee by UUID for company.
     */
    public function getEmployeeByUuid(string $uuid, User $user): ?Employee
    {
        return Employee::where('uuid', $uuid)
            ->where('company_uuid', $user->company_uuid)
            ->with(['department', 'position', 'manager', 'directReports', 'user', 'company'])
            ->first();
    }

    /**
     * Create a new employee.
     */
    public function createEmployee(array $data, User $user): Employee
    {
        // Ensure company UUID is set
        $data['company_uuid'] = $user->company_uuid;
        
        return Employee::create($data);
    }

    /**
     * Update an employee.
     */
    public function updateEmployee(Employee $employee, array $data): Employee
    {
        $employee->update($data);
        return $employee->fresh(['department', 'position', 'manager', 'directReports', 'user']);
    }

    /**
     * Delete an employee (soft delete).
     */
    public function deleteEmployee(Employee $employee): bool
    {
        return $employee->delete();
    }

    /**
     * Get employees for dropdown/selection (active only).
     */
    public function getEmployeesForDropdown(User $user): Collection
    {
        return Employee::select(['uuid', 'first_name', 'last_name', 'employee_id', 'position_uuid'])
            ->where('company_uuid', $user->company_uuid)
            ->with(['position:id,name'])
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Get potential managers for an employee (active employees who won't create circular reporting).
     */
    public function getPotentialManagers(User $user, ?Employee $employee = null): Collection
    {
        $query = Employee::select(['uuid', 'first_name', 'last_name', 'employee_id', 'position_uuid'])
            ->where('company_uuid', $user->company_uuid)
            ->with(['position:id,name'])
            ->where('status', 'active');

        // Exclude the employee themselves if updating
        if ($employee) {
            $query->where('uuid', '!=', $employee->uuid);
            
            // Exclude employees who report to this employee (prevent circular reporting)
            $subordinateUuids = $this->getAllSubordinateUuids($employee);
            if (!empty($subordinateUuids)) {
                $query->whereNotIn('uuid', $subordinateUuids);
            }
        }

        return $query->orderBy('first_name')->orderBy('last_name')->get();
    }

    /**
     * Get all subordinate UUIDs recursively.
     */
    private function getAllSubordinateUuids(Employee $employee): array
    {
        $subordinates = [];
        $directReports = $employee->directReports;
        
        foreach ($directReports as $report) {
            $subordinates[] = $report->uuid;
            $subordinates = array_merge($subordinates, $this->getAllSubordinateUuids($report));
        }
        
        return $subordinates;
    }

    /**
     * Get employee statistics for dashboard.
     */
    public function getEmployeeStats(User $user): array
    {
        $totalEmployees = Employee::where('company_uuid', $user->company_uuid)->count();
        $activeEmployees = Employee::where('company_uuid', $user->company_uuid)->where('status', 'active')->count();
        $inactiveEmployees = Employee::where('company_uuid', $user->company_uuid)->where('status', 'inactive')->count();
        $terminatedEmployees = Employee::where('company_uuid', $user->company_uuid)->where('status', 'terminated')->count();

        // Get department-wise count
        $departmentStats = Employee::selectRaw('department_uuid, count(*) as count')
            ->where('company_uuid', $user->company_uuid)
            ->with(['department:id,name'])
            ->where('status', 'active')
            ->groupBy('department_uuid')
            ->get()
            ->map(function ($stat) {
                return [
                    'department' => $stat->department->name ?? 'Unassigned',
                    'count' => $stat->count
                ];
            });

        // Get employment type distribution
        $employmentTypeStats = Employee::selectRaw('employment_type, count(*) as count')
            ->where('company_uuid', $user->company_uuid)
            ->where('status', 'active')
            ->groupBy('employment_type')
            ->get()
            ->pluck('count', 'employment_type');

        // Get recent hires (last 30 days)
        $recentHires = Employee::where('company_uuid', $user->company_uuid)
            ->where('hire_date', '>=', now()->subDays(30))
            ->where('status', 'active')
            ->count();

        // Get upcoming work anniversaries (next 30 days)
        $upcomingAnniversaries = Employee::where('company_uuid', $user->company_uuid)
            ->whereRaw('DATE_FORMAT(hire_date, "%m-%d") BETWEEN ? AND ?', [
                now()->format('m-d'),
                now()->addDays(30)->format('m-d')
            ])
            ->where('status', 'active')
            ->count();

        return [
            'total_employees' => $totalEmployees,
            'active_employees' => $activeEmployees,
            'inactive_employees' => $inactiveEmployees,
            'terminated_employees' => $terminatedEmployees,
            'department_stats' => $departmentStats,
            'employment_type_stats' => $employmentTypeStats,
            'recent_hires' => $recentHires,
            'upcoming_anniversaries' => $upcomingAnniversaries,
        ];
    }

    /**
     * Search employees with advanced filters.
     */
    public function searchEmployees(User $user, string $search, array $filters = []): Collection
    {
        $query = Employee::where('company_uuid', $user->company_uuid)
            ->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                  ->orWhere('last_name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('employee_id', 'like', '%' . $search . '%');
            })
            ->with(['department:id,name', 'position:id,name']);

        // Apply additional filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['department_uuid'])) {
            $query->where('department_uuid', $filters['department_uuid']);
        }

        if (isset($filters['position_uuid'])) {
            $query->where('position_uuid', $filters['position_uuid']);
        }

        return $query->limit(20)->get(['uuid', 'first_name', 'last_name', 'email', 'employee_id', 'department_uuid', 'position_uuid', 'status']);
    }

    /**
     * Get organogram data for the company.
     */
    public function getOrganogramData(User $user): Collection
    {
        return Employee::where('company_uuid', $user->company_uuid)
            ->with(['department:id,name', 'position:id,name', 'directReports.department', 'directReports.position'])
            ->whereNull('manager_uuid') // Start with top-level employees
            ->where('status', 'active')
            ->get();
    }

    /**
     * Get employees by department.
     */
    public function getEmployeesByDepartment(string $departmentUuid, User $user): Collection
    {
        return Employee::where('company_uuid', $user->company_uuid)
            ->where('department_uuid', $departmentUuid)
            ->with(['position:id,name', 'manager:uuid,first_name,last_name'])
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Get employees by position.
     */
    public function getEmployeesByPosition(string $positionUuid, User $user): Collection
    {
        return Employee::where('company_uuid', $user->company_uuid)
            ->where('position_uuid', $positionUuid)
            ->with(['department:id,name', 'manager:uuid,first_name,last_name'])
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Get employees reporting to a manager.
     */
    public function getDirectReports(string $managerUuid, User $user): Collection
    {
        return Employee::where('company_uuid', $user->company_uuid)
            ->where('manager_uuid', $managerUuid)
            ->with(['department:id,name', 'position:id,name'])
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Bulk update employee status.
     */
    public function bulkUpdateStatus(array $employeeUuids, string $status, User $user): int
    {
        return Employee::where('company_uuid', $user->company_uuid)
            ->whereIn('uuid', $employeeUuids)
            ->update(['status' => $status]);
    }

    /**
     * Get employees with upcoming birthdays (next 30 days).
     */
    public function getUpcomingBirthdays(User $user): Collection
    {
        return Employee::where('company_uuid', $user->company_uuid)
            ->whereRaw('DATE_FORMAT(dob, "%m-%d") BETWEEN ? AND ?', [
                now()->format('m-d'),
                now()->addDays(30)->format('m-d')
            ])
            ->where('status', 'active')
            ->select(['uuid', 'first_name', 'last_name', 'dob', 'department_uuid'])
            ->with(['department:id,name'])
            ->orderByRaw('DATE_FORMAT(dob, "%m-%d")')
            ->get();
    }

    /**
     * Get employees with work anniversaries (next 30 days).
     */
    public function getUpcomingAnniversaries(User $user): Collection
    {
        return Employee::where('company_uuid', $user->company_uuid)
            ->whereRaw('DATE_FORMAT(hire_date, "%m-%d") BETWEEN ? AND ?', [
                now()->format('m-d'),
                now()->addDays(30)->format('m-d')
            ])
            ->where('status', 'active')
            ->select(['uuid', 'first_name', 'last_name', 'hire_date', 'department_uuid'])
            ->with(['department:id,name'])
            ->orderByRaw('DATE_FORMAT(hire_date, "%m-%d")')
            ->get();
    }

    /**
     * Check if employee exists for company.
     */
    public function employeeExistsForCompany(string $employeeUuid, User $user): bool
    {
        return Employee::where('company_uuid', $user->company_uuid)
            ->where('uuid', $employeeUuid)
            ->exists();
    }
}