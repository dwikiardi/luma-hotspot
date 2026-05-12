<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceDna extends Model
{
    protected $table = 'device_dna';

    protected $fillable = [
        'fingerprint_hash',
        'known_macs',
        'known_hostnames',
        'known_ouis',
        'known_vendor_classes',
        'first_seen_at',
        'last_seen_at',
        'last_user_id',
        'last_device_id',
        'confidence',
        'match_count',
    ];

    protected $casts = [
        'known_macs' => 'array',
        'known_hostnames' => 'array',
        'known_ouis' => 'array',
        'known_vendor_classes' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'confidence' => 'decimal:2',
        'match_count' => 'integer',
    ];

    public function lastUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user_id');
    }

    public function lastDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'last_device_id');
    }
}
