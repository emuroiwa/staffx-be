<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Employee extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'user_id',
        'user_uuid',
        'department_uuid',
        'position_uuid',
        'manager_uuid',
        'employee_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'dob',
        'start_date',
        'employment_type',
        'is_director',
        'is_independent_contractor',
        'is_uif_exempt',
        'salary',
        'currency',
        'tax_number',
        'bank_details',
        'pay_frequency',
        'national_id',
        'passport_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'hire_date',
        'termination_date',
        'status',
        'benefits',
        'documents',
        'notes',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'dob' => 'date',
        'start_date' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'is_director' => 'boolean',
        'is_independent_contractor' => 'boolean',
        'is_uif_exempt' => 'boolean',
        'benefits' => 'array',
        'documents' => 'array',
        'bank_details' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Add global scope for company isolation
        static::addGlobalScope('company', function (Builder $builder) {
            if (auth()->check() && auth()->user()->company_uuid) {
                $builder->where('company_uuid', auth()->user()->company_uuid);
            }
        });

        // Auto-generate UUID on creation
        static::creating(function ($employee) {
            if (empty($employee->uuid)) {
                $employee->uuid = (string) Str::uuid();
            }
        });

        // Auto-generate employee ID if not provided
        static::creating(function ($employee) {
            if (empty($employee->employee_id) && $employee->company_uuid) {
                $employee->employee_id = static::generateEmployeeId($employee->company_uuid);
            }
        });

        // Validate manager assignment on save
        static::saving(function ($employee) {
            if ($employee->manager_uuid && !$employee->validateManagerAssignment($employee->manager_uuid)) {
                throw new \InvalidArgumentException('Invalid manager assignment. Manager must be in the same company and cannot create circular reporting.');
            }
        });
    }

    /**
     * Get the company that owns the employee.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Get the user associated with the employee.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the payrolls for the employee.
     */
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'employee_uuid', 'uuid');
    }

    /**
     * Get the department this employee belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_uuid', 'id');
    }

    /**
     * Get the position this employee holds.
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_uuid', 'id');
    }

    /**
     * Get the manager of this employee.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_uuid', 'uuid');
    }

    /**
     * Get the direct reports (subordinates) of this employee.
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_uuid', 'uuid');
    }

    /**
     * Get all subordinates recursively (for organogram).
     */
    public function allSubordinates(): HasMany
    {
        return $this->directReports()->with('allSubordinates');
    }

    /**
     * Get departments this employee heads.
     */
    public function departmentsHeaded(): HasMany
    {
        return $this->hasMany(Department::class, 'head_of_department_id', 'uuid');
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
    protected static function generateEmployeeId(string $companyUuid): string
    {
        $company = Company::where('uuid', $companyUuid)->first();
        $prefix = $company ? strtoupper(substr($company->slug, 0, 3)) : 'EMP';
        
        $lastEmployee = static::withoutGlobalScope('company')
            ->where('company_uuid', $companyUuid)
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
     * Get the employee's full display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the employee's age.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->dob ? $this->dob->diffInYears(now()) : null;
    }

    /**
     * Get years of service.
     */
    public function getYearsOfServiceAttribute(): ?float
    {
        $startDate = $this->start_date ?? $this->hire_date;
        return $startDate ? $startDate->diffInYears(now()) : null;
    }

    /**
     * Check if employee is a manager (has direct reports).
     */
    public function isManager(): bool
    {
        return $this->directReports()->exists();
    }

    /**
     * Check if employee is a department head.
     */
    public function isDepartmentHead(): bool
    {
        return $this->departmentsHeaded()->exists();
    }

    /**
     * Scope: By department.
     */
    public function scopeInDepartment(Builder $query, string $departmentUuid): Builder
    {
        return $query->where('department_uuid', $departmentUuid);
    }

    /**
     * Scope: By position.
     */
    public function scopeInPosition(Builder $query, string $positionUuid): Builder
    {
        return $query->where('position_uuid', $positionUuid);
    }

    /**
     * Scope: Managers only.
     */
    public function scopeManagers(Builder $query): Builder
    {
        return $query->whereHas('directReports');
    }

    /**
     * Scope: Department heads only.
     */
    public function scopeDepartmentHeads(Builder $query): Builder
    {
        return $query->whereHas('departmentsHeaded');
    }

    /**
     * Validate manager assignment (must be in same company).
     */
    public function validateManagerAssignment(string $managerUuid): bool
    {
        $manager = static::withoutGlobalScope('company')->find($managerUuid);
        
        if (!$manager) {
            return false;
        }
        
        // Manager must be in same company
        if ($manager->company_uuid !== $this->company_uuid) {
            return false;
        }
        
        // Prevent circular reporting (employee cannot be their own manager, directly or indirectly)
        return !$this->isSubordinateOf($manager);
    }

    /**
     * Check if this employee is a subordinate of given employee.
     */
    public function isSubordinateOf(Employee $employee): bool
    {
        $currentManager = $this->manager;
        
        while ($currentManager) {
            if ($currentManager->uuid === $employee->uuid) {
                return true;
            }
            $currentManager = $currentManager->manager;
        }
        
        return false;
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
