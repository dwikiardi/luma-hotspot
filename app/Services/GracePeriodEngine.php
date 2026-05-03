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
        $mac = $request->query('client_mac');
        $cookie = $request->cookie('luma_session');
        $fingerprint = $request->header('X-Fingerprint');
        $ip = $request->ip();
        $nasId = $router->nas_identifier;

        $config = $router->tenant->portalConfig;

        $sessions = UserSession::where('status', 'disconnected')
            ->where('expires_at', '>', now())
            ->where('router_id', $router->id)
            ->orderByDesc('disconnected_at')
            ->get();

        $hasValidMac = $mac && $mac !== 'unknown';

        foreach ($sessions as $session) {
            $score = 0;

            if ($hasValidMac && $session->mac_address === $mac) {
                $score += 4;
            }

            if ($cookie && $session->cookie_token === $cookie) {
                $score += 4;
            }

            if ($fingerprint && $session->fingerprint_hash === $fingerprint) {
                $score += 3;
            }

            if ($ip && $session->ip_address === $ip) {
                $score += 2;
            }

            if ($ip && $session->ip_address && $session->ip_address === $ip
                && $session->nas_id === $nasId) {
                $score += 1;
            }

            // Fallback: jika MAC tidak diketahui, cocokkan berdasarkan username yang sama di router yang sama
            if (!$hasValidMac && $cookie) {
                $cookieSession = UserSession::where('cookie_token', $cookie)
                    ->where('router_id', $router->id)
                    ->first();
                if ($cookieSession && $cookieSession->user_id === $session->user_id) {
                    $score += 3;
                }
            }

            // Threshold lebih rendah jika MAC tidak diketahui
            $threshold = $hasValidMac ? 3 : 2;

            if ($score >= $threshold) {
                return GraceCheckResult::autoLogin($session);
            }
        }

        return GraceCheckResult::requireLogin();
    }

    public function onDisconnect(UserSession $session): void
    {
        $config = $session->router?->tenant?->portalConfig;
        if (! $config) {
            $router = Router::find($session->router_id);
            $config = $router?->tenant?->portalConfig;
        }

        if (! $config) {
            return;
        }

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

        // Get MAC from multiple sources
        $mac = $request->query('client_mac') 
            ?? $request->input('client_mac') 
            ?? $request->query('mac') 
            ?? $request->input('mac') 
            ?? $request->query('callingstationid') 
            ?? 'unknown';

        // Get real client IP from multiple sources (never empty string)
        $clientIp = $request->query('ip') 
            ?? $request->input('ip') 
            ?? $request->header('X-Forwarded-For') 
            ?? $request->header('X-Real-IP') 
            ?? $request->ip();
        $clientIp = !empty($clientIp) ? $clientIp : null;

        UserSession::where('user_id', $user->id)
            ->where('router_id', $router->id)
            ->where('status', 'active')
            ->update(['status' => 'disconnected', 'disconnected_at' => now()]);

        return UserSession::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'router_id' => $router->id,
            'mac_address' => $mac,
            'fingerprint_hash' => $request->header('X-Fingerprint') ?? $request->input('fingerprint') ?? ('fp-'.substr(md5($request->userAgent()), 0, 16)),
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
    }
}