<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Currency extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'symbol',
        'exchange_rate',
        'is_active',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate UUID on creation
        static::creating(function ($currency) {
            if (empty($currency->uuid)) {
                $currency->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the employees that use this currency.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'currency_uuid', 'uuid');
    }

    /**
     * Get the payrolls that use this currency.
     */
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'currency_uuid', 'uuid');
    }

    /**
     * Scope: Active currencies only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: By currency code.
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    /**
     * Get formatted exchange rate.
     */
    public function getFormattedExchangeRateAttribute(): string
    {
        return number_format($this->exchange_rate, 6);
    }

    /**
     * Get display name with symbol.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->symbol})";
    }

    /**
     * Get full currency representation.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->code} - {$this->name} ({$this->symbol})";
    }

    /**
     * Check if this is the base currency (USD with rate 1.0).
     */
    public function isBaseCurrency(): bool
    {
        return $this->code === 'USD' && $this->exchange_rate == 1.0;
    }

    /**
     * Convert amount from this currency to base currency.
     */
    public function convertToBase(float $amount): float
    {
        return $amount / $this->exchange_rate;
    }

    /**
     * Convert amount from base currency to this currency.
     */
    public function convertFromBase(float $amount): float
    {
        return $amount * $this->exchange_rate;
    }
}
