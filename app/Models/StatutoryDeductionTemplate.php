<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class StatutoryDeductionTemplate extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'jurisdiction_uuid',
        'deduction_type',
        'code',
        'name',
        'description',
        'calculation_method',
        'rules',
        'minimum_salary',
        'maximum_salary',
        'employer_rate',
        'employee_rate',
        'effective_from',
        'effective_to',
        'is_mandatory',
        'is_active'
    ];

    protected $casts = [
        'rules' => 'array',
        'minimum_salary' => 'decimal:2',
        'maximum_salary' => 'decimal:2',
        'employer_rate' => 'decimal:4',
        'employee_rate' => 'decimal:4',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean'
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
    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(TaxJurisdiction::class, 'jurisdiction_uuid', 'uuid');
    }

    // Business Logic Methods
    
    /**
     * Convert salary to annual amount based on pay frequency
     */
    private function annualizeSalary(float $salary, string $payFrequency): float
    {
        return match($payFrequency) {
            'weekly' => $salary * 52,
            'bi_weekly' => $salary * 26,
            'monthly' => $salary * 12,
            'quarterly' => $salary * 4,
            'annually' => $salary,
            default => $salary * 12 // Default to monthly
        };
    }
    
    /**
     * Convert annual amount back to pay period amount
     */
    private function proRateFromAnnual(float $annualAmount, string $payFrequency): float
    {
        return match($payFrequency) {
            'weekly' => $annualAmount / 52,
            'bi_weekly' => $annualAmount / 26,
            'monthly' => $annualAmount / 12,
            'quarterly' => $annualAmount / 4,
            'annually' => $annualAmount,
            default => $annualAmount / 12 // Default to monthly
        };
    }
    
    public function calculateDeduction(float $grossSalary, string $payFrequency = 'monthly'): array
    {
        $cappedSalary = $this->applySalaryCap($grossSalary);
        
        return match($this->calculation_method) {
            'percentage' => $this->calculatePercentageDeduction($cappedSalary, $payFrequency),
            'progressive_bracket' => $this->calculateProgressiveBracketDeduction($cappedSalary, $payFrequency),
            'salary_bracket' => $this->calculateSalaryBracketDeduction($cappedSalary, $payFrequency),
            'flat_amount' => $this->calculateFlatAmountDeduction($payFrequency),
            default => ['employee_amount' => 0, 'employer_amount' => 0, 'calculation_details' => []]
        };
    }

    public function isEffectiveAt(\Carbon\Carbon $date): bool
    {
        if ($date->lt($this->effective_from)) {
            return false;
        }

        if ($this->effective_to && $date->gt($this->effective_to)) {
            return false;
        }

        return $this->is_active;
    }

    private function applySalaryCap(float $salary): float
    {
        if ($this->maximum_salary && $salary > $this->maximum_salary) {
            return $this->maximum_salary;
        }

        return max($salary, $this->minimum_salary ?? 0);
    }

    private function calculatePercentageDeduction(float $salary, string $payFrequency = 'monthly'): array
    {
        $employeeAmount = $salary * $this->employee_rate;
        $employerAmount = $salary * $this->employer_rate;

        return [
            'employee_amount' => round($employeeAmount, 2),
            'employer_amount' => round($employerAmount, 2),
            'calculation_details' => [
                'method' => 'percentage',
                'salary_used' => $salary,
                'employee_rate' => $this->employee_rate,
                'employer_rate' => $this->employer_rate
            ]
        ];
    }

    private function calculateProgressiveBracketDeduction(float $salary, string $payFrequency = 'monthly'): array
    {
        $brackets = $this->rules['brackets'] ?? [];
        $totalTax = 0;
        $details = [];

        // Validate brackets data
        if (!is_array($brackets)) {
            throw new \InvalidArgumentException("Invalid brackets data: expected array, got " . gettype($brackets));
        }

        // Store original salary for display
        $originalSalary = $salary;
        
        // Annualize the salary for tax calculation (tax tables are annual)
        $annualSalary = $this->annualizeSalary($salary, $payFrequency);

        // Sort brackets by min value to ensure proper order
        usort($brackets, function($a, $b) {
            return $a['min'] <=> $b['min'];
        });

        foreach ($brackets as $bracket) {
            $bracketMin = $bracket['min'];
            $bracketMax = $bracket['max'];
            
            // Skip if annual salary doesn't reach this bracket
            if ($annualSalary <= $bracketMin) {
                continue;
            }
            
            // Calculate the amount of annual salary that falls within this bracket
            $salaryInBracket = 0;
            
            if ($bracketMax === null) {
                // Top bracket - no upper limit
                $salaryInBracket = $annualSalary - $bracketMin;
            } else {
                // Middle bracket - has upper limit
                $salaryInBracket = min($annualSalary, $bracketMax) - $bracketMin;
            }
            
            // Only process if there's salary in this bracket
            if ($salaryInBracket > 0) {
                $taxInBracket = $salaryInBracket * $bracket['rate'];
                $totalTax += $taxInBracket;

                $details[] = [
                    'bracket' => $bracket,
                    'taxable_amount' => $salaryInBracket,
                    'tax_amount' => $taxInBracket
                ];
            }
        }

        // Apply rebates based on age (for now, apply only primary rebate)
        // TODO: Add age parameter to calculation for proper rebate application
        $rebates = $this->rules['rebates'] ?? [];
        
        // Apply primary rebate (available to all taxpayers under 65)
        if (isset($rebates['primary'])) {
            $rebateAmount = $rebates['primary'];
            $totalTax -= $rebateAmount;
            $details[] = [
                'type' => 'rebate',
                'rebate_type' => 'primary',
                'amount' => -$rebateAmount
            ];
        }
        
        // Note: Secondary and tertiary rebates should only be applied based on taxpayer age
        // This would require passing age information to the calculation method

        // Pro-rate the annual tax back to the pay period
        $finalTaxAmount = max(0, $totalTax);
        $proRatedTaxAmount = $this->proRateFromAnnual($finalTaxAmount, $payFrequency);
        
        $employeeAmount = $proRatedTaxAmount;
        $employerAmount = $originalSalary * $this->employer_rate;

        return [
            'employee_amount' => round($employeeAmount, 2),
            'employer_amount' => round($employerAmount, 2),
            'calculation_details' => [
                'method' => 'progressive_bracket',
                'salary_used' => $originalSalary,
                'annual_salary_used' => $annualSalary,
                'annual_tax_calculated' => $finalTaxAmount,
                'pay_frequency' => $payFrequency,
                'bracket_calculations' => $details
            ]
        ];
    }

    private function calculateSalaryBracketDeduction(float $salary, string $payFrequency = 'monthly'): array
    {
        $brackets = $this->rules['brackets'] ?? [];
        
        // Validate brackets data
        if (!is_array($brackets)) {
            throw new \InvalidArgumentException("Invalid brackets data: expected array, got " . gettype($brackets));
        }
        
        $deductionAmount = 0;

        foreach ($brackets as $bracket) {
            if ($salary >= $bracket['min'] && ($bracket['max'] === null || $salary <= $bracket['max'])) {
                $deductionAmount = $bracket['amount'];
                break;
            }
        }

        return [
            'employee_amount' => round($deductionAmount, 2),
            'employer_amount' => round($salary * $this->employer_rate, 2),
            'calculation_details' => [
                'method' => 'salary_bracket',
                'salary_used' => $salary,
                'bracket_amount' => $deductionAmount
            ]
        ];
    }

    private function calculateFlatAmountDeduction(string $payFrequency = 'monthly'): array
    {
        $amount = $this->rules['amount'] ?? 0;

        return [
            'employee_amount' => round($amount, 2),
            'employer_amount' => 0,
            'calculation_details' => [
                'method' => 'flat_amount',
                'amount' => $amount
            ]
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

    public function scopeOfType($query, string $type)
    {
        return $query->where('deduction_type', $type);
    }

    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_mandatory', false);
    }

    public function scopeEffectiveAt($query, \Carbon\Carbon $date)
    {
        return $query->where('effective_from', '<=', $date)
                    ->where(function($q) use ($date) {
                        $q->whereNull('effective_to')
                          ->orWhere('effective_to', '>=', $date);
                    });
    }

    public function scopeForJurisdiction($query, string $jurisdictionUuid)
    {
        return $query->where('jurisdiction_uuid', $jurisdictionUuid);
    }
}