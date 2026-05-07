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

        // Find ANY session for any user on this router (active or grace)
        $session = UserSession::where('router_id', $router->id)
            ->whereIn('status', ['active', 'disconnected'])
            ->where('expires_at', '>', now())
            ->first();

        if (! $session) {
            \App\Services\ActivityLogger::graceRequireLogin('no sessions');
            return GraceCheckResult::requireLogin();
        }

        \App\Services\ActivityLogger::graceCheck(
            $mac ?: 'unknown', false,
            UserSession::where('status', 'disconnected')->where('router_id', $router->id)->count(),
            UserSession::where('status', 'active')->where('router_id', $router->id)->count()
        );

        // Primary: cookie match
        if ($hasCookie && $session->cookie_token === $cookie) {
            \App\Services\ActivityLogger::graceAutoLogin(
                User::find($session->user_id)?->identity_value ?? '?',
                $session->id, $session->mac_address, $session->ip_address ?? '?'
            );
            return GraceCheckResult::autoLogin($session);
        }

        // Primary: real JS fingerprint match
        if ($hasRealFp && $session->fingerprint_hash === $fingerprint) {
            \App\Services\ActivityLogger::graceAutoLogin(
                User::find($session->user_id)?->identity_value ?? '?',
                $session->id, $session->mac_address, $session->ip_address ?? '?'
            );
            return GraceCheckResult::autoLogin($session);
        }

        // Secondary: MAC + IP scoring
        $score = 0;
        if ($hasValidMac && $session->mac_address === $mac) $score += 3;
        if ($ip && $session->ip_address === $ip) $score += 2;
        if ($ip && $session->ip_address === $ip && $session->nas_id === $nasId) $score += 1;

        if ($score >= 4) {
            \App\Services\ActivityLogger::graceAutoLogin(
                User::find($session->user_id)?->identity_value ?? '?',
                $session->id, $session->mac_address, $session->ip_address ?? '?'
            );
            return GraceCheckResult::autoLogin($session);
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
        $sessionTimeout = $config->session_timeout ?? 14400;

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
            // UPDATE existing session — NEVER change cookie_token (immutable!)
            $existing->update([
                'status' => 'active',
                'login_at' => now(),
                'last_seen_at' => now(),
                'expires_at' => now()->addSeconds($sessionTimeout),
                'disconnected_at' => null,
                'ip_address' => $clientIp ?: $existing->ip_address,
                'mac_address' => $mac !== 'unknown' ? $mac : $existing->mac_address,
                // Only overwrite fingerprint with real JS fp
                'fingerprint_hash' => ($isRealFp && $fingerprint) ? $fingerprint : $existing->fingerprint_hash,
                // NEVER change cookie_token
            ]);
            $this->logDeviceFingerprint($request, $user, $device, $router);
            return $existing;
        }

        // CREATE new session (first time for this user+router)
        $session = UserSession::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'router_id' => $router->id,
            'mac_address' => $mac,
            'fingerprint_hash' => ($isRealFp && $fingerprint) ? $fingerprint : null,
            'cookie_token' => UserSession::generateCookieToken(), // Generate ONCE
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

            $existing = \App\Models\DeviceFingerprint::where('fingerprint_hash', $fp)->first();

            if ($existing) {
                $matches = $existing->match_count + 1;
                $score = min(95, 50 + ($matches * 15));
                $existing->update([
                    'trust_score' => $score,
                    'confidence' => $matches >= 3 ? 'high' : ($matches >= 2 ? 'medium' : 'low'),
                    'match_count' => $matches,
                    'is_known_device' => $matches >= 2,
                    'user_id' => $user->id,
                    'ip_address' => $request->header('X-Forwarded-For') ?? $request->ip(),
                    'nas_id' => $router->nas_identifier,
                    'user_agent' => $request->userAgent(),
                    'mac' => $request->query('client_mac') ?? $request->input('client_mac'),
                ]);
            } else {
                \App\Models\DeviceFingerprint::create([
                    'fingerprint_hash' => $fp,
                    'user_id' => $user->id,
                    'device_id' => $device->id,
                    'ip_address' => $request->header('X-Forwarded-For') ?? $request->ip(),
                    'nas_id' => $router->nas_identifier,
                    'user_agent' => $request->userAgent(),
                    'mac' => $request->query('client_mac') ?? $request->input('client_mac'),
                    'trust_score' => 50,
                    'confidence' => 'low',
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('DeviceFingerprint log failed', ['error' => $e->getMessage()]);
        }
    }
}
