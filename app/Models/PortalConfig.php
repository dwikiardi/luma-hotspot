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

        $script = <<<SCRIPT
# MikroTik Hotspot Profile - Luma Network
/ip hotspot profile
add name={$profileName} \
    hotspot-address=192.168.88.1 \
    dns-name={$dnsName} \
    login-by=http-chap,http-pap \
    http-cookie-lifetime=01:00:00 \
    split-user-domain=no \
    use-radius=yes \
    radius-accounting=yes \
    radius-interim-update=5m \
    http-redirect={$portalUrl} \
    rate-limit="10M/10M" \
    session-timeout={$sessionTimeout} \
    idle-timeout={$idleTimeout} \
    shared-users={$sharedUsers} \
    ssl-certificate=none

# Create address pool if not exists
/ip pool
add name={$poolName} ranges=192.168.88.10-192.168.88.254

# Enable HTTP PAP for simpler authentication
/ip hotspot
add name={$profileName}-server \
    interface=bridge-lan \
    address-pool={$poolName} \
    profile={$profileName} \
    disabled=no
SCRIPT;

        return $script;
    }
}
