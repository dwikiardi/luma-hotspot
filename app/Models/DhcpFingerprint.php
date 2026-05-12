<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DhcpFingerprint extends Model
{
    protected $fillable = [
        'mac_address',
        'ip_address',
        'hostname',
        'vendor_class_id',
        'parameter_request_list',
        'client_id',
        'subnet_mask',
        'gateway',
        'dns_server',
        'fingerprint_hash',
        'dhcp_server',
        'router_id',
        'detected_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public static function computeHash(?string $hostname, ?string $vendorClassId, ?string $parameterRequestList, ?string $clientId): string
    {
        $parts = [];
        if ($hostname) $parts[] = strtolower(trim($hostname));
        if ($vendorClassId) $parts[] = $vendorClassId;
        if ($parameterRequestList) $parts[] = $parameterRequestList;
        if ($clientId) $parts[] = $clientId;
        return hash('sha256', implode('|', $parts));
    }
}
