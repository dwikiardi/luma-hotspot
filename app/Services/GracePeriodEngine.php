<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Router;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;

class GraceCheckResult
{
    public bool $shouldAutoLogin;
    public ?UserSession $session;

    private function __construct(bool $auto, ?UserSession $session)
    {
        $this->shouldAutoLogin = $auto;
        $this->session = $session;
    }

    public static function autoLogin(UserSession $s): self
    {
        return new self(true, $s);
    }

    public static function requireLogin(): self
    {
        return new self(false, null);
    }
}

class GracePeriodEngine
{
    public function check(Request $request, Router $router): GraceCheckResult
    {
        $mac = $request->query('client_mac') ?? $request->query('mac');
        $cookie = $request->cookie('luma_session');
        $fingerprint = $request->header('X-Fingerprint') ?? $request->query('fingerprint');
        $ip = $request->ip();
        $nasId = $router->nas_identifier;

        $hasValidMac = $mac && $mac !== 'unknown';
        $hasRealFp = $fingerprint && !str_starts_with($fingerprint, 'fp-Mozilla');
        $hasCookie = !empty($cookie);

        $tenantRouterIds = \App\Models\Router::where('tenant_id', $router->tenant_id)->pluck('id')->toArray();

        \App\Services\ActivityLogger::graceCheck(
            $mac ?: 'unknown', false,
            UserSession::where('status', 'disconnected')->whereIn('router_id', $tenantRouterIds)->count(),
            UserSession::where('status', 'active')->whereIn('router_id', $tenantRouterIds)->count()
        );

        // 1. Cookie match (most reliable — survives MAC randomization)
        if ($hasCookie) {
            $session = UserSession::whereIn('router_id', $tenantRouterIds)
                ->where('cookie_token', $cookie)
                ->whereIn('status', ['active', 'disconnected'])
                ->where('expires_at', '>', now())
                ->first();
            if ($session) {
                \App\Services\ActivityLogger::graceAutoLogin(
                    User::find($session->user_id)?->identity_value ?? '?',
                    $session->id, $session->mac_address, $session->ip_address ?? '?'
                );
                return GraceCheckResult::autoLogin($session);
            }
        }

        // 2. Real JS fingerprint match (survives cookie clear + MAC change)
        if ($hasRealFp) {
            $session = UserSession::whereIn('router_id', $tenantRouterIds)
                ->where('fingerprint_hash', $fingerprint)
                ->whereIn('status', ['active', 'disconnected'])
                ->where('expires_at', '>', now())
                ->first();
            if ($session) {
                \App\Services\ActivityLogger::graceAutoLogin(
                    User::find($session->user_id)?->identity_value ?? '?',
                    $session->id, $session->mac_address, $session->ip_address ?? '?'
                );
                return GraceCheckResult::autoLogin($session);
            }
        }

        // 3. MAC match (works on non-randomizing devices / same network)
        if ($hasValidMac) {
            $session = UserSession::whereIn('router_id', $tenantRouterIds)
                ->where('mac_address', $mac)
                ->whereIn('status', ['active', 'disconnected'])
                ->where('expires_at', '>', now())
                ->first();
            if ($session) {
                \App\Services\ActivityLogger::graceAutoLogin(
                    User::find($session->user_id)?->identity_value ?? '?',
                    $session->id, $session->mac_address, $session->ip_address ?? '?'
                );
                return GraceCheckResult::autoLogin($session);
            }
        }

        \App\Services\ActivityLogger::graceRequireLogin('no matching signals');
        return GraceCheckResult::requireLogin();
    }

    public function onDisconnect(UserSession $session): void
    {
        $config = $session->router?->tenant?->portalConfig;
        if (! $config) {
            $router = Router::find($session->router_id);
            $config = $router?->tenant?->portalConfig;
        }
        if (! $config) return;

        $graceSeconds = $config->grace_period_seconds;
        $session->update([
            'status' => 'disconnected',
            'disconnected_at' => now(),
            'expires_at' => now()->addSeconds($graceSeconds),
        ]);
    }

    public function createSession(
        Request $request,
        User $user,
        Device $device,
        Router $router
    ): UserSession {
        $config = $router->tenant->portalConfig;
        $sessionTimeout = $config->session_timeout ?? 0;
        if ($sessionTimeout <= 0) $sessionTimeout = 365 * 86400; // 0/kosong = 1 tahun

        $mac = $request->query('client_mac')
            ?? $request->input('client_mac')
            ?? $request->query('mac')
            ?? $request->input('mac')
            ?? $request->query('callingstationid')
            ?? 'unknown';

        $clientIp = $request->query('ip')
            ?? $request->input('ip')
            ?? $request->header('X-Forwarded-For')
            ?? $request->header('X-Real-IP')
            ?? $request->ip();
        $clientIp = !empty($clientIp) ? $clientIp : null;

        $fingerprint = $request->header('X-Fingerprint') ?? $request->input('fingerprint');
        $cookie = $request->cookie('luma_session');
        $isRealFp = $fingerprint && !str_starts_with($fingerprint, 'fp-Mozilla');

        // Find existing session for this user+router (max 1 guaranteed by unique index)
        $existing = UserSession::where('user_id', $user->id)
            ->where('router_id', $router->id)
            ->whereIn('status', ['active', 'disconnected'])
            ->first();

        if ($existing) {
            $existing->update([
                'status' => 'active',
                'username' => $user->identity_value,
                'login_at' => now(),
                'last_seen_at' => now(),
                'expires_at' => now()->addSeconds($sessionTimeout),
                'disconnected_at' => null,
                'ip_address' => $clientIp ?: $existing->ip_address,
                'mac_address' => $mac !== 'unknown' ? $mac : $existing->mac_address,
                'fingerprint_hash' => ($isRealFp && $fingerprint) ? $fingerprint : $existing->fingerprint_hash,
            ]);
            $this->logDeviceFingerprint($request, $user, $device, $router);
            return $existing;
        }

        $session = UserSession::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'router_id' => $router->id,
            'username' => $user->identity_value,
            'mac_address' => $mac,
            'fingerprint_hash' => ($isRealFp && $fingerprint) ? $fingerprint : null,
            'cookie_token' => UserSession::generateCookieToken(),
            'ip_address' => $clientIp,
            'login_at' => now(),
            'last_seen_at' => now(),
            'expires_at' => now()->addSeconds($sessionTimeout),
            'status' => 'active',
            'nas_id' => $router->nas_identifier,
            'login_method' => $request->input('login_method') ?? 'room',
            'user_agent' => $request->userAgent(),
            'meta' => [
                'room_number' => $request->input('room_number'),
                'circuit_id' => $request->input('circuit_id'),
                'remote_id' => $request->input('remote_id'),
            ],
        ]);

        $this->logDeviceFingerprint($request, $user, $device, $router);
        return $session;
    }

    private function logDeviceFingerprint(Request $request, User $user, Device $device, Router $router): void
    {
        try {
            $fp = $request->header('X-Fingerprint') ?? $request->input('fingerprint') ?? ('fp-' . substr(md5($request->userAgent()), 0, 16));

            // Parse full FingerprintJS components data (from body or header)
            $fpData = [];
            $fpDataRaw = $request->input('fingerprint_data') ?? $request->header('X-Fingerprint-Data');
            \Illuminate\Support\Facades\Log::info('[FP DEBUG] raw type: ' . gettype($fpDataRaw) . ' len: ' . (is_string($fpDataRaw) ? strlen($fpDataRaw) : 0));
            if ($fpDataRaw) {
                if (is_string($fpDataRaw)) {
                    $fpData = json_decode($fpDataRaw, true) ?? [];
                } elseif (is_array($fpDataRaw)) {
                    $fpData = $fpDataRaw;
                }
            }
            \Illuminate\Support\Facades\Log::info('[FP DEBUG] parsed keys: ' . implode(',', array_keys($fpData)));

            $existing = \App\Models\DeviceFingerprint::where('fingerprint_hash', $fp)->first();

            $fields = array_filter([
                'canvas_hash' => $fpData['canvas']['value'] ?? null,
                'webgl_hash' => $fpData['webgl']['value'] ?? null,
                'webgl_vendor' => isset($fpData['webglVendorAndRenderer']['value'])
                    ? explode('~', $fpData['webglVendorAndRenderer']['value'])[0] ?? null : null,
                'webgl_renderer' => isset($fpData['webglVendorAndRenderer']['value'])
                    ? explode('~', $fpData['webglVendorAndRenderer']['value'])[1] ?? null : null,
                'fonts_hash' => is_array($fpData['fonts']['value'] ?? null) ? md5(implode(',', $fpData['fonts']['value'])) : null,
                'audio_hash' => $fpData['audio']['value'] ?? null,
                'screen_resolution' => isset($fpData['screenResolution']['value'])
                    ? $fpData['screenResolution']['value'][0] . 'x' . $fpData['screenResolution']['value'][1] : null,
                'color_depth' => $fpData['colorDepth']['value'] ?? null,
                'device_memory' => $fpData['deviceMemory']['value'] ?? null,
                'hardware_concurrency' => $fpData['hardwareConcurrency']['value'] ?? null,
                'timezone' => $fpData['timezone']['value'] ?? null,
                'languages' => isset($fpData['languages']['value']) ? json_encode($fpData['languages']['value']) : null,
                'touch_support' => isset($fpData['touchSupport']['value']['maxTouchPoints'])
                    ? $fpData['touchSupport']['value']['maxTouchPoints'] > 0 : null,
                'platform' => $fpData['platform']['value'] ?? null,
                'os_name' => $fpData['osCpu']['value'] ?? null,
                'browser_name' => $fpData['vendor']['value'] ?? null,
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->header('X-Forwarded-For') ?? $request->ip(),
                'nas_id' => $router->nas_identifier,
                'mac' => $request->query('client_mac') ?? $request->input('client_mac'),
            ], fn ($v) => $v !== null);

            if ($existing) {
                $matches = $existing->match_count + 1;
                $score = min(95, 50 + ($matches * 15));
                $fields['trust_score'] = $score;
                $fields['confidence'] = $matches >= 3 ? 'high' : ($matches >= 2 ? 'medium' : 'low');
                $fields['match_count'] = $matches;
                $fields['is_known_device'] = $matches >= 2;
                $fields['user_id'] = $user->id;
                $existing->update($fields);
            } else {
                $fields['user_id'] = $user->id;
                $fields['device_id'] = $device->id;
                $fields['trust_score'] = 50;
                $fields['confidence'] = 'low';
                $fields['fingerprint_hash'] = $fp;
                \App\Models\DeviceFingerprint::create($fields);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('DeviceFingerprint log failed', ['error' => $e->getMessage()]);
        }
    }
}
