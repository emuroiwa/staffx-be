<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should use UUIDs.
     */
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'uuid',
        'created_by_uuid',
        'country_uuid',
        'name',
        'slug',
        'domain',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'website',
        'tax_id',
        'logo_path',
        'subscription_expires_at',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'subscription_expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($company) {
            if (empty($company->uuid)) {
                $company->uuid = Str::uuid();
            }
            
            if (empty($company->slug)) {
                $company->slug = Str::slug($company->name);
                
                // Ensure slug is unique
                $count = 1;
                $originalSlug = $company->slug;
                while (static::where('slug', $company->slug)->exists()) {
                    $company->slug = $originalSlug . '-' . $count;
                    $count++;
                }
            }
        });
    }

    /**
     * Get the user who created this company (HCA).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_uuid', 'uuid');
    }

    /**
     * Get the country this company operates in.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_uuid', 'uuid');
    }

    /**
     * Get the users for the company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_uuid', 'uuid');
    }

    /**
     * Get the departments for the company.
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'company_uuid', 'uuid');
    }

    /**
     * Get the positions for the company.
     */
    public function positions(): HasMany
    {       
        return $this->hasMany(Position::class, 'company_uuid', 'uuid');
    }


    /**
     * Get the employees for the company.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'company_uuid', 'uuid');
    }

    /**
     * Get the payrolls for the company.
     */
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'company_uuid', 'uuid');
    }

    /**
     * Get the admin users for the company.
     */
    public function admins(): HasMany
    {
        return $this->users()->where('role', 'admin');
    }

    /**
     * Get a setting value.
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a setting value.
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Check if company has active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->is_active && 
               ($this->subscription_expires_at === null || $this->subscription_expires_at->isFuture());
    }

    /**
     * Check if subscription is expired.
     */
    public function hasExpiredSubscription(): bool
    {
        return $this->subscription_expires_at && $this->subscription_expires_at->isPast();
    }

    /**
     * Get days left in subscription.
     */
    public function getDaysLeftInSubscription(): int
    {
        if (!$this->subscription_expires_at) {
            return 0;
        }
        
        return max(0, (int) now()->diffInDays($this->subscription_expires_at, false));
    }

    /**
     * Check if subscription is expiring soon (within 7 days).
     */
    public function isSubscriptionExpiringSoon(): bool
    {
        return $this->hasActiveSubscription() && $this->getDaysLeftInSubscription() <= 7;
    }

    /**
     * Get subscription status.
     */
    public function getSubscriptionStatus(): string
    {
        if (!$this->subscription_expires_at) {
            return 'trial'; // No subscription set means trial/free
        }
        
        $daysLeft = $this->getDaysLeftInSubscription();
        
        if ($daysLeft > 7) {
            return 'active';
        } elseif ($daysLeft > 0) {
            return 'expiring_soon';
        } else {
            return 'expired';
        }
    }

    /**
     * Extend subscription by given months.
     */
    public function extendSubscription(int $months): void
    {
        $currentExpiry = $this->subscription_expires_at ?? now();
        
        // If subscription is expired, extend from now
        if ($this->hasExpiredSubscription()) {
            $currentExpiry = now();
        }
        
        $this->subscription_expires_at = $currentExpiry->addMonths($months);
        $this->save();
    }

    /**
     * Start trial subscription (1 month from now).
     */
    public function startTrialSubscription(): void
    {
        $this->subscription_expires_at = now()->addMonth();
        $this->is_active = true;
        $this->save();
    }
}
