<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'router_id',
        'mac_address',
        'fingerprint_hash',
        'cookie_token',
        'ip_address',
        'login_at',
        'last_seen_at',
        'disconnected_at',
        'expires_at',
        'status',
        'nas_id',
        'login_method',
        'user_agent',
        'meta',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'expires_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public static function generateCookieToken(): string
    {
        return hash('sha256', Str::random(64).now()->timestamp);
    }

    public function isInGracePeriod(): bool
    {
        return $this->status === 'disconnected'
            && $this->disconnected_at !== null
            && now()->lessThan($this->expires_at);
    }

    public function getSecondsRemainingAttribute(): int
    {
        if ($this->expires_at === null) {
            return 0;
        }

        $remaining = (int) now()->diffInSeconds($this->expires_at, false);
        return max(0, $remaining);
    }

    public function refreshExpiry(int $gracePeriodSeconds): void
    {
        $this->update([
            'disconnected_at' => now(),
            'expires_at' => now()->addSeconds($gracePeriodSeconds),
            'status' => 'disconnected',
        ]);
    }
}
