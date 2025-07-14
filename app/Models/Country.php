<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Country extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'iso_code',
        'name',
        'currency_code',
        'timezone',
        'regulatory_framework',
        'is_supported_for_payroll',
        'is_active'
    ];

    protected $casts = [
        'regulatory_framework' => 'array',
        'is_supported_for_payroll' => 'boolean',
        'is_active' => 'boolean'
    ];

    // Validation rules
    public static function rules(): array
    {
        return [
            'iso_code' => 'required|string|size:2|unique:countries,iso_code',
            'name' => 'required|string|max:255',
            'currency_code' => 'required|string|size:3',
            'timezone' => 'required|string|max:50',
            'regulatory_framework' => 'nullable|array',
            'is_supported_for_payroll' => 'boolean',
            'is_active' => 'boolean'
        ];
    }

    // Relationships
    public function taxJurisdictions(): HasMany
    {
        return $this->hasMany(TaxJurisdiction::class, 'country_uuid', 'uuid');
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'country_uuid', 'uuid');
    }

    // Business Logic Methods
    public function supportsPayroll(): bool
    {
        return $this->is_supported_for_payroll;
    }

    public function getCurrentTaxJurisdiction(): ?TaxJurisdiction
    {
        return $this->taxJurisdictions()
            ->where('effective_from', '<=', now())
            ->where(function($query) {
                $query->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', now());
            })
            ->where('is_active', true)
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    public function getTaxYearStart(): ?string
    {
        return $this->regulatory_framework['tax_year_start'] ?? null;
    }

    public function getTaxYearEnd(): ?string
    {
        return $this->regulatory_framework['tax_year_end'] ?? null;
    }

    public function getMandatoryDeductions(): array
    {
        return $this->regulatory_framework['mandatory_deductions'] ?? [];
    }

    public function getTaxYearForDate(Carbon $date): array
    {
        $taxYearStart = $this->getTaxYearStart();
        $taxYearEnd = $this->getTaxYearEnd();
        
        if (!$taxYearStart || !$taxYearEnd) {
            // Default to calendar year
            return [
                'start' => $date->copy()->startOfYear(),
                'end' => $date->copy()->endOfYear()
            ];
        }
        
        // Parse tax year dates
        $startMonth = (int)substr($taxYearStart, 0, 2);
        $startDay = (int)substr($taxYearStart, 3, 2);
        $endMonth = (int)substr($taxYearEnd, 0, 2);
        $endDay = (int)substr($taxYearEnd, 3, 2);
        
        // Calculate tax year based on current date
        $year = $date->year;
        $taxYearStartDate = Carbon::create($year, $startMonth, $startDay);
        
        // If tax year crosses calendar years
        if ($startMonth > $endMonth) {
            if ($date->lt($taxYearStartDate)) {
                $taxYearStartDate = $taxYearStartDate->subYear();
            }
            $taxYearEndDate = Carbon::create($year + 1, $endMonth, $endDay);
        } else {
            $taxYearEndDate = Carbon::create($year, $endMonth, $endDay);
        }
        
        return [
            'start' => $taxYearStartDate,
            'end' => $taxYearEndDate
        ];
    }

    // Scope Methods
    public function scopePayrollSupported($query)
    {
        return $query->where('is_supported_for_payroll', true);
    }

    public function scopePayrollNotSupported($query)
    {
        return $query->where('is_supported_for_payroll', false);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeByCurrencyCode($query, string $currencyCode)
    {
        return $query->where('currency_code', $currencyCode);
    }

    public function scopeByTimezone($query, string $timezone)
    {
        return $query->where('timezone', $timezone);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate UUID on creation
        static::creating(function ($country) {
            if (empty($country->uuid)) {
                $country->uuid = (string) Str::uuid();
            }
        });
    }

}