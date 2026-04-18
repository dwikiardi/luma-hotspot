<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'user_id',
        'fingerprint_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function macHistories(): HasMany
    {
        return $this->hasMany(DeviceMacHistory::class);
    }

    public function activeMac()
    {
        return $this->hasOne(DeviceMacHistory::class)
            ->where('is_active', true)
            ->latestOfMany();
    }
}
