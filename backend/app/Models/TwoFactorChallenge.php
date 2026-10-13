<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorChallenge extends Model
{
    protected $fillable = [
        'user_id',
        'challenge_token',
        'method',
        'attempts',
        'max_attempts',
        'remember_device',
        'ip_address',
        'user_agent',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'remember_device' => 'boolean',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }
}
