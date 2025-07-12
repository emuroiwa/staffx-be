<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Position extends Model
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
        'min_salary',
        'max_salary',
        'currency',
        'is_active',
        'requirements',
    ];

    protected $casts = [
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
        'is_active' => 'boolean',
        'requirements' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate UUID on creation
        static::creating(function ($position) {
            if (empty($position->id)) {
                $position->id = (string) Str::uuid();
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
     * Get the company that owns the position.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Get the employees for this position.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'position_uuid', 'id');
    }

    /**
     * Check if position is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get formatted salary range.
     */
    public function getSalaryRangeAttribute(): string
    {
        if (!$this->min_salary && !$this->max_salary) {
            return 'Not specified';
        }
        
        $min = $this->min_salary ? number_format($this->min_salary, 2) : '0.00';
        $max = $this->max_salary ? number_format($this->max_salary, 2) : 'No limit';
        
        return "{$this->currency} {$min} - {$max}";
    }

    /**
     * Scope: Active positions.
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
     * Get positions count within salary range.
     */
    public function scopeWithinSalaryRange(Builder $query, float $minSalary, float $maxSalary): Builder
    {
        return $query->where(function ($q) use ($minSalary, $maxSalary) {
            $q->whereBetween('min_salary', [$minSalary, $maxSalary])
              ->orWhereBetween('max_salary', [$minSalary, $maxSalary])
              ->orWhere(function ($subQ) use ($minSalary, $maxSalary) {
                  $subQ->where('min_salary', '<=', $minSalary)
                       ->where('max_salary', '>=', $maxSalary);
              });
        });
    }
}