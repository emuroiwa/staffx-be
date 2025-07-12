<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Services\PositionService;
use App\Http\Resources\PositionResource;
use App\Http\Resources\PositionDetailResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    public function __construct(
        protected PositionService $positionService
    ) {}

    /**
     * Display a listing of positions.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search', 'is_active', 'min_salary_from', 'min_salary_to', 
            'max_salary_from', 'max_salary_to', 'currency', 'order_by', 'order_direction'
        ]);

        $perPage = $request->get('per_page', 15);
        $positions = $this->positionService->getPaginatedPositions(auth()->user(), $filters, $perPage);

        return PositionResource::collection($positions);
    }

    /**
     * Store a newly created position.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('positions', 'name')->where('company_uuid', auth()->user()->company_uuid)],
            'description' => ['nullable', 'string', 'max:1000'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'requirements' => ['nullable', 'array'],
            'requirements.education' => ['nullable', 'string'],
            'requirements.experience' => ['nullable', 'string'],
            'requirements.skills' => ['nullable', 'array'],
            'requirements.skills.*' => ['string'],
        ]);

        try {
            $position = $this->positionService->createPosition($validated, auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Position created successfully.',
                'data' => new PositionDetailResource($position)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create position: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ], 422);
        }
    }

    /**
     * Display the specified position.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $position = $this->positionService->getPositionById($id, auth()->user());

            if (!$position) {
                return response()->json([
                    'success' => false,
                    'message' => 'Position not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new PositionDetailResource($position)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve position: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified position.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $position = $this->positionService->getPositionById($id, auth()->user());

        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found.'
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('positions', 'name')
                    ->where('company_uuid', auth()->user()->company_uuid)
                    ->ignore($position->id)
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'min_salary' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_salary' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
            'requirements' => ['sometimes', 'nullable', 'array'],
            'requirements.education' => ['nullable', 'string'],
            'requirements.experience' => ['nullable', 'string'],
            'requirements.skills' => ['nullable', 'array'],
            'requirements.skills.*' => ['string'],
        ]);

        try {
            $updatedPosition = $this->positionService->updatePosition($position, $validated, auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Position updated successfully.',
                'data' => new PositionDetailResource($updatedPosition)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update position: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ], 422);
        }
    }

    /**
     * Remove the specified position.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $position = $this->positionService->getPositionById($id, auth()->user());

            if (!$position) {
                return response()->json([
                    'success' => false,
                    'message' => 'Position not found.'
                ], 404);
            }

            $this->positionService->deletePosition($position, auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Position deleted successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete position: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active positions for dropdown.
     */
    public function dropdown(): JsonResponse
    {
        try {
            $positions = $this->positionService->getActivePositions(auth()->user());

            return response()->json([
                'success' => true,
                'data' => $positions->map(function ($position) {
                    return [
                        'id' => $position->id,
                        'name' => $position->name,
                        'salary_range' => $position->salary_range
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve positions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get position statistics.
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->positionService->getPositionStats(auth()->user());

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve position statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search positions.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:2|max:255',
            'is_active' => 'nullable|boolean',
            'currency' => 'nullable|string|size:3'
        ]);

        try {
            $positions = $this->positionService->searchPositions(
                auth()->user(),
                $request->get('search'),
                $request->only(['is_active', 'currency'])
            );

            return response()->json([
                'success' => true,
                'data' => $positions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search positions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get positions within salary range.
     */
    public function salaryRange(Request $request): JsonResponse
    {
        $request->validate([
            'min_salary' => 'required|numeric|min:0',
            'max_salary' => 'required|numeric|min:0|gte:min_salary'
        ]);

        try {
            $positions = $this->positionService->getPositionsInSalaryRange(
                auth()->user(),
                $request->get('min_salary'),
                $request->get('max_salary')
            );

            return response()->json([
                'success' => true,
                'data' => PositionResource::collection($positions)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve positions in salary range: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update position status.
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'position_ids' => 'required|array|min:1',
            'position_ids.*' => 'required|uuid',
            'is_active' => 'required|boolean'
        ]);

        try {
            $updated = $this->positionService->bulkUpdateStatus(
                $request->get('position_ids'),
                $request->get('is_active'),
                auth()->user()
            );

            return response()->json([
                'success' => true,
                'message' => "{$updated} positions updated successfully.",
                'data' => ['updated_count' => $updated]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update position status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get positions by requirements.
     */
    public function byRequirements(Request $request): JsonResponse
    {
        $request->validate([
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'required|string'
        ]);

        try {
            $positions = $this->positionService->getPositionsByRequirements(
                auth()->user(),
                $request->get('keywords')
            );

            return response()->json([
                'success' => true,
                'data' => $positions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve positions by requirements: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get position hierarchy by salary levels.
     */
    public function hierarchy(): JsonResponse
    {
        try {
            $hierarchy = $this->positionService->getPositionHierarchy(auth()->user());

            return response()->json([
                'success' => true,
                'data' => $hierarchy->map(function ($position) {
                    return [
                        'id' => $position->id,
                        'name' => $position->name,
                        'salary_range' => $position->salary_range,
                        'employee_count' => $position->employees->count(),
                        'employees' => $position->employees->map(function ($employee) {
                            return [
                                'uuid' => $employee->uuid,
                                'name' => $employee->display_name
                            ];
                        })
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve position hierarchy: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get deletable positions.
     */
    public function deletable(): JsonResponse
    {
        try {
            $positions = $this->positionService->getDeletablePositions(auth()->user());

            return response()->json([
                'success' => true,
                'data' => $positions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve deletable positions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get position comparison for salary benchmarking.
     */
    public function comparison(string $id): JsonResponse
    {
        try {
            $position = $this->positionService->getPositionById($id, auth()->user());

            if (!$position) {
                return response()->json([
                    'success' => false,
                    'message' => 'Position not found.'
                ], 404);
            }

            $comparison = $this->positionService->getPositionComparison($position, auth()->user());

            return response()->json([
                'success' => true,
                'data' => $comparison
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve position comparison: ' . $e->getMessage()
            ], 500);
        }
    }
}