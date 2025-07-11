<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeController extends Controller
{
    /**
     * Display a listing of employees for the company.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Employee::with(['user', 'company']);

            // Apply filters
            if ($request->has('department') && $request->department) {
                $query->where('department', $request->department);
            }

            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('employee_id', 'like', "%{$search}%");
                });
            }

            $employees = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $employees,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employees',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created employee.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:employees,email',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'employment_type' => 'nullable|in:full_time,part_time,contract',
            'salary' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'hire_date' => 'nullable|date',
            'status' => 'nullable|in:active,inactive,terminated',
        ]);

        try {
            $data = $request->validated();
            $data['company_id'] = auth()->user()->company_id;

            $employee = Employee::create($data);
            $employee->load(['user', 'company']);

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'data' => $employee,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified employee.
     */
    public function show(Employee $employee): JsonResponse
    {
        try {
            $employee->load(['user', 'company', 'payrolls' => function ($query) {
                $query->latest()->limit(5);
            }]);

            return response()->json([
                'success' => true,
                'data' => $employee,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified employee.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|nullable|email|unique:employees,email,' . $employee->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'department' => 'sometimes|nullable|string|max:100',
            'position' => 'sometimes|nullable|string|max:100',
            'employment_type' => 'sometimes|nullable|in:full_time,part_time,contract',
            'salary' => 'sometimes|nullable|numeric|min:0',
            'currency' => 'sometimes|nullable|string|size:3',
            'hire_date' => 'sometimes|nullable|date',
            'status' => 'sometimes|nullable|in:active,inactive,terminated',
        ]);

        try {
            $employee->update($request->validated());
            $employee->load(['user', 'company']);

            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully',
                'data' => $employee,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified employee.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        try {
            $employee->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get company departments.
     */
    public function departments(): JsonResponse
    {
        try {
            $departments = Employee::select('department')
                ->whereNotNull('department')
                ->distinct()
                ->pluck('department');

            return response()->json([
                'success' => true,
                'data' => $departments,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch departments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
