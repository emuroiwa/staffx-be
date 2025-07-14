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
    public function calculateDeduction(float $grossSalary): array
    {
        $cappedSalary = $this->applySalaryCap($grossSalary);
        
        return match($this->calculation_method) {
            'percentage' => $this->calculatePercentageDeduction($cappedSalary),
            'progressive_bracket' => $this->calculateProgressiveBracketDeduction($cappedSalary),
            'salary_bracket' => $this->calculateSalaryBracketDeduction($cappedSalary),
            'flat_amount' => $this->calculateFlatAmountDeduction(),
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

    private function calculatePercentageDeduction(float $salary): array
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

    private function calculateProgressiveBracketDeduction(float $salary): array
    {
        $brackets = $this->rules['brackets'] ?? [];
        $totalTax = 0;
        $details = [];

        // Validate brackets data
        if (!is_array($brackets)) {
            throw new \InvalidArgumentException("Invalid brackets data: expected array, got " . gettype($brackets));
        }

        // Sort brackets by min value to ensure proper order
        usort($brackets, function($a, $b) {
            return $a['min'] <=> $b['min'];
        });

        foreach ($brackets as $bracket) {
            $bracketMin = $bracket['min'];
            $bracketMax = $bracket['max'];
            
            // Skip if salary doesn't reach this bracket
            if ($salary <= $bracketMin) {
                continue;
            }
            
            // Calculate the amount of salary that falls within this bracket
            $salaryInBracket = 0;
            
            if ($bracketMax === null) {
                // Top bracket - no upper limit
                $salaryInBracket = $salary - $bracketMin;
            } else {
                // Middle bracket - has upper limit
                $salaryInBracket = min($salary, $bracketMax) - $bracketMin;
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

        // Apply rebates if specified
        $rebates = $this->rules['rebates'] ?? [];
        foreach ($rebates as $rebateType => $amount) {
            $totalTax -= $amount;
            $details[] = [
                'type' => 'rebate',
                'rebate_type' => $rebateType,
                'amount' => -$amount
            ];
        }

        $employeeAmount = max(0, $totalTax);
        $employerAmount = $salary * $this->employer_rate;

        return [
            'employee_amount' => round($employeeAmount, 2),
            'employer_amount' => round($employerAmount, 2),
            'calculation_details' => [
                'method' => 'progressive_bracket',
                'salary_used' => $salary,
                'bracket_calculations' => $details
            ]
        ];
    }

    private function calculateSalaryBracketDeduction(float $salary): array
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

    private function calculateFlatAmountDeduction(): array
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