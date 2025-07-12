<?php

namespace App\Services;

use App\Models\Position;
use App\Models\User;
use App\Repositories\PositionRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PositionService
{
    public function __construct(
        protected PositionRepository $positionRepository
    ) {}

    /**
     * Get paginated positions with filters.
     */
    public function getPaginatedPositions(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->positionRepository->getPaginatedPositions($user, $filters, $perPage);
    }

    /**
     * Get position by ID.
     */
    public function getPositionById(string $id, User $user): ?Position
    {
        return $this->positionRepository->getPositionById($id, $user);
    }

    /**
     * Create a new position.
     */
    public function createPosition(array $data, User $user): Position
    {
        try {
            DB::beginTransaction();

            // Validate salary range
            $this->validateSalaryRange($data);

            // Create the position
            $position = $this->positionRepository->createPosition($data, $user);

            Log::info('Position created', [
                'position_id' => $position->id,
                'name' => $position->name,
                'company_uuid' => $user->company_uuid,
                'created_by' => $user->uuid
            ]);

            DB::commit();
            
            return $position->load(['company']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create position', [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_uuid' => $user->uuid
            ]);
            throw $e;
        }
    }

    /**
     * Update a position.
     */
    public function updatePosition(Position $position, array $data, User $user): Position
    {
        try {
            DB::beginTransaction();

            // Validate salary range if provided
            if (isset($data['min_salary']) || isset($data['max_salary'])) {
                $this->validateSalaryRange($data, $position);
            }

            // Check if position has employees before making certain changes
            if (isset($data['is_active']) && !$data['is_active']) {
                $this->validateDeactivation($position, $user);
            }

            // Update the position
            $updatedPosition = $this->positionRepository->updatePosition($position, $data);

            Log::info('Position updated', [
                'position_id' => $position->id,
                'changes' => array_keys($data),
                'updated_by' => $user->uuid
            ]);

            DB::commit();
            
            return $updatedPosition;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update position', [
                'position_id' => $position->id,
                'error' => $e->getMessage(),
                'data' => $data,
                'user_uuid' => $user->uuid
            ]);
            throw $e;
        }
    }

    /**
     * Delete a position.
     */
    public function deletePosition(Position $position, User $user): bool
    {
        try {
            DB::beginTransaction();

            // Check if position has active employees
            $activeEmployeesCount = $position->employees()->where('status', 'active')->count();
            
            if ($activeEmployeesCount > 0) {
                throw new \Exception("Cannot delete position '{$position->name}' because it has {$activeEmployeesCount} active employee(s). Please reassign or terminate these employees first.");
            }

            // Delete the position
            $result = $this->positionRepository->deletePosition($position);

            Log::info('Position deleted', [
                'position_id' => $position->id,
                'name' => $position->name,
                'deleted_by' => $user->uuid
            ]);

            DB::commit();
            
            return $result;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete position', [
                'position_id' => $position->id,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid
            ]);
            throw $e;
        }
    }

    /**
     * Get active positions for dropdown.
     */
    public function getActivePositions(User $user): Collection
    {
        return $this->positionRepository->getActivePositions($user);
    }

    /**
     * Get position statistics.
     */
    public function getPositionStats(User $user): array
    {
        return $this->positionRepository->getPositionStats($user);
    }

    /**
     * Search positions.
     */
    public function searchPositions(User $user, string $search, array $filters = []): Collection
    {
        return $this->positionRepository->searchPositions($user, $search, $filters);
    }

    /**
     * Get positions within salary range.
     */
    public function getPositionsInSalaryRange(User $user, float $minSalary, float $maxSalary): Collection
    {
        return $this->positionRepository->getPositionsInSalaryRange($user, $minSalary, $maxSalary);
    }

    /**
     * Bulk update position status.
     */
    public function bulkUpdateStatus(array $positionIds, bool $isActive, User $user): int
    {
        try {
            DB::beginTransaction();

            // If deactivating, check each position for active employees
            if (!$isActive) {
                foreach ($positionIds as $positionId) {
                    $position = $this->positionRepository->getPositionById($positionId, $user);
                    if ($position) {
                        $this->validateDeactivation($position, $user);
                    }
                }
            }

            $updated = $this->positionRepository->bulkUpdateStatus($positionIds, $isActive, $user);

            Log::info('Bulk position status update', [
                'position_ids' => $positionIds,
                'is_active' => $isActive,
                'updated_count' => $updated,
                'updated_by' => $user->uuid
            ]);

            DB::commit();
            
            return $updated;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk update position status', [
                'position_ids' => $positionIds,
                'is_active' => $isActive,
                'error' => $e->getMessage(),
                'user_uuid' => $user->uuid
            ]);
            throw $e;
        }
    }

    /**
     * Get positions by requirements keywords.
     */
    public function getPositionsByRequirements(User $user, array $keywords): Collection
    {
        return $this->positionRepository->getPositionsByRequirements($user, $keywords);
    }

    /**
     * Get position hierarchy by salary levels.
     */
    public function getPositionHierarchy(User $user): Collection
    {
        return $this->positionRepository->getPositionHierarchy($user);
    }

    /**
     * Get deletable positions (no active employees).
     */
    public function getDeletablePositions(User $user): Collection
    {
        return $this->positionRepository->getDeletablePositions($user);
    }

    /**
     * Validate salary range.
     */
    private function validateSalaryRange(array $data, ?Position $position = null): void
    {
        $minSalary = $data['min_salary'] ?? ($position->min_salary ?? null);
        $maxSalary = $data['max_salary'] ?? ($position->max_salary ?? null);

        if ($minSalary && $maxSalary && $minSalary > $maxSalary) {
            throw new \Exception('Minimum salary cannot be greater than maximum salary.');
        }

        if ($minSalary && $minSalary < 0) {
            throw new \Exception('Minimum salary cannot be negative.');
        }

        if ($maxSalary && $maxSalary < 0) {
            throw new \Exception('Maximum salary cannot be negative.');
        }
    }

    /**
     * Validate position deactivation.
     */
    private function validateDeactivation(Position $position, User $user): void
    {
        $activeEmployeesCount = $position->employees()->where('status', 'active')->count();
        
        if ($activeEmployeesCount > 0) {
            throw new \Exception("Cannot deactivate position '{$position->name}' because it has {$activeEmployeesCount} active employee(s). Please reassign these employees to other positions first.");
        }
    }

    /**
     * Get position comparison data for salary benchmarking.
     */
    public function getPositionComparison(Position $position, User $user): array
    {
        $similarPositions = $this->positionRepository->searchPositions($user, $position->name);
        
        $avgMinSalary = $similarPositions->where('min_salary', '>', 0)->avg('min_salary');
        $avgMaxSalary = $similarPositions->where('max_salary', '>', 0)->avg('max_salary');
        
        return [
            'position' => $position,
            'similar_positions_count' => $similarPositions->count(),
            'avg_min_salary' => $avgMinSalary,
            'avg_max_salary' => $avgMaxSalary,
            'salary_percentile' => $this->calculateSalaryPercentile($position, $similarPositions),
        ];
    }

    /**
     * Calculate salary percentile for position.
     */
    private function calculateSalaryPercentile(Position $position, Collection $similarPositions): ?float
    {
        if (!$position->min_salary || $similarPositions->isEmpty()) {
            return null;
        }

        $salaries = $similarPositions->where('min_salary', '>', 0)->pluck('min_salary')->sort()->values();
        
        if ($salaries->isEmpty()) {
            return null;
        }

        $positionIndex = $salaries->search(function ($salary) use ($position) {
            return $salary >= $position->min_salary;
        });

        if ($positionIndex === false) {
            return 100.0; // Higher than all others
        }

        return ($positionIndex / $salaries->count()) * 100;
    }
}