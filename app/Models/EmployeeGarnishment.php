<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class EmployeeGarnishment extends EmployeePayrollItem
{
    protected $table = 'employee_payroll_items';

    protected static function booted()
    {
        parent::booted();
        
        static::addGlobalScope('garnishment', function (Builder $builder) {
            $builder->where('type', 'garnishment');
        });

        // Set default values for garnishment items
        static::creating(function ($garnishment) {
            $garnishment->type = 'garnishment';
            $garnishment->is_recurring = true;
            
            // Set default priority based on garnishment type
            if (empty($garnishment->priority_order)) {
                $garnishment->priority_order = static::getDefaultPriority($garnishment->garnishment_type);
            }
        });
    }

    /**
     * Get default priority order for garnishment types
     */
    protected static function getDefaultPriority(string $garnishmentType): int
    {
        $priorities = [
            'child_support' => 1,      // Highest priority
            'tax_levy' => 2,           // Federal/state tax levies
            'student_loan' => 3,       // Federal student loans
            'bankruptcy' => 4,         // Bankruptcy court orders
            'wage_garnishment' => 5,   // General wage garnishments
            'other' => 6               // Other types
        ];

        return $priorities[$garnishmentType] ?? 5;
    }

    /**
     * Create a new garnishment with validation
     */
    public static function createGarnishment(array $data): self
    {
        $garnishment = new static();
        $garnishment->validateGarnishmentData($data);
        
        // Set garnishment-specific defaults
        $data['type'] = 'garnishment';
        $data['is_recurring'] = true;
        $data['status'] = $data['status'] ?? 'pending_approval';
        
        // Set priority if not provided
        if (empty($data['priority_order'])) {
            $data['priority_order'] = static::getDefaultPriority($data['garnishment_type']);
        }

        // Set effective dates
        $data['effective_from'] = $data['garnishment_start_date'] ?? now()->toDateString();
        $data['effective_to'] = $data['garnishment_end_date'] ?? null;

        return static::create($data);
    }

    /**
     * Validate garnishment data
     */
    protected function validateGarnishmentData(array $data): void
    {
        $required = ['employee_uuid', 'garnishment_type', 'calculation_method'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Required field missing: {$field}");
            }
        }

        // Validate garnishment type
        $validTypes = ['wage_garnishment', 'child_support', 'tax_levy', 'student_loan', 'bankruptcy', 'other'];
        if (!in_array($data['garnishment_type'], $validTypes)) {
            throw new \InvalidArgumentException("Invalid garnishment type: {$data['garnishment_type']}");
        }

        // Validate calculation method
        $validMethods = ['fixed_amount', 'percentage_of_salary', 'percentage_of_basic', 'formula', 'manual'];
        if (!in_array($data['calculation_method'], $validMethods)) {
            throw new \InvalidArgumentException("Invalid calculation method: {$data['calculation_method']}");
        }

        // Validate amount/percentage based on calculation method
        if (in_array($data['calculation_method'], ['fixed_amount', 'manual']) && empty($data['amount'])) {
            throw new \InvalidArgumentException("Amount is required for {$data['calculation_method']} calculation method");
        }

        if (in_array($data['calculation_method'], ['percentage_of_salary', 'percentage_of_basic']) && empty($data['percentage'])) {
            throw new \InvalidArgumentException("Percentage is required for {$data['calculation_method']} calculation method");
        }

        // Validate legal limits
        if (!empty($data['maximum_percentage']) && $data['maximum_percentage'] > 100) {
            throw new \InvalidArgumentException("Maximum percentage cannot exceed 100%");
        }
    }

    /**
     * Get all active garnishments for an employee, ordered by priority
     */
    public static function getActiveGarnishments(string $employeeUuid, Carbon $date = null): array
    {
        $date = $date ?? now();
        
        return static::forEmployee($employeeUuid)
            ->active()
            ->effectiveForDate($date)
            ->byPriority()
            ->get()
            ->filter(function ($garnishment) use ($date) {
                return $garnishment->isEffectiveForGarnishment($date);
            })
            ->values()
            ->toArray();
    }

    /**
     * Calculate total garnishment amount for an employee
     */
    public static function calculateTotalGarnishments(string $employeeUuid, float $disposableIncome, Carbon $date = null): array
    {
        $garnishments = static::getActiveGarnishments($employeeUuid, $date);
        $totalGarnished = 0;
        $processedGarnishments = [];
        $remainingIncome = $disposableIncome;

        foreach ($garnishments as $garnishment) {
            $garnishmentModel = static::find($garnishment['uuid']);
            $amount = $garnishmentModel->calculateGarnishmentAmount($remainingIncome, $date);
            
            if ($amount > 0) {
                $processedGarnishments[] = [
                    'uuid' => $garnishment['uuid'],
                    'name' => $garnishment['name'],
                    'type' => $garnishment['garnishment_type'],
                    'amount' => $amount,
                    'priority' => $garnishment['priority_order'],
                    'court_order' => $garnishment['court_order_number'],
                    'authority' => $garnishment['garnishment_authority']
                ];
                
                $totalGarnished += $amount;
                $remainingIncome -= $amount;
            }
        }

        return [
            'total_garnished' => $totalGarnished,
            'remaining_disposable_income' => $remainingIncome,
            'garnishments' => $processedGarnishments
        ];
    }

    /**
     * Process garnishment for payroll
     */
    public function processForPayroll(float $disposableIncome, Carbon $payrollDate): array
    {
        $amount = $this->calculateGarnishmentAmount($disposableIncome, $payrollDate);
        
        if ($amount > 0) {
            $this->updateGarnishedAmount($amount);
        }

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => $this->garnishment_type,
            'amount' => $amount,
            'priority' => $this->priority_order,
            'court_order' => $this->court_order_number,
            'authority' => $this->garnishment_authority,
            'remaining_amount' => $this->getRemainingGarnishmentAmount(),
            'status' => $this->garnishment_status
        ];
    }

    /**
     * Get garnishment summary for reporting
     */
    public function getSummary(): array
    {
        return [
            'uuid' => $this->uuid,
            'employee_uuid' => $this->employee_uuid,
            'employee_name' => $this->employee->full_name ?? 'Unknown',
            'type' => $this->garnishment_type,
            'authority' => $this->garnishment_authority,
            'court_order' => $this->court_order_number,
            'start_date' => $this->garnishment_start_date,
            'end_date' => $this->garnishment_end_date,
            'total_amount' => $this->total_amount_to_garnish,
            'amount_garnished' => $this->amount_garnished_to_date,
            'remaining_amount' => $this->getRemainingGarnishmentAmount(),
            'status' => $this->garnishment_status,
            'priority' => $this->priority_order,
            'calculation_method' => $this->calculation_method,
            'amount' => $this->amount,
            'percentage' => $this->percentage,
            'maximum_percentage' => $this->maximum_percentage,
            'contact_info' => $this->contact_information,
            'legal_reference' => $this->legal_reference,
            'is_active' => $this->status === 'active'
        ];
    }

    /**
     * Get garnishment types with labels
     */
    public static function getGarnishmentTypes(): array
    {
        return [
            'wage_garnishment' => 'Wage Garnishment',
            'child_support' => 'Child Support',
            'tax_levy' => 'Tax Levy',
            'student_loan' => 'Student Loan',
            'bankruptcy' => 'Bankruptcy',
            'other' => 'Other'
        ];
    }

    /**
     * Get calculation methods with labels
     */
    public static function getCalculationMethods(): array
    {
        return [
            'fixed_amount' => 'Fixed Amount',
            'percentage_of_salary' => 'Percentage of Disposable Income',
            'percentage_of_basic' => 'Percentage of Basic Salary',
            'formula' => 'Formula Based',
            'manual' => 'Manual Entry'
        ];
    }

    /**
     * Calculate maximum allowable garnishment amount based on legal limits
     */
    public function calculateMaxAllowableGarnishment(float $disposableIncome): float
    {
        // Validate that this is actually a garnishment
        if ($this->type !== 'garnishment') {
            throw new \InvalidArgumentException('Method can only be called on garnishment items');
        }

        // Use custom maximum percentage if set
        if ($this->maximum_percentage > 0) {
            return $disposableIncome * ($this->maximum_percentage / 100);
        }

        // Default legal limits based on garnishment type
        $legalLimits = [
            'wage_garnishment' => 0.25, // 25% of disposable income
            'child_support' => 0.50,    // Up to 50% for child support (can be higher with multiple orders)
            'tax_levy' => 0.15,         // 15% for tax levies (IRS guidelines)
            'student_loan' => 0.15,     // 15% for federal student loans
            'bankruptcy' => 0.25,       // 25% for bankruptcy court orders
            'other' => 0.25            // Default 25% for other types
        ];

        $limit = $legalLimits[$this->garnishment_type] ?? 0.25;
        $maxAmount = $disposableIncome * $limit;

        // Additional validation for child support - can be higher in some cases
        if ($this->garnishment_type === 'child_support') {
            // If employee supports another spouse/child, limit is 50%
            // If no other dependents, limit can be up to 60%
            // For this implementation, we'll use the configured percentage or 50% default
            $maxAmount = $disposableIncome * min(($this->maximum_percentage ?: 50) / 100, 0.60);
        }

        return round($maxAmount, 2);
    }

    /**
     * Get detailed garnishment calculation breakdown
     */
    public function getCalculationBreakdown(float $disposableIncome): array
    {
        $maxAllowable = $this->calculateMaxAllowableGarnishment($disposableIncome);
        
        $calculatedAmount = match($this->calculation_method) {
            'fixed_amount' => $this->amount ?? 0,
            'percentage_of_salary' => ($disposableIncome * ($this->percentage / 100)),
            'percentage_of_basic' => ($this->employee->salary * ($this->percentage / 100)),
            'formula' => $this->evaluateFormula($disposableIncome),
            'manual' => $this->amount ?? 0
        };

        $finalAmount = min($calculatedAmount, $maxAllowable);

        // Check remaining amount if total is set
        if ($this->total_amount_to_garnish > 0) {
            $remainingAmount = $this->total_amount_to_garnish - $this->amount_garnished_to_date;
            $finalAmount = min($finalAmount, $remainingAmount);
        }

        return [
            'disposable_income' => $disposableIncome,
            'calculated_amount' => $calculatedAmount,
            'max_allowable_amount' => $maxAllowable,
            'final_amount' => max(0, $finalAmount),
            'garnishment_type' => $this->garnishment_type,
            'calculation_method' => $this->calculation_method,
            'percentage_used' => $disposableIncome > 0 ? (max(0, $finalAmount) / $disposableIncome) * 100 : 0,
            'legal_limit_percentage' => $this->getLegalLimitPercentage(),
            'remaining_total' => $this->getRemainingGarnishmentAmount(),
            'is_limited_by_legal' => $calculatedAmount > $maxAllowable,
            'is_limited_by_remaining' => $this->total_amount_to_garnish > 0 && 
                                       $calculatedAmount > ($this->total_amount_to_garnish - $this->amount_garnished_to_date)
        ];
    }

    /**
     * Get the legal limit percentage for this garnishment type
     */
    public function getLegalLimitPercentage(): float
    {
        if ($this->maximum_percentage > 0) {
            return $this->maximum_percentage;
        }

        $legalLimits = [
            'wage_garnishment' => 25.0,
            'child_support' => 50.0,
            'tax_levy' => 15.0,
            'student_loan' => 15.0,
            'bankruptcy' => 25.0,
            'other' => 25.0
        ];

        return $legalLimits[$this->garnishment_type] ?? 25.0;
    }
}