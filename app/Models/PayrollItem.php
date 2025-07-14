<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class PayrollItem extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'payroll_uuid',
        'employee_uuid',
        'code',
        'name',
        'type',
        'amount',
        'calculation_details'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'calculation_details' => 'array'
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

    /**
     * Get the payroll this item belongs to.
     */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_uuid', 'uuid');
    }

    /**
     * Get the employee this item belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_uuid', 'uuid');
    }

    /**
     * Check if this is an allowance.
     */
    public function isAllowance(): bool
    {
        return $this->type === 'allowance';
    }

    /**
     * Check if this is a deduction.
     */
    public function isDeduction(): bool
    {
        return in_array($this->type, ['deduction', 'income_tax', 'unemployment_insurance', 'health_insurance', 'social_security']);
    }

    /**
     * Check if this is a statutory deduction.
     */
    public function isStatutory(): bool
    {
        return in_array($this->type, ['income_tax', 'unemployment_insurance', 'health_insurance', 'social_security']);
    }
}