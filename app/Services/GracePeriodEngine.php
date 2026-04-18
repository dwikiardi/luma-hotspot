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

        // #region agent log H1-H3: grace check inputs
        $debugLogPath = base_path('.cursor/debug-4dc385.log');
        $debugPayload = [
            'sessionId' => '4dc385',
            'runId' => 'debug_initial',
            'hypothesisId' => 'H1',
            'location' => 'GracePeriodEngine.php:check',
            'message' => 'Grace check request context',
            'data' => [
                'host' => $request->getHost(),
                'scheme' => $request->getScheme(),
                'path' => $request->path(),
                'nas_id' => $request->query('nas_id'),
                'client_mac_param_present' => $request->query->has('client_mac'),
                'client_mac_param_len' => is_string($mac) ? strlen($mac) : null,
                'has_luma_session_cookie' => $request->cookies->has('luma_session'),
                'luma_session_cookie_len' => is_string($cookie) ? strlen($cookie) : null,
                'has_fingerprint_header' => $fingerprint !== null && $fingerprint !== '',
                'ip' => $ip,
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ];
        file_put_contents($debugLogPath, json_encode($debugPayload) . "\n", FILE_APPEND);
        // #endregion

        $config = $router->tenant->portalConfig;
        $graceSeconds = $config->grace_period_seconds;

        $sessions = UserSession::where('status', 'disconnected')
            ->where('expires_at', '>', now())
            ->where('router_id', $router->id)
            ->get();

        $maxScore = -1;
        $maxSignals = [];
        $maxSessionId = null;

        foreach ($sessions as $session) {
            $score = 0;
            $signals = [];

            if ($session->mac_address === $mac) {
                $score += 4;
                $signals['mac'] = 4;
            }

            if ($session->cookie_token === $cookie) {
                $score += 4;
                $signals['cookie'] = 4;
            }

            if ($session->fingerprint_hash === $fingerprint) {
                $score += 3;
                $signals['fingerprint'] = 3;
            }

            if ($session->ip_address === $ip) {
                $score += 1;
                $signals['ip'] = 1;
            }

            if ($score > $maxScore) {
                $maxScore = $score;
                $maxSignals = $signals;
                $maxSessionId = $session->id;
            }

            if ($score >= 3) {
                // #region agent log H1-H2: grace autoLogin decision
                $debugPayload = [
                    'sessionId' => '4dc385',
                    'runId' => 'debug_initial',
                    'hypothesisId' => 'H1',
                    'location' => 'GracePeriodEngine.php:check',
                    'message' => 'Grace check matched disconnected session',
                    'data' => [
                        'matched_session_id' => $session->id,
                        'matched_session_status' => $session->status,
                        'matched_session_expires_at' => $session->expires_at?->toISOString(),
                        'matched_score' => $score,
                        'matched_signals' => $signals,
                    ],
                    'timestamp' => (int) round(microtime(true) * 1000),
                ];
                file_put_contents($debugLogPath, json_encode($debugPayload) . "\n", FILE_APPEND);
                // #endregion
                return GraceCheckResult::autoLogin($session);
            }
        }

        // #region agent log H1-H3: grace requireLogin decision
        $debugPayload = [
            'sessionId' => '4dc385',
            'runId' => 'debug_initial',
            'hypothesisId' => 'H2',
            'location' => 'GracePeriodEngine.php:check',
            'message' => 'Grace check did not match disconnected sessions',
            'data' => [
                'disconnected_sessions_checked' => $sessions->count(),
                'max_score' => $maxScore,
                'max_signals' => $maxSignals,
                'max_session_id' => $maxSessionId,
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ];
        file_put_contents($debugLogPath, json_encode($debugPayload) . "\n", FILE_APPEND);
        // #endregion
        return GraceCheckResult::requireLogin();
    }

    public function onDisconnect(string $mac, string $nasId): void
    {
        $router = Router::where('nas_identifier', $nasId)->first();
        if (! $router) {
            return;
        }

        $session = UserSession::where('mac_address', $mac)
            ->where('router_id', $router->id)
            ->where('status', 'active')
            ->first();

        if (! $session) {
            return;
        }

        $config = $router->tenant->portalConfig;
        $graceSeconds = $config->grace_period_seconds;

        $session->refreshExpiry($graceSeconds);
    }

    public function createSession(
        Request $request,
        User $user,
        Device $device,
        Router $router
    ): UserSession {
        $config = $router->tenant->portalConfig;
        $graceSeconds = $config->grace_period_seconds;

        $mac = $request->query('client_mac') ?? $request->input('client_mac') ?? 'unknown';

        return UserSession::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'router_id' => $router->id,
            'mac_address' => $mac,
            'fingerprint_hash' => $request->header('X-Fingerprint') ?? $request->input('fingerprint') ?? ('fp-'.substr(md5($request->userAgent()), 0, 16)),
            'cookie_token' => UserSession::generateCookieToken(),
            'ip_address' => $request->ip(),
            'login_at' => now(),
            'last_seen_at' => now(),
            'expires_at' => now()->addSeconds($graceSeconds),
            'status' => 'active',
            'nas_id' => $router->nas_identifier,
            'login_method' => $request->input('login_method') ?? $method ?? 'room',
            'user_agent' => $request->userAgent(),
            'meta' => [
                'room_number' => $request->input('room_number'),
                'circuit_id' => $request->input('circuit_id'),
                'remote_id' => $request->input('remote_id'),
            ],
        ]);
    }
}
