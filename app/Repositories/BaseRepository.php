<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    /**
     * Apply company filtering to a query builder to ensure multi-tenant isolation.
     * This method should be called on every query to prevent data leakage between companies.
     */
    protected function applyCompanyFilter(Builder $query, User $user): Builder
    {
        return $query->where('company_uuid', $user->company_uuid);
    }

    /**
     * Get a new query builder with company filtering already applied.
     * This is a convenience method to start queries with proper tenant isolation.
     */
    protected function getCompanyQuery(string $modelClass, User $user): Builder
    {
        $model = new $modelClass();
        return $this->applyCompanyFilter($model->query(), $user);
    }

    /**
     * Validate that a model belongs to the user's company before performing operations.
     * This provides an additional security check for update/delete operations.
     */
    protected function validateCompanyOwnership(Model $model, User $user): bool
    {
        if (!property_exists($model, 'company_uuid') && !$model->hasAttribute('company_uuid')) {
            return true; // Model doesn't have company isolation
        }

        return $model->company_uuid === $user->company_uuid;
    }
}