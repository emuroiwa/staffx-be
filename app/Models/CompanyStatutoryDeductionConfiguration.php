<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CompanyStatutoryDeductionConfiguration extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'company_uuid',
        'statutory_deduction_template_uuid',
        'employer_covers_employee_portion',
        'is_taxable_if_employer_paid',
        'is_active',
        'employer_rate_override',
        'employee_rate_override',
        'minimum_salary_override',
        'maximum_salary_override',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'employer_covers_employee_portion' => 'boolean',
        'is_taxable_if_employer_paid' => 'boolean',
        'is_active' => 'boolean',
        'employer_rate_override' => 'decimal:4',
        'employee_rate_override' => 'decimal:4',
        'minimum_salary_override' => 'decimal:2',
        'maximum_salary_override' => 'decimal:2',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime'
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate UUID on creation
        static::creating(function ($config) {
            if (empty($config->uuid)) {
                $config->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    public function statutoryDeductionTemplate(): BelongsTo
    {
        return $this->belongsTo(StatutoryDeductionTemplate::class, 'statutory_deduction_template_uuid', 'uuid');
    }

    // Business Logic Methods
    
    /**
     * Get the effective employer rate (override or template default)
     */
    public function getEffectiveEmployerRate(): float
    {
        return $this->employer_rate_override ?? $this->statutoryDeductionTemplate->employer_rate ?? 0.0;
    }

    /**
     * Get the effective employee rate (override or template default)
     */
    public function getEffectiveEmployeeRate(): float
    {
        return $this->employee_rate_override ?? $this->statutoryDeductionTemplate->employee_rate ?? 0.0;
    }

    /**
     * Get the effective minimum salary (override or template default)
     */
    public function getEffectiveMinimumSalary(): ?float
    {
        return $this->minimum_salary_override ?? $this->statutoryDeductionTemplate->minimum_salary;
    }

    /**
     * Get the effective maximum salary (override or template default)
     */
    public function getEffectiveMaximumSalary(): ?float
    {
        return $this->maximum_salary_override ?? $this->statutoryDeductionTemplate->maximum_salary;
    }

    /**
     * Check if configuration is effective at given date
     */
    public function isEffectiveAt(Carbon $date): bool
    {
        if ($date->lt($this->effective_from)) {
            return false;
        }

        if ($this->effective_to && $date->gt($this->effective_to)) {
            return false;
        }

        return $this->is_active;
    }

    /**
     * Calculate deduction amounts considering company configuration
     */
    public function calculateDeduction(float $grossSalary, string $payFrequency = 'monthly'): array
    {
        $template = $this->statutoryDeductionTemplate;
        
        // Apply salary caps using effective values
        $minSalary = $this->getEffectiveMinimumSalary() ?? 0;
        $maxSalary = $this->getEffectiveMaximumSalary();
        
        $cappedSalary = $grossSalary;
        if ($maxSalary && $grossSalary > $maxSalary) {
            $cappedSalary = $maxSalary;
        }
        $cappedSalary = max($cappedSalary, $minSalary);

        // Calculate base deduction using template method
        $baseCalculation = $template->calculateDeduction($cappedSalary, $payFrequency);
        
        // Apply company-specific rates if overridden
        if ($this->employer_rate_override !== null || $this->employee_rate_override !== null) {
            $employerRate = $this->getEffectiveEmployerRate();
            $employeeRate = $this->getEffectiveEmployeeRate();
            
            $employeeAmount = $cappedSalary * $employeeRate;
            $employerAmount = $cappedSalary * $employerRate;
            
            $baseCalculation['employee_amount'] = round($employeeAmount, 2);
            $baseCalculation['employer_amount'] = round($employerAmount, 2);
        }

        // Determine who pays what based on configuration
        $finalEmployeeAmount = $baseCalculation['employee_amount'];
        $finalEmployerAmount = $baseCalculation['employer_amount'];
        
        if ($this->employer_covers_employee_portion) {
            // Employer covers employee portion
            $finalEmployerAmount += $finalEmployeeAmount;
            $finalEmployeeAmount = 0;
        }

        return [
            'employee_amount' => $finalEmployeeAmount,
            'employer_amount' => $finalEmployerAmount,
            'employer_covers_employee_portion' => $this->employer_covers_employee_portion,
            'is_taxable_if_employer_paid' => $this->is_taxable_if_employer_paid,
            'calculation_details' => array_merge($baseCalculation['calculation_details'], [
                'company_configuration' => [
                    'employer_covers_employee_portion' => $this->employer_covers_employee_portion,
                    'is_taxable_if_employer_paid' => $this->is_taxable_if_employer_paid,
                    'employer_rate_override' => $this->employer_rate_override,
                    'employee_rate_override' => $this->employee_rate_override,
                    'effective_employer_rate' => $this->getEffectiveEmployerRate(),
                    'effective_employee_rate' => $this->getEffectiveEmployeeRate()
                ]
            ])
        ];
    }

    // Scope Methods
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeForCompany($query, string $companyUuid)
    {
        return $query->where('company_uuid', $companyUuid);
    }

    public function scopeEffectiveAt($query, Carbon $date)
    {
        return $query->where('effective_from', '<=', $date)
                    ->where(function($q) use ($date) {
                        $q->whereNull('effective_to')
                          ->orWhere('effective_to', '>=', $date);
                    });
    }

    public function scopeEmployerCoversEmployee($query)
    {
        return $query->where('employer_covers_employee_portion', true);
    }

    public function scopeTaxableIfEmployerPaid($query)
    {
        return $query->where('is_taxable_if_employer_paid', true);
    }
}