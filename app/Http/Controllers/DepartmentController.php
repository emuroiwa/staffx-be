<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\DepartmentService;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\DepartmentDetailResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function __construct(
        protected DepartmentService $departmentService
    ) {}

    /**
     * Display a listing of departments.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search', 'is_active', 'has_head', 'order_by', 'order_direction'
        ]);

        $perPage = $request->get('per_page', 15);
        $departments = $this->departmentService->getPaginatedDepartments(auth()->user(), $filters, $perPage);

        return DepartmentResource::collection($departments);
    }

    /**
     * Store a newly created department.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->where('company_uuid', auth()->user()->company_uuid)],
            'description' => ['nullable', 'string', 'max:1000'],
            'cost_center' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'budget_info' => ['nullable', 'array'],
            'budget_info.allocation' => ['nullable', 'numeric', 'min:0'],
            'budget_info.currency' => ['nullable', 'string', 'size:3'],
            'budget_info.fiscal_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        try {
            $department = $this->departmentService->createDepartment($validated, auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Department created successfully.',
                'data' => new DepartmentDetailResource($department)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ], 422);
        }
    }

    /**
     * Display the specified department.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $department = $this->departmentService->getDepartmentById($id, auth()->user());

            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new DepartmentDetailResource($department)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve department: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified department.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $department = $this->departmentService->getDepartmentById($id, auth()->user());

        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('departments', 'name')
                    ->where('company_uuid', auth()->user()->company_uuid)
                    ->ignore($department->id)
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'cost_center' => ['sometimes', 'nullable', 'string', 'max:50'],
            'head_of_department_id' => [
                'sometimes', 'nullable', 'uuid',
                Rule::exists('employees', 'uuid')->where('company_uuid', auth()->user()->company_uuid)
            ],
            'is_active' => ['sometimes', 'boolean'],
            'budget_info' => ['sometimes', 'nullable', 'array'],
            'budget_info.allocation' => ['nullable', 'numeric', 'min:0'],
            'budget_info.currency' => ['nullable', 'string', 'size:3'],
            'budget_info.fiscal_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        try {
            $updatedDepartment = $this->departmentService->updateDepartment($department, $validated, auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully.',
                'data' => new DepartmentDetailResource($updatedDepartment)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ], 422);
        }
    }

    /**
     * Remove the specified department.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $department = $this->departmentService->getDepartmentById($id, auth()->user());

            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department not found.'
                ], 404);
            }

            $this->departmentService->deleteDepartment($department, auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active departments for dropdown.
     */
    public function dropdown(): JsonResponse
    {
        try {
            $departments = $this->departmentService->getActiveDepartments(auth()->user());

            return response()->json([
                'success' => true,
                'data' => $departments->map(function ($department) {
                    return [
                        'id' => $department->id,
                        'name' => $department->name,
                        'cost_center' => $department->cost_center
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve departments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get department statistics.
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->departmentService->getDepartmentStats(auth()->user());

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve department statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search departments.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:2|max:255',
            'is_active' => 'nullable|boolean'
        ]);

        try {
            $departments = $this->departmentService->searchDepartments(
                auth()->user(),
                $request->get('search'),
                $request->only(['is_active'])
            );

            return response()->json([
                'success' => true,
                'data' => $departments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search departments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update department status.
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'department_ids' => 'required|array|min:1',
            'department_ids.*' => 'required|uuid',
            'is_active' => 'required|boolean'
        ]);

        try {
            $updated = $this->departmentService->bulkUpdateStatus(
                $request->get('department_ids'),
                $request->get('is_active'),
                auth()->user()
            );

            return response()->json([
                'success' => true,
                'message' => "{$updated} departments updated successfully.",
                'data' => ['updated_count' => $updated]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department status: ' . $e->getMessage()
            ], 500);
        }
    }
}