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

        $sessions = UserSession::where('status', 'disconnected')
            ->where('expires_at', '>', now())
            ->where('router_id', $router->id)
            ->orderByDesc('disconnected_at')
            ->get();

        $hasValidMac = $mac && $mac !== 'unknown';
        $hasFingerprint = !empty($fingerprint);
        $hasCookie = !empty($cookie);
        $isCNA = str_contains($request->userAgent() ?? '', 'CaptiveNetworkSupport')
            || str_contains($request->header('User-Agent') ?? '', 'CaptiveNetworkSupport');

        // Log grace check
        if ($isCNA || $hasFingerprint || $hasCookie || $hasValidMac) {
            \App\Services\ActivityLogger::graceCheck(
                $mac ?: 'unknown', $isCNA, $sessions->count(),
                UserSession::where('status', 'active')->where('router_id', $router->id)->count()
            );
        }

        // === SCAN ALL SESSIONS (disconnected + active) ===
        $allSessions = $sessions->merge(
            UserSession::where('status', 'active')
                ->where('router_id', $router->id)
                ->where('expires_at', '>', now())
                ->get()
        );

        foreach ($allSessions as $session) {
            // Primary: fingerprint match (HANYA real JS fingerprint, bukan UA fallback)
            // UA fallback dimulai dengan "fp-Mozilla" — jangan di-match (tidak unique per device)
            $isRealFp = $hasFingerprint && !str_starts_with($fingerprint, 'fp-Mozilla');
            if ($isRealFp && $session->fingerprint_hash === $fingerprint) {
                \App\Services\ActivityLogger::graceAutoLogin(
                    User::find($session->user_id)?->identity_value ?? '?',
                    $session->id, $session->mac_address, $session->ip_address ?? '?'
                );
                return GraceCheckResult::autoLogin($session);
            }

            // Primary: cookie match
            if ($hasCookie && $session->cookie_token === $cookie) {
                \App\Services\ActivityLogger::graceAutoLogin(
                    User::find($session->user_id)?->identity_value ?? '?',
                    $session->id, $session->mac_address, $session->ip_address ?? '?'
                );
                return GraceCheckResult::autoLogin($session);
            }
        }

        // Secondary: MAC + IP scoring (only if primary signals exist but didn't match above,
        // or if at least IP is valid)
        $hasAnySignal = $hasValidMac || ($ip && $ip !== 'unknown' && $ip !== '127.0.0.1');

        if ($hasAnySignal) {
            foreach ($allSessions as $session) {
                $score = 0;

                if ($hasValidMac && $session->mac_address === $mac) $score += 3;
                if ($ip && $session->ip_address === $ip) $score += 2;
                if ($ip && $session->ip_address === $ip && $session->nas_id === $nasId) $score += 1;

                if ($score >= 4) { // Need MAC+IP or strong combination
                    \App\Services\ActivityLogger::graceAutoLogin(
                        User::find($session->user_id)?->identity_value ?? '?',
                        $session->id, $session->mac_address, $session->ip_address ?? '?'
                    );
                    return GraceCheckResult::autoLogin($session);
                }
            }
        }

        // No match → require login
        $reason = $hasFingerprint ? 'fp no match' : ($hasCookie ? 'cookie no match' : 'no signal');
        \App\Services\ActivityLogger::graceRequireLogin($reason);
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

        // Reactivate existing session by fingerprint, cookie, or MAC
        $isRealFp = $fingerprint && !str_starts_with($fingerprint, 'fp-Mozilla');
        $existing = UserSession::where('user_id', $user->id)
            ->where('router_id', $router->id)
            ->whereIn('status', ['active', 'disconnected'])
            ->where(function ($q) use ($isRealFp, $fingerprint, $cookie, $mac) {
                if ($isRealFp && $fingerprint) $q->orWhere('fingerprint_hash', $fingerprint);
                if ($cookie) $q->orWhere('cookie_token', $cookie);
                if ($mac && $mac !== 'unknown') $q->orWhere('mac_address', $mac);
            })
            ->orderByDesc('login_at')
            ->first();

        // Fallback: no signal match → reactivate most recent session for this user
        // (User entered room number = we KNOW it's them. Always reactivate, never duplicate.)
        if (! $existing) {
            $existing = UserSession::where('user_id', $user->id)
                ->where('router_id', $router->id)
                ->whereIn('status', ['active', 'disconnected'])
                ->orderByDesc('login_at')
                ->first();
        }

        if ($existing) {
            // Expire any OTHER active sessions for this user (clean duplicates)
            UserSession::where('user_id', $user->id)
                ->where('router_id', $router->id)
                ->where('status', 'active')
                ->where('id', '!=', $existing->id)
                ->update(['status' => 'expired']);

            $existing->update([
                'status' => 'active',
                'login_at' => now(),
                'last_seen_at' => now(),
                'expires_at' => now()->addSeconds($sessionTimeout),
                'disconnected_at' => null,
                'ip_address' => $clientIp ?: $existing->ip_address,
                'mac_address' => $mac !== 'unknown' ? $mac : $existing->mac_address,
                'fingerprint_hash' => ($isRealFp && $fingerprint) ? $fingerprint : $existing->fingerprint_hash,
                'cookie_token' => $cookie ?: $existing->cookie_token,
            ]);
            $this->logDeviceFingerprint($request, $user, $device, $router);
            return $existing;
        }

        // Create new session
        $session = UserSession::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'router_id' => $router->id,
            'mac_address' => $mac,
            'fingerprint_hash' => ($fingerprint && !str_starts_with($fingerprint, 'fp-Mozilla')) ? $fingerprint : null,
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
