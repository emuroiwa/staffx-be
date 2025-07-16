<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class EmployeePayrollItem extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'employee_uuid',
        'template_uuid',
        'statutory_template_uuid',
        'code',
        'name', 
        'type',
        'calculation_method',
        'amount',
        'percentage',
        'formula_expression',
        'effective_from',
        'effective_to',
        'is_recurring',
        'status',
        'approved_by',
        'approved_at',
        'notes',
        // Garnishment fields
        'court_order_number',
        'garnishment_type',
        'garnishment_authority',
        'maximum_percentage',
        'priority_order',
        'contact_information',
        'legal_reference',
        'garnishment_start_date',
        'garnishment_end_date',
        'total_amount_to_garnish',
        'amount_garnished_to_date'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_recurring' => 'boolean',
        'approved_at' => 'datetime',
        // Garnishment field casts
        'maximum_percentage' => 'decimal:2',
        'priority_order' => 'integer',
        'contact_information' => 'array',
        'garnishment_start_date' => 'date',
        'garnishment_end_date' => 'date',
        'total_amount_to_garnish' => 'decimal:2',
        'amount_garnished_to_date' => 'decimal:2'
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate UUID on creation
        static::creating(function ($item) {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_uuid', 'uuid');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CompanyPayrollTemplate::class, 'template_uuid', 'uuid');
    }

    public function statutoryTemplate(): BelongsTo
    {
        return $this->belongsTo(StatutoryDeductionTemplate::class, 'statutory_template_uuid', 'uuid');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'uuid');
    }

    // Note: PayrollItemHistory and PayrollItem models would be created separately
    // public function history(): HasMany
    // {
    //     return $this->hasMany(PayrollItemHistory::class, 'employee_payroll_item_uuid', 'uuid');
    // }

    // public function payrollItems(): HasMany
    // {
    //     return $this->hasMany(PayrollItem::class, 'employee_payroll_item_uuid', 'uuid');
    // }

    // Business Logic Methods
    public function calculateAmount(float $baseSalary, Carbon $payrollDate = null): float
    {
        $payrollDate = $payrollDate ?? now();

        // Check if item is effective for this payroll period
        if (!$this->isEffectiveForDate($payrollDate)) {
            return 0;
        }

        return match($this->calculation_method) {
            'fixed_amount' => $this->amount ?? 0,
            'percentage_of_salary' => ($baseSalary * ($this->percentage / 100)),
            'percentage_of_basic' => ($this->employee->salary * ($this->percentage / 100)),
            'formula' => $this->evaluateFormula($baseSalary),
            'manual' => $this->amount ?? 0 // Manual amounts are set per payroll
        };
    }

    public function isEffectiveForDate(Carbon $date): bool
    {
        if ($date->lt($this->effective_from)) {
            return false;
        }

        if ($this->effective_to && $date->gt($this->effective_to)) {
            return false;
        }

        return $this->status === 'active';
    }

    public function isStatutory(): bool
    {
        return !is_null($this->statutory_template_uuid);
    }

    public function requiresApproval(): bool
    {
        return $this->template?->requires_approval ?? false;
    }

    public function approve(User $user, string $reason = null): bool
    {
        if ($this->status !== 'pending_approval') {
            return false;
        }

        $oldValues = $this->getOriginal();
        
        $this->update([
            'status' => 'active',
            'approved_by' => $user->uuid,
            'approved_at' => now()
        ]);

        // Log the approval
        $this->logChange('approved', $oldValues, $this->toArray(), $user->uuid, $reason);

        return true;
    }

    public function suspend(User $user, string $reason): bool
    {
        $oldValues = $this->getOriginal();
        
        $this->update(['status' => 'suspended']);
        
        $this->logChange('suspended', $oldValues, $this->toArray(), $user->uuid, $reason);
        
        return true;
    }

    public function cancel(User $user, string $reason): bool
    {
        $oldValues = $this->getOriginal();
        
        $this->update([
            'status' => 'cancelled',
            'effective_to' => now()->toDateString()
        ]);
        
        $this->logChange('cancelled', $oldValues, $this->toArray(), $user->uuid, $reason);
        
        return true;
    }

    private function evaluateFormula(float $baseSalary): float
    {
        $expression = $this->formula_expression;
        
        // Replace variables with actual values
        $basicSalary = $this->employee->salary ?? 0;
        // Calculate years of service from hire_date
        $yearsOfService = $this->employee->hire_date ? now()->diffInYears($this->employee->hire_date) : 0;
        
        $expression = str_replace('{basic_salary}', $basicSalary, $expression);
        $expression = str_replace('{gross_salary}', $baseSalary, $expression);
        $expression = str_replace('{years_of_service}', $yearsOfService, $expression);
        
        // For safety, only allow basic mathematical operations
        try {
            // Remove any non-numeric, operator, or decimal characters for security
            if (!preg_match('/^[0-9+\-*\/().\s]+$/', $expression)) {
                Log::error("Unsafe formula expression for employee payroll item {$this->uuid}: {$expression}");
                return 0;
            }
            
            // Evaluate the cleaned expression
            $result = eval("return $expression;");
            return is_numeric($result) ? (float) $result : 0;
        } catch (Exception $e) {
            Log::error("Formula evaluation error for employee payroll item {$this->uuid}: " . $e->getMessage());
            return 0;
        }
    }

    private function logChange(string $action, array $oldValues, array $newValues, string $userId, string $reason = null)
    {
        // TODO: Implement PayrollItemHistory model and logging
        Log::info("Employee payroll item {$action}", [
            'item_uuid' => $this->uuid,
            'employee_uuid' => $this->employee_uuid,
            'action' => $action,
            'previous_values' => $oldValues,
            'new_values' => $newValues,
            'changed_by' => $userId,
            'reason' => $reason,
            'changed_at' => now()
        ]);
    }

    // Scope methods
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeEffectiveForDate($query, Carbon $date)
    {
        return $query->where('effective_from', '<=', $date)
                    ->where(function($q) use ($date) {
                        $q->whereNull('effective_to')
                          ->orWhere('effective_to', '>=', $date);
                    });
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeOneTime($query)
    {
        return $query->where('is_recurring', false);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    public function scopeForEmployee($query, string $employeeUuid)
    {
        return $query->where('employee_uuid', $employeeUuid);
    }

    public function scopeGarnishments($query)
    {
        return $query->where('type', 'garnishment');
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority_order', 'asc');
    }

    // Garnishment-specific methods
    public function isGarnishment(): bool
    {
        return $this->type === 'garnishment';
    }

    public function calculateGarnishmentAmount(float $disposableIncome, Carbon $payrollDate = null): float
    {
        if (!$this->isGarnishment()) {
            return 0;
        }

        $payrollDate = $payrollDate ?? now();

        // Check if garnishment is effective for this payroll period
        if (!$this->isEffectiveForGarnishment($payrollDate)) {
            return 0;
        }

        // Calculate maximum allowable garnishment
        $maxAllowableAmount = $this->calculateMaxAllowableGarnishment($disposableIncome);
        
        // Get the calculated amount based on calculation method
        $calculatedAmount = match($this->calculation_method) {
            'fixed_amount' => $this->amount ?? 0,
            'percentage_of_salary' => ($disposableIncome * ($this->percentage / 100)),
            'percentage_of_basic' => ($this->employee->salary * ($this->percentage / 100)),
            'formula' => $this->evaluateFormula($disposableIncome),
            'manual' => $this->amount ?? 0
        };

        // Apply the lesser of calculated amount or maximum allowable
        $garnishmentAmount = min($calculatedAmount, $maxAllowableAmount);

        // Check if we've reached the total amount to garnish
        if ($this->total_amount_to_garnish > 0) {
            $remainingAmount = $this->total_amount_to_garnish - $this->amount_garnished_to_date;
            $garnishmentAmount = min($garnishmentAmount, $remainingAmount);
        }

        return max(0, $garnishmentAmount);
    }

    protected function calculateMaxAllowableGarnishment(float $disposableIncome): float
    {
        if ($this->maximum_percentage > 0) {
            return $disposableIncome * ($this->maximum_percentage / 100);
        }

        // Default legal limits based on garnishment type
        $legalLimits = [
            'wage_garnishment' => 0.25, // 25% of disposable income
            'child_support' => 0.50,    // Up to 50% for child support
            'tax_levy' => 0.15,         // 15% for tax levies
            'student_loan' => 0.15,     // 15% for student loans
            'bankruptcy' => 0.25,       // 25% for bankruptcy
            'other' => 0.25            // Default 25%
        ];

        $limit = $legalLimits[$this->garnishment_type] ?? 0.25;
        return $disposableIncome * $limit;
    }

    protected function isEffectiveForGarnishment(Carbon $date): bool
    {
        if ($this->garnishment_start_date && $date->lt($this->garnishment_start_date)) {
            return false;
        }

        if ($this->garnishment_end_date && $date->gt($this->garnishment_end_date)) {
            return false;
        }

        // Check if total amount has been garnished
        if ($this->total_amount_to_garnish > 0 && 
            $this->amount_garnished_to_date >= $this->total_amount_to_garnish) {
            return false;
        }

        return $this->status === 'active';
    }

    public function updateGarnishedAmount(float $amount): void
    {
        $this->increment('amount_garnished_to_date', $amount);
        
        // If total amount has been garnished, mark as completed
        if ($this->total_amount_to_garnish > 0 && 
            $this->amount_garnished_to_date >= $this->total_amount_to_garnish) {
            $this->update(['status' => 'completed']);
        }
    }

    public function getGarnishmentStatusAttribute(): string
    {
        if ($this->type !== 'garnishment') {
            return 'not_garnishment';
        }

        if ($this->total_amount_to_garnish > 0) {
            $percentage = ($this->amount_garnished_to_date / $this->total_amount_to_garnish) * 100;
            if ($percentage >= 100) {
                return 'completed';
            }
            return 'in_progress';
        }

        return 'ongoing';
    }

    public function getRemainingGarnishmentAmount(): float
    {
        if ($this->total_amount_to_garnish > 0) {
            return max(0, $this->total_amount_to_garnish - $this->amount_garnished_to_date);
        }
        return 0;
    }
}