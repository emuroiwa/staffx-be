<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'employee_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'department',
        'position',
        'employment_type',
        'salary',
        'currency',
        'hire_date',
        'termination_date',
        'status',
        'benefits',
        'documents',
        'notes',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'benefits' => 'array',
        'documents' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Add global scope for company isolation
        static::addGlobalScope('company', function (Builder $builder) {
            if (auth()->check() && auth()->user()->company_id) {
                $builder->where('company_id', auth()->user()->company_id);
            }
        });

        // Auto-generate employee ID if not provided
        static::creating(function ($employee) {
            if (empty($employee->employee_id) && $employee->company_id) {
                $employee->employee_id = static::generateEmployeeId($employee->company_id);
            }
        });
    }

    /**
     * Get the company that owns the employee.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user associated with the employee.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payrolls for the employee.
     */
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    /**
     * Get the full name attribute.
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Check if employee is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get employee's current salary formatted.
     */
    public function getFormattedSalaryAttribute(): string
    {
        return number_format($this->salary, 2) . ' ' . $this->currency;
    }

    /**
     * Generate unique employee ID for company.
     */
    protected static function generateEmployeeId(int $companyId): string
    {
        $company = Company::find($companyId);
        $prefix = $company ? strtoupper(substr($company->slug, 0, 3)) : 'EMP';
        
        $lastEmployee = static::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('employee_id', 'like', $prefix . '%')
            ->orderBy('employee_id', 'desc')
            ->first();

        if ($lastEmployee && preg_match('/(\d+)$/', $lastEmployee->employee_id, $matches)) {
            $number = intval($matches[1]) + 1;
        } else {
            $number = 1;
        }

        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Scope: Active employees.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: By department.
     */
    public function scopeByDepartment(Builder $query, string $department): Builder
    {
        return $query->where('department', $department);
    }
}
