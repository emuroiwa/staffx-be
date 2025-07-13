<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Services\EmployeeService;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\EmployeeDetailResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService
    ) {}

    /**
     * Display a listing of employees.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search', 'department_uuid', 'position_uuid', 'status', 'employment_type',
            'manager_uuid', 'hire_date_from', 'hire_date_to', 'salary_min', 'salary_max',
            'order_by', 'order_direction'
        ]);

        $perPage = $request->get('per_page', 15);
        $employees = $this->employeeService->getPaginatedEmployees(auth()->user(), $filters, $perPage);

        return EmployeeResource::collection($employees);
    }

    /**
     * Store a newly created employee.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        try {
            $employee = $this->employeeService->createEmployee($request->validated(), auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully.',
                'data' => new EmployeeDetailResource($employee)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ], 422);
        }
    }

    /**
     * Display the specified employee.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $employee = $this->employeeService->getEmployeeByUuid($uuid, auth()->user());

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new EmployeeDetailResource($employee)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified employee.
     */
    public function update(UpdateEmployeeRequest $request, string $uuid): JsonResponse
    {
        try {
            $employee = $this->employeeService->getEmployeeByUuid($uuid, auth()->user());

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found.'
                ], 404);
            }

            $updatedEmployee = $this->employeeService->updateEmployee(
                $employee, 
                $request->validated(), 
                auth()->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully.',
                'data' => new EmployeeDetailResource($updatedEmployee)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ], 422);
        }
    }

    /**
     * Remove the specified employee.
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $employee = $this->employeeService->getEmployeeByUuid($uuid, auth()->user());

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found.'
                ], 404);
            }

            $this->employeeService->deleteEmployee($employee, auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Employee deleted successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employees for dropdown selection.
     */
    public function dropdown(): JsonResponse
    {
        try {
            $employees = $this->employeeService->getEmployeesForDropdown(auth()->user());

            return response()->json([
                'success' => true,
                'data' => $employees->map(function ($employee) {
                    return [
                        'uuid' => $employee->uuid,
                        'name' => $employee->display_name,
                        'employee_id' => $employee->employee_id,
                        'position' => $employee->position->name ?? null
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employees: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get potential managers for employee assignment.
     */
    public function potentialManagers(Request $request): JsonResponse
    {
        try {
            $employeeUuid = $request->get('employee_uuid');
            $employee = null;

            if ($employeeUuid) {
                $employee = $this->employeeService->getEmployeeByUuid($employeeUuid, auth()->user());
            }

            $managers = $this->employeeService->getPotentialManagers(auth()->user(), $employee);

            return response()->json([
                'success' => true,
                'data' => $managers->map(function ($manager) {
                    return [
                        'uuid' => $manager->uuid,
                        'name' => $manager->display_name,
                        'employee_id' => $manager->employee_id,
                        'position' => $manager->position->name ?? null
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve potential managers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee statistics for dashboard.
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->employeeService->getEmployeeStats(auth()->user());

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search employees.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:2|max:255',
            'status' => 'nullable|in:active,inactive,terminated',
            'department_uuid' => 'nullable|uuid',
            'position_uuid' => 'nullable|uuid'
        ]);

        try {
            $employees = $this->employeeService->searchEmployees(
                auth()->user(),
                $request->get('search'),
                $request->only(['status', 'department_uuid', 'position_uuid'])
            );

            return response()->json([
                'success' => true,
                'data' => $employees
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search employees: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get organogram data.
     */
    public function organogram(): JsonResponse
    {
        try {
            $organogram = $this->employeeService->getOrganogramData(auth()->user());

            return response()->json([
                'success' => true,
                'data' => $organogram
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve organogram data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employees by department.
     */
    public function byDepartment(string $departmentUuid): JsonResponse
    {
        try {
            $employees = $this->employeeService->getEmployeesByDepartment($departmentUuid, auth()->user());

            return response()->json([
                'success' => true,
                'data' => EmployeeResource::collection($employees)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employees by department: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employees by position.
     */
    public function byPosition(string $positionUuid): JsonResponse
    {
        try {
            $employees = $this->employeeService->getEmployeesByPosition($positionUuid, auth()->user());

            return response()->json([
                'success' => true,
                'data' => EmployeeResource::collection($employees)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employees by position: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get direct reports for a manager.
     */
    public function directReports(string $managerUuid): JsonResponse
    {
        try {
            $employees = $this->employeeService->getDirectReports($managerUuid, auth()->user());

            return response()->json([
                'success' => true,
                'data' => EmployeeResource::collection($employees)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve direct reports: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update employee status.
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'employee_uuids' => 'required|array|min:1',
            'employee_uuids.*' => 'required|uuid',
            'status' => 'required|in:active,inactive,terminated'
        ]);

        try {
            $updated = $this->employeeService->bulkUpdateStatus(
                $request->get('employee_uuids'),
                $request->get('status'),
                auth()->user()
            );

            return response()->json([
                'success' => true,
                'message' => "{$updated} employees updated successfully.",
                'data' => ['updated_count' => $updated]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming birthdays.
     */
    public function upcomingBirthdays(): JsonResponse
    {
        try {
            $birthdays = $this->employeeService->getUpcomingBirthdays(auth()->user());

            return response()->json([
                'success' => true,
                'data' => $birthdays->map(function ($employee) {
                    return [
                        'uuid' => $employee->uuid,
                        'name' => $employee->display_name,
                        'dob' => $employee->dob->format('Y-m-d'),
                        'age' => $employee->age,
                        'department' => $employee->department->name ?? null,
                        'days_until' => now()->diffInDays($employee->dob->setYear(now()->year))
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upcoming birthdays: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming work anniversaries.
     */
    public function upcomingAnniversaries(): JsonResponse
    {
        try {
            $anniversaries = $this->employeeService->getUpcomingAnniversaries(auth()->user());

            return response()->json([
                'success' => true,
                'data' => $anniversaries->map(function ($employee) {
                    return [
                        'uuid' => $employee->uuid,
                        'name' => $employee->display_name,
                        'hire_date' => $employee->hire_date->format('Y-m-d'),
                        'years_of_service' => $employee->years_of_service,
                        'department' => $employee->department->name ?? null,
                        'days_until' => now()->diffInDays($employee->hire_date->setYear(now()->year))
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upcoming anniversaries: ' . $e->getMessage()
            ], 500);
        }
    }
}