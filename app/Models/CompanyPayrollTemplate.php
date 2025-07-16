<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;

class CompanyPayrollTemplate extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'company_uuid',
        'category_uuid', 
        'code',
        'name',
        'description',
        'type',
        'contribution_type',
        'has_employee_match',
        'match_logic',
        'employee_match_amount',
        'employee_match_percentage',
        'calculation_method',
        'amount',
        'default_amount',
        'default_percentage',
        'formula_expression',
        'minimum_amount',
        'maximum_amount',
        'is_taxable',
        'is_pensionable',
        'eligibility_rules',
        'is_active',
        'requires_approval'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'default_amount' => 'decimal:2',
        'default_percentage' => 'decimal:2', 
        'employee_match_amount' => 'decimal:2',
        'employee_match_percentage' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_amount' => 'decimal:2',
        'eligibility_rules' => 'array',
        'is_taxable' => 'boolean',
        'is_pensionable' => 'boolean',
        'has_employee_match' => 'boolean',
        'is_active' => 'boolean',
        'requires_approval' => 'boolean'
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate UUID on creation
        static::creating(function ($template) {
            if (empty($template->uuid)) {
                $template->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PayrollItemCategory::class, 'category_uuid', 'uuid');
    }

    public function employeeItems(): HasMany
    {
        return $this->hasMany(EmployeePayrollItem::class, 'template_uuid', 'uuid');
    }

    // Business Logic Methods
    public function calculateAmount(Employee $employee, float $baseSalary): float
    {
        $result = match($this->calculation_method) {
            'fixed_amount' => (float) ($this->amount ?? $this->default_amount ?? 0),
            'percentage_of_salary' => ($baseSalary * ((float) ($this->default_percentage ?? 0) / 100)),
            'percentage_of_basic' => (($employee->salary ?? 0) * ((float) ($this->default_percentage ?? 0) / 100)),
            'formula' => $this->evaluateFormula($employee, $baseSalary),
            'manual' => 0, // Requires manual input
            default => 0
        };

        // Apply min/max constraints
        if ($this->minimum_amount && $result < (float) $this->minimum_amount) {
            $result = (float) $this->minimum_amount;
        }
        
        if ($this->maximum_amount && $result > (float) $this->maximum_amount) {
            $result = (float) $this->maximum_amount;
        }

        return round($result, 2);
    }

    /**
     * Calculate employer contribution with optional employee matching
     */
    public function calculateEmployerContribution(Employee $employee, float $baseSalary): array
    {
        if ($this->type !== 'employer_contribution') {
            return [
                'employer_amount' => 0,
                'employee_amount' => 0,
                'total_amount' => 0
            ];
        }

        // Calculate employer contribution
        $employerAmount = $this->calculateAmount($employee, $baseSalary);
        $employeeAmount = 0;

        // Calculate employee matching contribution if applicable
        if ($this->has_employee_match) {
            $employeeAmount = $this->calculateEmployeeMatchAmount($employee, $baseSalary, $employerAmount);
        }

        return [
            'employer_amount' => $employerAmount,
            'employee_amount' => $employeeAmount,
            'total_amount' => $employerAmount + $employeeAmount,
            'calculation_details' => [
                'method' => $this->calculation_method,
                'base_salary' => $baseSalary,
                'employer_rate' => $this->default_percentage,
                'employee_rate' => $this->employee_match_percentage,
                'match_logic' => $this->match_logic,
                'contribution_type' => $this->contribution_type
            ]
        ];
    }

    /**
     * Calculate employee matching contribution amount
     */
    private function calculateEmployeeMatchAmount(Employee $employee, float $baseSalary, float $employerAmount): float
    {
        if (!$this->has_employee_match) {
            return 0;
        }

        $result = match($this->match_logic) {
            'equal' => $employerAmount, // 1:1 matching
            'percentage' => $this->calculateEmployeePercentageMatch($baseSalary),
            'custom' => $this->calculateCustomEmployeeMatch($employee, $baseSalary),
            default => 0
        };

        // Apply same constraints as employer contribution for consistency
        if ($this->minimum_amount && $result < (float) $this->minimum_amount) {
            $result = (float) $this->minimum_amount;
        }
        
        if ($this->maximum_amount && $result > (float) $this->maximum_amount) {
            $result = (float) $this->maximum_amount;
        }

        return round($result, 2);
    }

    /**
     * Calculate employee percentage-based match
     */
    private function calculateEmployeePercentageMatch(float $baseSalary): float
    {
        if (!$this->employee_match_percentage) {
            return 0;
        }

        return match($this->calculation_method) {
            'percentage_of_salary' => ($baseSalary * ((float) $this->employee_match_percentage / 100)),
            'percentage_of_basic' => ($baseSalary * ((float) $this->employee_match_percentage / 100)),
            default => 0
        };
    }

    /**
     * Calculate custom employee match amount
     */
    private function calculateCustomEmployeeMatch(Employee $employee, float $baseSalary): float
    {
        return match($this->calculation_method) {
            'fixed_amount' => (float) ($this->employee_match_amount ?? 0),
            'percentage_of_salary' => ($baseSalary * ((float) ($this->employee_match_percentage ?? 0) / 100)),
            'percentage_of_basic' => (($employee->salary ?? 0) * ((float) ($this->employee_match_percentage ?? 0) / 100)),
            default => 0
        };
    }

    public function isApplicableToEmployee(Employee $employee): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $rules = $this->eligibility_rules ?? [];

        // Check department eligibility
        if (isset($rules['departments']) && !empty($rules['departments'])) {
            if (!in_array($employee->department_uuid, $rules['departments'])) {
                return false;
            }
        }

        // Check position eligibility
        if (isset($rules['positions']) && !empty($rules['positions'])) {
            if (!in_array($employee->position_uuid, $rules['positions'])) {
                return false;
            }
        }

        // Check employment type eligibility
        if (isset($rules['employment_types']) && !empty($rules['employment_types'])) {
            if (!in_array($employee->employment_type, $rules['employment_types'])) {
                return false;
            }
        }

        // Check salary range eligibility
        if (isset($rules['min_salary']) && $employee->salary < $rules['min_salary']) {
            return false;
        }

        if (isset($rules['max_salary']) && $employee->salary > $rules['max_salary']) {
            return false;
        }

        return true;
    }

    private function evaluateFormula(Employee $employee, float $baseSalary): float
    {
        // Simple formula evaluation - for safety, we'll implement basic math operations
        $expression = $this->formula_expression;
        
        // Replace variables with actual values
        $basicSalary = $employee->salary ?? 0;
        // Calculate years of service from hire_date
        $yearsOfService = $employee->hire_date ? now()->diffInYears($employee->hire_date) : 0;
        
        $expression = str_replace('{basic_salary}', $basicSalary, $expression);
        $expression = str_replace('{gross_salary}', $baseSalary, $expression);
        $expression = str_replace('{years_of_service}', $yearsOfService, $expression);
        
        // For safety, only allow basic mathematical operations
        // This is a simplified implementation - in production use a proper expression parser
        try {
            // Remove any non-numeric, operator, or decimal characters for security
            if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expression)) {
                Log::error("Unsafe formula expression for template {$this->code}: {$expression}");
                return 0;
            }
            
            // Evaluate the cleaned expression
            $result = eval("return $expression;");
            return is_numeric($result) ? (float) $result : 0;
        } catch (Exception $e) {
            Log::error("Formula evaluation error for template {$this->code}: " . $e->getMessage());
            return 0;
        }
    }

    // Scope methods
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->whereHas('category', function($q) use ($category) {
            $q->where('name', $category);
        });
    }

    public function scopeForEmployee($query, Employee $employee)
    {
        return $query->where(function($q) use ($employee) {
            // Filter by eligibility rules
            $q->where('is_active', true);
            // Additional filtering logic can be added here
        });
    }

    public function scopeForCompany($query, string $companyUuid)
    {
        return $query->where('company_uuid', $companyUuid);
    }

    public function scopeEffectiveForDate($query, $date)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate for employee considering all factors.
     */
    public function calculateForEmployee(Employee $employee, $date = null): array
    {
        if (!$this->isApplicableToEmployee($employee)) {
            return [
                'amount' => 0,
                'employer_amount' => 0,
                'employee_amount' => 0,
                'total_amount' => 0
            ];
        }

        $baseSalary = (float) ($employee->salary ?? 0);

        // Handle employer contributions differently
        if ($this->type === 'employer_contribution') {
            $contribution = $this->calculateEmployerContribution($employee, $baseSalary);
            
            return [
                'amount' => $contribution['employer_amount'], // For backward compatibility
                'employer_amount' => $contribution['employer_amount'],
                'employee_amount' => $contribution['employee_amount'],
                'total_amount' => $contribution['total_amount'],
                'calculation_method' => $this->calculation_method,
                'base_value' => $baseSalary,
                'calculation_details' => $contribution['calculation_details'],
                'type' => 'employer_contribution',
                'contribution_type' => $this->contribution_type,
                'has_employee_match' => $this->has_employee_match,
                'match_logic' => $this->match_logic
            ];
        }

        // Handle regular allowances and deductions
        $amount = $this->calculateAmount($employee, $baseSalary);

        return [
            'amount' => $amount,
            'employer_amount' => 0,
            'employee_amount' => ($this->type === 'deduction') ? $amount : 0,
            'total_amount' => $amount,
            'calculation_method' => $this->calculation_method,
            'base_value' => $baseSalary,
            'type' => $this->type
        ];
    }
}