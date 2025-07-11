<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Notifications\CustomVerifyEmail;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'company_id',
        'default_company_id',
        'role',
        'company', // Keep for backward compatibility during migration
        'password',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail);
    }

    /**
     * Get the user's settings.
     */
    public function settings(): HasMany
    {
        return $this->hasMany(UserSettings::class);
    }

    /**
     * Get a specific setting value.
     */
    public function getSetting(string $key, $default = null)
    {
        $setting = $this->settings()->where('key', $key)->first();
        return $setting ? $setting->getTypedValue() : $default;
    }

    /**
     * Set a specific setting value.
     */
    public function setSetting(string $key, $value): void
    {
        $setting = $this->settings()->firstOrNew(['key' => $key]);
        $setting->setTypedValue($value);
        $setting->save();
    }

    /**
     * Get all settings as an associative array.
     */
    public function getAllSettings(): array
    {
        return $this->settings->pluck('value', 'key')
            ->map(function ($value, $key) {
                $setting = $this->settings->firstWhere('key', $key);
                return $setting ? $setting->getTypedValue() : $value;
            })
            ->toArray();
    }

    /**
     * Get the companies created by this user (HCA only).
     */
    public function ownedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'created_by');
    }

    /**
     * Get the default company for this user.
     */
    public function defaultCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'default_company_id');
    }

    /**
     * Get the company that owns the user.
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the employee record associated with this user.
     */
    public function employee(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Check if user is holding company admin (HCA).
     */
    public function isHoldingCompanyAdmin(): bool
    {
        return $this->role === 'holding_company_admin';
    }

    /**
     * Check if user has active subscription through their company.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->company && $this->company->hasActiveSubscription();
    }

    /**
     * Get days left in subscription through company.
     */
    public function getDaysLeftInSubscription(): int
    {
        if (!$this->company) {
            return 0;
        }
        
        return $this->company->getDaysLeftInSubscription();
    }

    /**
     * Get subscription status through company.
     */
    public function getSubscriptionStatus(): ?string
    {
        return $this->company?->getSubscriptionStatus();
    }

    /**
     * Backward compatibility for hasActiveTrial - now delegates to company subscription.
     */
    public function hasActiveTrial(): bool
    {
        return $this->hasActiveSubscription();
    }

    /**
     * Backward compatibility for getDaysLeftInTrial - now delegates to company subscription.
     */
    public function getDaysLeftInTrial(): int
    {
        return $this->getDaysLeftInSubscription();
    }

    /**
     * Check if user can access the system (company subscription active).
     */
    public function canAccessSystem(): bool
    {
        if ($this->isHoldingCompanyAdmin()) {
            // HCA users can access if any of their owned companies has active subscription
            return $this->ownedCompanies()->whereHas('users', function($query) {
                $query->where('users.id', $this->id);
            })->get()->some(function($company) {
                return $company->hasActiveSubscription();
            }) || $this->hasActiveSubscription();
        }
        
        // For other roles, check if their company has active subscription
        return $this->hasActiveSubscription();
    }

    /**
     * Check if user is company admin.
     */
    public function isCompanyAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is manager.
     */
    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }

    /**
     * Check if user is HR.
     */
    public function isHR(): bool
    {
        return in_array($this->role, ['admin', 'hr']);
    }

    /**
     * Check if user can manage employees.
     */
    public function canManageEmployees(): bool
    {
        return in_array($this->role, ['admin', 'manager', 'hr']);
    }

    /**
     * Check if user can manage payroll.
     */
    public function canManagePayroll(): bool
    {
        return in_array($this->role, ['admin', 'hr']);
    }

    /**
     * Get user's permissions based on role.
     */
    public function getPermissions(): array
    {
        return match($this->role) {
            'holding_company_admin' => [
                'manage_companies',
                'create_companies',
                'view_all_companies',
                'manage_default_company',
                'manage_trial',
            ],
            'admin' => [
                'manage_company',
                'manage_users',
                'manage_employees',
                'manage_payroll',
                'view_reports',
                'manage_settings',
            ],
            'manager' => [
                'manage_employees',
                'view_reports',
            ],
            'hr' => [
                'manage_employees',
                'manage_payroll',
                'view_reports',
            ],
            'employee' => [
                'view_own_profile',
                'view_own_payroll',
            ],
            default => [],
        };
    }

    /**
     * Check if user has specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }
}
