<?php

namespace App\Http\Controllers;

use App\Models\DhcpFingerprint;
use App\Models\Router;
use App\Models\User;
use App\Models\UserSession;
use App\Services\AnalyticsEngine;
use App\Services\GracePeriodEngine;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public static function sanitizeDst(?string $dst, string $default = 'https://www.google.com'): string
    {
        if (empty($dst)) return $default;
        $cnaProbes = [
            'captive.apple.com',
            'connectivitycheck.gstatic.com',
            'connectivitycheck.android.com',
            'clients3.google.com/generate_204',
        ];
        foreach ($cnaProbes as $probe) {
            if (str_contains($dst, $probe)) return $default;
        }
        return $dst;
    }

    public function __construct(
        private GracePeriodEngine $graceEngine,
        private AnalyticsEngine $analytics
    ) {}

    public function show(Request $request)
    {
        $nasId = $request->query('nas_id');
        $mac = $request->query('client_mac') ?? $request->query('mac') ?? $request->query('callingstationid') ?? 'unknown';
        $linkLogin = $request->query('link_login');
        $dstUrl = self::sanitizeDst($request->query('dst') ?? $request->query('redirect'));

        // Get real client IP from various sources (never empty string)
        $clientIp = $request->query('ip') 
            ?? $request->header('X-Forwarded-For') 
            ?? $request->header('X-Real-IP') 
            ?? $request->ip();
        $clientIp = $clientIp ?: null;

        if (! $nasId) {
            return response()->view('portal', [
                'methods' => [],
                'branding' => ['name' => 'Luma Network', 'color' => '#6366f1'],
                'nasId' => null,
                'mac' => null,
                'isCNA' => false,
                'isIOS' => false,
                'isAndroid' => false,
                'relayInfo' => [],
                'serverFingerprint' => [],
                'router' => null,
                'customLoginEnabled' => false,
                'customLoginLabel' => 'Nomor Kamar',
                'customLoginPlaceholder' => 'Contoh: 101',
                'linkLogin' => null,
                'dstUrl' => self::sanitizeDst(null),
            ]);
        }

        $router = Router::with('tenant.portalConfig')
            ->where('nas_identifier', $nasId)
            ->first();

        if (! $router) {
            return response()->view('portal', [
                'methods' => [],
                'branding' => ['name' => 'Luma Network', 'color' => '#6366f1'],
                'nasId' => $nasId,
                'mac' => $mac,
                'isCNA' => false,
                'isIOS' => false,
                'isAndroid' => false,
                'relayInfo' => [],
                'serverFingerprint' => [],
                'router' => null,
                'customLoginEnabled' => false,
                'customLoginLabel' => 'Nomor Kamar',
                'customLoginPlaceholder' => 'Contoh: 101',
                'linkLogin' => $linkLogin,
                'dstUrl' => $dstUrl,
            ]);
        }

        // Normal flow
        $isCNA = $this->detectCNA($request->userAgent() ?? '');
        $isIOS = $this->isIOS($request->userAgent() ?? '');
        $isBrowser = $request->query('browser') === '1';

        $this->analytics->track('portal_opened', [
            'tenant_id' => $router->tenant_id,
            'router_id' => $router->id,
            'mac' => $mac,
            'ip' => $clientIp ?: null,
        ]);

        \App\Services\ActivityLogger::portalOpened($mac, $clientIp ?? 'unknown', $this->detectCNA($request->userAgent() ?? ''));

        $tenantRouterIds = \App\Models\Router::where('tenant_id', $router->tenant_id)->pluck('id')->toArray();

        $graceResult = $this->graceEngine->check($request, $router);

        // Tier 0: Device DNA — kenali device bahkan dengan MAC baru (private MAC randomization)
        // Cari dari DHCP fingerprint yang sudah direcord di dhcp-hook, cross-MAC
        $activeSession = $this->resolveByDeviceDna($mac, $tenantRouterIds);

        // Tier 1: MAC address lookup
        if (! $activeSession && $mac && $mac !== 'unknown') {
            $activeSession = UserSession::where('mac_address', $mac)
                ->whereIn('router_id', $tenantRouterIds)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->first();
        }

        // Tier 2: Cookie token
        if (! $activeSession) {
            $cookie = $request->cookie('luma_session');
            $activeSession = $cookie
                ? UserSession::where('cookie_token', $cookie)
                    ->whereIn('router_id', $tenantRouterIds)
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->first()
                : null;
        }

        // Tier 3: JS browser fingerprint
        if (! $activeSession) {
            $fp = $request->header('X-Fingerprint') ?? $request->query('fingerprint');
            $activeSession = $fp
                ? UserSession::where('fingerprint_hash', $fp)
                    ->whereIn('router_id', $tenantRouterIds)
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->first()
                : null;
        }

        if ($activeSession) {
            $user = User::find($activeSession->user_id);
            if ($user && empty($activeSession->username)) {
                $activeSession->update(['username' => $user->identity_value]);
            }

            $otherActiveDevices = UserSession::where('user_id', $activeSession->user_id)
                ->where('router_id', $router->id)
                ->where('status', 'active')
                ->where('id', '!=', $activeSession->id)
                ->count();
            if ($otherActiveDevices === 0) {
                try {
                    app(\App\Services\MikroTikApiService::class)->disconnectUser(
                        $user?->identity_value ?? '',
                        $router
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('MikroTik pre-disconnect failed', ['error' => $e->getMessage()]);
                }
            }

            \App\Services\ActivityLogger::portalActiveRedirect(
                $user?->identity_value ?? '?',
                $mac
            );
            $loginUrl = $this->buildMikroTikLoginUrl(
                $router,
                $user?->identity_value ?? '',
                $user?->identity_value ?? '',
                $linkLogin,
                $dstUrl
            );

            return redirect($loginUrl)
                ->withCookie(cookie(
                    'luma_session',
                    $activeSession->cookie_token,
                    (int) ($activeSession->seconds_remaining / 60),
                    '/',
                    null,
                    false,
                    false,
                    false,
                    'Lax'
                ));
        }

        if ($graceResult->shouldAutoLogin) {
            $session = $graceResult->session;

            if ($session->status === 'active') {
                $user = User::find($session->user_id);
                if ($user && empty($session->username)) {
                    $session->update(['username' => $user->identity_value]);
                }

                $otherActiveDevices = UserSession::where('user_id', $session->user_id)
                    ->where('router_id', $router->id)
                    ->where('status', 'active')
                    ->where('id', '!=', $session->id)
                    ->count();
                if ($otherActiveDevices === 0) {
                    try {
                        app(\App\Services\MikroTikApiService::class)->disconnectUser(
                            $user?->identity_value ?? '',
                            $router
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('MikroTik pre-disconnect failed', ['error' => $e->getMessage()]);
                    }
                }

                $loginUrl = $this->buildMikroTikLoginUrl(
                    $router,
                    $user?->identity_value ?? '',
                    $user?->identity_value ?? '',
                    $linkLogin,
                    $dstUrl
                );

                return redirect($loginUrl)
                    ->withCookie(cookie(
                        'luma_session',
                        $session->cookie_token,
                        (int) ($session->seconds_remaining / 60),
                        '/',
                        null,
                        false,
                        false,
                        false,
                        'Lax'
                    ));
            }

            return $this->silentAutoLogin($request, $session, $router, $linkLogin, $dstUrl, $clientIp);
        }

        // All auto-login failed → CNA welcome page or login form
        if ($isCNA && !$isBrowser) {
            $branding = $router->tenant->portalConfig->branding ?? [];
            return view('portal.welcome', [
                'venueName' => $branding['name'] ?? $router->tenant->name ?? 'Luma Network',
                'logo' => $branding['logo'] ?? null,
                'color' => $branding['color'] ?? '#6366f1',
                'colorDark' => $this->adjustBrightness($branding['color'] ?? '#6366f1', -30),
                'nasId' => $nasId,
                'mac' => $mac,
                'linkLogin' => $linkLogin,
                'dstUrl' => $dstUrl,
            ]);
        }

        \App\Services\ActivityLogger::portalLoginForm('no active session & no grace match');

        $config = $router->tenant->portalConfig;
        $methods = $config->active_login_methods;
        $branding = $config->branding;
        $customLoginEnabled = $config->custom_login_enabled ?? false;
        $customLoginLabel = $config->custom_login_label ?? 'Nomor Kamar';
        $customLoginPlaceholder = $config->custom_login_placeholder ?? 'Contoh: 101';
        $isCNA = $this->detectCNA($request->userAgent() ?? '');
        $isIOS = $this->isIOS($request->userAgent() ?? '');
        $isAndroid = $this->isAndroid($request->userAgent() ?? '');

        if ($isCNA) {
            $methods['google'] = false;
        }

        $relayInfo = $this->parseOption82($request);
        $serverFingerprint = $this->buildServerFingerprint($request, $relayInfo, $clientIp, $mac);

        return view('portal', [
            'methods' => $methods,
            'branding' => $branding,
            'nasId' => $nasId,
            'mac' => $mac,
            'isCNA' => $isCNA,
            'isIOS' => $isIOS,
            'isAndroid' => $isAndroid,
            'relayInfo' => $relayInfo,
            'serverFingerprint' => $serverFingerprint,
            'router' => $router,
            'customLoginEnabled' => $customLoginEnabled,
            'customLoginLabel' => $customLoginLabel,
            'customLoginPlaceholder' => $customLoginPlaceholder,
            'linkLogin' => $linkLogin,
            'dstUrl' => $dstUrl,
        ]);
    }

    private function silentAutoLogin(Request $request, $session, Router $router, ?string $linkLogin, string $dstUrl, ?string $clientIp)
    {
        $user = User::find($session->user_id);

        if (! $user) {
            return redirect($dstUrl);
        }

        $username = $user->identity_value;

        $session->update([
            'status' => 'active',
            'username' => $username,
            'login_at' => now(),
            'last_seen_at' => now(),
            'expires_at' => now()->addSeconds(
                ($router->tenant->portalConfig->session_timeout ?? 0) > 0
                    ? $router->tenant->portalConfig->session_timeout
                    : 365 * 86400
            ),
            'disconnected_at' => null,
        ]);
        $password = $user->identity_value;

        \App\Services\ActivityLogger::portalSilentLogin($username, $session->mac_address, $clientIp ?? 'unknown');

        // Hanya disconnect MikroTik kalau ini satu-satunya device (gak ganggu device lain sharing room)
        $otherActiveDevices = UserSession::where('user_id', $user->id)
            ->where('router_id', $router->id)
            ->where('status', 'active')
            ->where('id', '!=', $session->id)
            ->count();
        if ($otherActiveDevices === 0) {
            try {
                app(\App\Services\MikroTikApiService::class)->disconnectUser($username, $router);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('MikroTik pre-disconnect failed', ['error' => $e->getMessage()]);
            }
        }

        $redirectUrl = $this->buildMikroTikLoginUrl($router, $username, $password, $linkLogin, $dstUrl);

        $this->analytics->track('auto_reconnect', [
            'tenant_id' => $router->tenant_id,
            'router_id' => $router->id,
            'user_id' => $session->user_id,
            'device_id' => $session->device_id,
            'mac' => $session->mac_address,
            'ip' => $clientIp ?: null,
            'redirect_url' => $redirectUrl,
        ]);

        return redirect($redirectUrl)
            ->withCookie(cookie(
                'luma_session',
                $session->cookie_token,
                (int) ($session->seconds_remaining / 60),
                '/',
                null,
                false,
                false,
                false,
                'Lax'
            ));
    }

    /**
     * Tier 0: Device DNA lookup — kenali device dari DHCP fingerprint meskipun MAC berganti.
     * Cari fingerprint terbaru untuk MAC ini, cari DNA profile, lalu cari session user.
     */
    private function resolveByDeviceDna(string $mac, array $tenantRouterIds): ?\App\Models\UserSession
    {
        if ($mac === 'unknown') return null;

        $latestFp = DhcpFingerprint::where('mac_address', $mac)
            ->whereNotNull('fingerprint_hash')
            ->latest('detected_at')
            ->first();

        if (!$latestFp) return null;

        $dnaService = app(\App\Services\DeviceDnaService::class);
        $user = $dnaService->resolveIdentity(
            $mac,
            $latestFp->fingerprint_hash,
            $latestFp->hostname
        );

        if (!$user) return null;

        $session = $dnaService->findActiveSessionForUser($user, \App\Models\Router::find($tenantRouterIds[0] ?? 0));

        if ($session) {
            \App\Services\ActivityLogger::log('device_dna', 'auto_login',
                "Device DNA auto-login: MAC={$mac} user={$user->identity_value} session={$session->id}",
                ['mac' => $mac, 'user_id' => $user->id, 'session_id' => $session->id]
            );
        }

        return $session;
    }

    public function buildMikroTikLoginUrl(Router $router, string $username, string $password, ?string $linkLogin, string $dstUrl): string
    {
        if ($linkLogin) {
            $separator = str_contains($linkLogin, '?') ? '&' : '?';
            return $linkLogin . $separator . 'username=' . urlencode($username) . '&password=' . urlencode($password) . '&dst=' . urlencode($dstUrl);
        }

        if ($router->hotspot_address) {
            $hotspotAddr = $router->hotspot_address;
            if (!str_starts_with($hotspotAddr, 'http')) {
                $hotspotAddr = 'http://' . $hotspotAddr;
            }
            return $hotspotAddr . '/login?username=' . urlencode($username) . '&password=' . urlencode($password) . '&dst=' . urlencode($dstUrl);
        }

        return $dstUrl;
    }

    private function buildAlreadyOnlineUrl(string $dstUrl): string
    {
        return url('/portal/online?dst=' . urlencode($dstUrl));
    }

    private function detectCNA(string $ua): bool
    {
        // iOS: CaptiveNetworkSupport in User-Agent
        // Android: Some captive portal WebViews also have this
        return str_contains($ua, 'CaptiveNetworkSupport')
            || str_contains($ua, 'wispr')
            || str_contains($ua, 'CaptiveNetwork')
            // iOS 12+ sometimes uses different UA for captive portal
            || (str_contains($ua, 'iPhone') && str_contains($ua, 'OS 1') && !str_contains($ua, 'Safari'));
    }

    private function isIOS(string $ua): bool
    {
        return str_contains($ua, 'iPhone') || str_contains($ua, 'iPad');
    }

    private function isAndroid(string $ua): bool
    {
        return str_contains($ua, 'Android');
    }

    private function parseOption82(Request $request): array
    {
        $circuitId = $request->query('circuit_id');
        preg_match('/kamar(\d+)/i', $circuitId ?? '', $matches);

        return [
            'circuit_id' => $circuitId,
            'remote_id' => $request->query('remote_id'),
            'room_number' => $matches[1] ?? null,
        ];
    }

    private function buildServerFingerprint(Request $request, array $relay, ?string $clientIp, string $mac): array
    {
        return [
            'ip' => $clientIp ?? $request->ip(),
            'user_agent' => $request->userAgent(),
            'mac' => $mac,
            'nas_id' => $request->query('nas_id'),
            'accept_lang' => $request->header('Accept-Language'),
            'room_hint' => $relay['room_number'],
            'circuit_id' => $relay['circuit_id'],
            'timestamp' => now()->timestamp,
        ];
    }

    private function adjustBrightness(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + $percent));
        $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + $percent));
        $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + $percent));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    public static function adjustColorStatic(string $hex, int $percent): string
    {
        return (new self(app(\App\Services\GracePeriodEngine::class), app(\App\Services\AnalyticsEngine::class)))
            ->adjustBrightness($hex, $percent);
    }

    public static function validateRoomStatic($roomNumber, $config): bool
    {
        return (new self(app(\App\Services\GracePeriodEngine::class), app(\App\Services\AnalyticsEngine::class)))
            ->validateRoomNumber($roomNumber, $config);
    }
}