<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Department extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'company_uuid',
        'name',
        'description',
        'head_of_department_id',
        'cost_center',
        'is_active',
        'budget_info',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'budget_info' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate UUID on creation
        static::creating(function ($department) {
            if (empty($department->id)) {
                $department->id = (string) Str::uuid();
            }
        });

        // Add global scope for company isolation
        static::addGlobalScope('company', function (Builder $builder) {
            if (auth()->check() && auth()->user()->company_uuid) {
                $builder->where('company_uuid', auth()->user()->company_uuid);
            }
        });
    }

    /**
     * Get the company that owns the department.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Get the head of department (employee).
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'head_of_department_id', 'uuid');
    }

    /**
     * Get the employees in this department.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department_uuid', 'id');
    }

    /**
     * Get active employees in this department.
     */
    public function activeEmployees(): HasMany
    {
        return $this->employees()->where('status', 'active');
    }

    /**
     * Check if department is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get employees count.
     */
    public function getEmployeesCountAttribute(): int
    {
        return $this->employees()->count();
    }

    /**
     * Get active employees count.
     */
    public function getActiveEmployeesCountAttribute(): int
    {
        return $this->activeEmployees()->count();
    }

    /**
     * Get budget allocation if available.
     */
    public function getBudgetAllocationAttribute(): ?float
    {
        return data_get($this->budget_info, 'allocation');
    }

    /**
     * Scope: Active departments.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: By company.
     */
    public function scopeForCompany(Builder $query, string $companyUuid): Builder
    {
        return $query->where('company_uuid', $companyUuid);
    }

    /**
     * Scope: With head assigned.
     */
    public function scopeWithHead(Builder $query): Builder
    {
        return $query->whereNotNull('head_of_department_id');
    }

    /**
     * Scope: Without head assigned.
     */
    public function scopeWithoutHead(Builder $query): Builder
    {
        return $query->whereNull('head_of_department_id');
    }

    /**
     * Set department head.
     */
    public function setHead(Employee $employee): void
    {
        // Ensure employee belongs to same company
        if ($employee->company_uuid !== $this->company_uuid) {
            throw new \InvalidArgumentException('Employee must belong to the same company as the department.');
        }

        // Ensure employee is in this department
        if ($employee->department_uuid !== $this->id) {
            throw new \InvalidArgumentException('Employee must be assigned to this department to be its head.');
        }

        $this->update(['head_of_department_id' => $employee->uuid]);
    }

    /**
     * Remove department head.
     */
    public function removeHead(): void
    {
        $this->update(['head_of_department_id' => null]);
    }
}