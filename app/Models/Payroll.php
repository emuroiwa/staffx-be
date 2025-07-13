<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Payroll extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'employee_uuid',
        'payroll_number',
        'period_start',
        'period_end',
        'base_salary',
        'overtime_hours',
        'overtime_rate',
        'overtime_pay',
        'bonus',
        'allowances',
        'gross_salary',
        'deductions',
        'total_deductions',
        'net_salary',
        'currency_uuid',
        'status',
        'pay_date',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'pay_date' => 'date',
        'base_salary' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'bonus' => 'decimal:2',
        'allowances' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'deductions' => 'array',
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
        static::creating(function ($payroll) {
            if (empty($payroll->uuid)) {
                $payroll->uuid = (string) Str::uuid();
            }
        });

        // Auto-generate payroll number if not provided
        static::creating(function ($payroll) {
            if (empty($payroll->payroll_number)) {
                $payroll->payroll_number = static::generatePayrollNumber();
            }
            
            // Auto-calculate fields
            $payroll->gross_salary = $payroll->base_salary + $payroll->overtime_pay + $payroll->bonus + $payroll->allowances;
            $payroll->net_salary = $payroll->gross_salary - $payroll->total_deductions;
        });

        static::updating(function ($payroll) {
            // Recalculate on update
            $payroll->gross_salary = $payroll->base_salary + $payroll->overtime_pay + $payroll->bonus + $payroll->allowances;
            $payroll->net_salary = $payroll->gross_salary - $payroll->total_deductions;
        });
    }

    /**
     * Get the company that owns the payroll.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    /**
     * Get the employee that owns the payroll.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_uuid', 'uuid');
    }

    /**
     * Get the currency for this payroll.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_uuid', 'uuid');
    }

    /**
     * Check if payroll is processed.
     */
    public function isProcessed(): bool
    {
        return in_array($this->status, ['processed', 'paid']);
    }

    /**
     * Check if payroll is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Get formatted gross salary.
     */
    public function getFormattedGrossSalaryAttribute(): string
    {
        $symbol = $this->currency ? $this->currency->symbol : '$';
        return $symbol . number_format($this->gross_salary, 2);
    }

    /**
     * Get formatted net salary.
     */
    public function getFormattedNetSalaryAttribute(): string
    {
        $symbol = $this->currency ? $this->currency->symbol : '$';
        return $symbol . number_format($this->net_salary, 2);
    }

    /**
     * Generate unique payroll number.
     */
    protected static function generatePayrollNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $prefix = 'PAY' . $year . $month;
        
        $lastPayroll = static::withoutGlobalScope('company')
            ->where('payroll_number', 'like', $prefix . '%')
            ->orderBy('payroll_number', 'desc')
            ->first();

        if ($lastPayroll && preg_match('/(\d+)$/', $lastPayroll->payroll_number, $matches)) {
            $number = intval($matches[1]) + 1;
        } else {
            $number = 1;
        }

        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate total deductions from deductions array.
     */
    public function calculateTotalDeductions(): void
    {
        $total = 0;
        if (is_array($this->deductions)) {
            foreach ($this->deductions as $deduction) {
                if (isset($deduction['amount'])) {
                    $total += floatval($deduction['amount']);
                }
            }
        }
        $this->total_deductions = $total;
    }

    /**
     * Scope: By status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: By period.
     */
    public function scopeByPeriod(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('period_start', [$start, $end]);
    }
}
