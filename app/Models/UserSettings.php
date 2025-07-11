<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'key',
        'value',
        'type',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    /**
     * Get the user that owns the setting.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the value with proper type casting.
     */
    public function getTypedValue()
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Set the value with proper type detection.
     */
    public function setTypedValue($value): void
    {
        if (is_bool($value)) {
            $this->type = 'boolean';
            $this->value = $value ? '1' : '0';
        } elseif (is_int($value)) {
            $this->type = 'integer';
            $this->value = (string) $value;
        } elseif (is_float($value)) {
            $this->type = 'float';
            $this->value = (string) $value;
        } elseif (is_array($value) || is_object($value)) {
            $this->type = 'json';
            $this->value = json_encode($value);
        } else {
            $this->type = 'string';
            $this->value = (string) $value;
        }
    }
}
