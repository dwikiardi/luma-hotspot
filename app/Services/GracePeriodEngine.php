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

        // iPhone CNA: tidak ada fingerprint/cookie → auto-login session
        // Aman kalau semua disconnected session milik user yg sama (group tamu 1 kamar)
        if ($isCNA && !$hasFingerprint && !$hasCookie && $sessions->isNotEmpty()) {
            $uniqueUsers = $sessions->pluck('user_id')->unique();
            if ($uniqueUsers->count() === 1) {
                return GraceCheckResult::autoLogin($sessions->first());
            }
        }

        // CNA fallback: kalau gak ada grace session (udah di-reactivate sync),
        // cek active sessions via user_id uniqueness + latest radacct MAC
        if ($isCNA && !$hasFingerprint && !$hasCookie && $sessions->isEmpty()) {
            $activeSessions = UserSession::where('status', 'active')
                ->where('router_id', $router->id)
                ->where('expires_at', '>', now())
                ->get();

            if ($activeSessions->isNotEmpty()) {
                // Match by MAC dulu (CNA kirim client_mac)
                if ($hasValidMac) {
                    $macMatch = $activeSessions->firstWhere('mac_address', $mac);
                    if ($macMatch) {
                        return GraceCheckResult::autoLogin($macMatch);
                    }
                    // Cek radacct: user mana yg punya MAC ini (sedang connected)
                    $radUser = \Illuminate\Support\Facades\DB::table('radacct')
                        ->where('callingstationid', $mac)
                        ->whereNull('acctstoptime')
                        ->orderByDesc('acctstarttime')
                        ->first();
                    if ($radUser) {
                        $dbUser = \App\Models\User::where('identity_value', $radUser->username)->first();
                        if ($dbUser) {
                            $radSession = $activeSessions->firstWhere('user_id', $dbUser->id);
                            if ($radSession) {
                                return GraceCheckResult::autoLogin($radSession);
                            }
                        }
                    }
                }
                // Fallback: kalau semua active session user_id-nya sama
                $activeUniqueUsers = $activeSessions->pluck('user_id')->unique();
                if ($activeUniqueUsers->count() === 1) {
                    return GraceCheckResult::autoLogin($activeSessions->first());
                }
            }
        }

        // Fallback: no signal, check by user_id uniqueness
        if (!$hasFingerprint && !$hasCookie && $sessions->isNotEmpty()) {
            $uniqueUsers = $sessions->pluck('user_id')->unique();
            if ($uniqueUsers->count() === 1 && $sessions->first()->disconnected_at?->diffInMinutes(now()) <= 10) {
                return GraceCheckResult::autoLogin($sessions->first());
            }
        }

        foreach ($sessions as $session) {
            $score = 0;

            // Fingerprint match = sinyal terkuat (device yang sama)
            if ($hasFingerprint && $session->fingerprint_hash === $fingerprint) {
                $score += 5;
            }

            // Cookie match = session yang sama
            if ($hasCookie && $session->cookie_token === $cookie) {
                $score += 5;
            }

            // MAC match (tidak wajib, karena random MAC)
            if ($hasValidMac && $session->mac_address === $mac) {
                $score += 2;
            }

            // IP match
            if ($ip && $session->ip_address === $ip) {
                $score += 2;
            }

            if ($ip && $session->ip_address && $session->ip_address === $ip
                && $session->nas_id === $nasId) {
                $score += 1;
            }

            // Fallback: jika tidak ada MAC valid, cocokkan cookie ke user yang sama
            if (!$hasValidMac && $hasCookie) {
                $cookieSession = UserSession::where('cookie_token', $cookie)
                    ->where('router_id', $router->id)
                    ->first();
                if ($cookieSession && $cookieSession->user_id === $session->user_id) {
                    $score += 3;
                }
            }

            // Threshold: fingerprint atau cookie cukup untuk auto-login
            $threshold = 3;
            if ($hasFingerprint || $hasCookie) {
                $threshold = 1;
            } elseif (!$hasValidMac) {
                $threshold = 2;
            }

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

        // Reactivate existing grace session (same device: fingerprint, MAC, cookie, atau user)
        $fingerprint = $request->header('X-Fingerprint') ?? $request->input('fingerprint');
        $cookie = $request->cookie('luma_session');

        $existing = UserSession::where('user_id', $user->id)
            ->where('router_id', $router->id)
            ->where('status', 'disconnected')
            ->where('expires_at', '>', now())
            ->where(function ($q) use ($mac, $fingerprint, $cookie) {
                if ($fingerprint) $q->orWhere('fingerprint_hash', $fingerprint);
                if ($cookie) $q->orWhere('cookie_token', $cookie);
                if ($mac && $mac !== 'unknown') $q->orWhere('mac_address', $mac);
            })
            ->orderByDesc('disconnected_at')
            ->first();

        if ($existing) {
            $existing->update([
                'status' => 'active',
                'login_at' => now(),
                'last_seen_at' => now(),
                'expires_at' => now()->addSeconds($sessionTimeout),
                'disconnected_at' => null,
                'ip_address' => $clientIp ?: $existing->ip_address,
                'mac_address' => $mac !== 'unknown' ? $mac : $existing->mac_address,
                'fingerprint_hash' => $fingerprint ?: $existing->fingerprint_hash,
                'cookie_token' => $cookie ?: $existing->cookie_token,
            ]);

            $this->logDeviceFingerprint($request, $user, $device, $router);
            return $existing;
        }

        // Disconnect session active sebelumnya untuk user ini
        UserSession::where('user_id', $user->id)
            ->where('router_id', $router->id)
            ->where('status', 'active')
            ->update(['status' => 'disconnected', 'disconnected_at' => now()]);

        // Expire session disconnected lama (keep only 1 most recent)
        $keepDisconnected = UserSession::where('user_id', $user->id)
            ->where('router_id', $router->id)
            ->where('status', 'disconnected')
            ->orderByDesc('disconnected_at')
            ->first();

        if ($keepDisconnected) {
            UserSession::where('user_id', $user->id)
                ->where('router_id', $router->id)
                ->where('status', 'disconnected')
                ->where('id', '!=', $keepDisconnected->id)
                ->update(['status' => 'expired']);
        }

        $session = UserSession::create([
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

        // Catat fingerprint device
        $this->logDeviceFingerprint($request, $user, $device, $router);

        return $session;
    }

    /**
     * Catat fingerprint device ke log
     */
    private function logDeviceFingerprint(Request $request, User $user, Device $device, Router $router): void
    {
        try {
            $fp = $request->header('X-Fingerprint') ?? $request->input('fingerprint') ?? ('fp-'.substr(md5($request->userAgent()), 0, 16));
            
            $existing = \App\Models\DeviceFingerprint::where('fingerprint_hash', $fp)->first();
            
            if ($existing) {
                $matches = $existing->match_count + 1;
                $score = min(95, 50 + ($matches * 15)); // 50→65→80→95
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