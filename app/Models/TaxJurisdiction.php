<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class TaxJurisdiction extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'country_uuid',
        'region_code',
        'name',
        'tax_year_start',
        'tax_year_end',
        'regulatory_authority',
        'effective_from',
        'effective_to',
        'settings',
        'is_active'
    ];

    protected $casts = [
        'tax_year_start' => 'date',
        'tax_year_end' => 'date',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'settings' => 'array',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_uuid', 'uuid');
    }

    public function statutoryDeductionTemplates(): HasMany
    {
        return $this->hasMany(StatutoryDeductionTemplate::class, 'jurisdiction_uuid', 'uuid');
    }

    // Scope Methods
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrent($query)
    {
        return $query->where('effective_from', '<=', now())
                    ->where(function($q) {
                        $q->whereNull('effective_to')
                          ->orWhere('effective_to', '>=', now());
                    });
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate UUID on creation
        static::creating(function ($jurisdiction) {
            if (empty($jurisdiction->uuid)) {
                $jurisdiction->uuid = (string) Str::uuid();
            }
        });
    }
}