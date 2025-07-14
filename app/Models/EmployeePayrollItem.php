<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasUuid;
use Carbon\Carbon;

class EmployeePayrollItem extends Model
{
    use HasUuid;

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
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_recurring' => 'boolean',
        'approved_at' => 'datetime'
    ];

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

    public function history(): HasMany
    {
        return $this->hasMany(PayrollItemHistory::class, 'employee_payroll_item_uuid', 'uuid');
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'employee_payroll_item_uuid', 'uuid');
    }

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
        
        // Replace variables
        $expression = str_replace('{basic_salary}', $this->employee->salary, $expression);
        $expression = str_replace('{gross_salary}', $baseSalary, $expression);
        $expression = str_replace('{years_of_service}', $this->employee->years_of_service ?? 0, $expression);
        
        try {
            return eval("return $expression;");
        } catch (Exception $e) {
            \Log::error("Formula evaluation error for employee payroll item {$this->uuid}: " . $e->getMessage());
            return 0;
        }
    }

    private function logChange(string $action, array $oldValues, array $newValues, string $userId, string $reason = null)
    {
        PayrollItemHistory::create([
            'employee_payroll_item_uuid' => $this->uuid,
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
}