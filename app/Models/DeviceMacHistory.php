<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceMacHistory extends Model
{
    protected $fillable = [
        'device_id',
        'mac_address',
        'is_active',
        'circuit_id',
        'remote_id',
        'room_number',
        'nas_id',
        'ip_address',
        'detected_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'detected_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
