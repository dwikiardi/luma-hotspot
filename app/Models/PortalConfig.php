<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;

class PortalConfig extends Model
{
    protected $fillable = [
        'tenant_id',
        'active_login_methods',
        'branding',
        'grace_period_seconds',
        'grace_period_enabled',
        'custom_login_enabled',
        'custom_login_label',
        'custom_login_placeholder',
        'hotspot_profile_name',
        'address_pool_name',
        'dns_name',
        'session_timeout',
        'idle_timeout',
        'shared_users',
        'room_validation_enabled',
        'room_validation_mode',
        'room_validation_config',
    ];

    protected $casts = [
        'active_login_methods' => 'array',
        'branding' => 'array',
        'grace_period_enabled' => 'boolean',
        'custom_login_enabled' => 'boolean',
        'room_validation_enabled' => 'boolean',
        'room_validation_config' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getGracePeriodPresetAttribute(): string
    {
        return match (true) {
            $this->grace_period_seconds >= 28800 => 'hotel',
            $this->grace_period_seconds >= 14400 => 'coworking',
            $this->grace_period_seconds >= 7200 => 'cafe',
            $this->grace_period_seconds >= 3600 => 'mall',
            default => 'custom',
        };
    }

    public function getPortalUrl(?string $nasId = null): string
    {
        $baseUrl = Config::get('app.server_url', 'http://103.137.140.6:8081');
        $url = rtrim($baseUrl, '/').'/portal';

        $params = [];
        if ($nasId) {
            $params['nas_id'] = $nasId;
        }

        if (! empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        return $url;
    }

    public function getMikroTikHotspotScript(?string $nasId = null): string
    {
        $serverIp = Config::get('app.server_ip', '103.137.140.6');
        $serverUrl = Config::get('app.server_url', 'http://103.137.140.6:8081');
        $radiusSecret = Config::get('services.radius.secret', 'luma_radius_secret');

        $profileName = $this->hotspot_profile_name ?? 'luma-portal';
        $poolName = $this->address_pool_name ?? 'hotspot-pool';
        $dnsName = $this->dns_name ?? 'portal.lumanetwork.id';
        $sessionTimeout = $this->session_timeout ?? 14400;
        $idleTimeout = $this->idle_timeout ?? 1800;
        $sharedUsers = $this->shared_users ?? 3;

        $portalUrl = $this->getPortalUrl($nasId);
        
        // Remove CIDR notation if present (e.g., 192.168.100.1/24 -> 192.168.100.1)
        $hotspotAddress = preg_replace('/\/\d+$/', '', $hotspotAddress);
        
        // Extract base network from hotspot address (e.g., 192.168.100.1 -> 192.168.100)
        $networkBase = substr($hotspotAddress, 0, strrpos($hotspotAddress, '.'));

        $script = <<<SCRIPT
# MikroTik Configuration - Luma Network
# Generated for NAS: {$nasId}

# ============================================================
# MANUAL STEP 1: Configure IP Pool (Do this first!)
# ============================================================
# Example for network {$hotspotAddress}/24:
# /ip pool add name=hotspot-pool ranges={$networkBase}.10-{$networkBase}.254
#
# Or use your existing pool (default: 'hsprof1')

# ============================================================
# STEP 2: Run these commands
# ============================================================

# 1. System Identity
/system identity set name="{$nasId}"

# 2. RADIUS Server Configuration
/radius
add service=hotspot address={$serverIp} secret="{$radiusSecret}" authentication-port=1812 accounting-port=1813

# 3. Hotspot Profile (Uses existing/default profile)
/ip hotspot profile
set [find default=yes] use-radius=yes radius-accounting=yes radius-interim-update=5m

# 4. Enable Hotspot
/ip hotspot
enable [find]

# 5. Walled Garden - Allow access to Luma portal
/ip hotspot walled-garden ip
add dst-address={$serverIp} action=accept comment="Luma Server"
add dst-host="*.lumanetwork.id" action=accept
SCRIPT;

        return $script;
    }
}
