<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\User;
use App\Models\UserSession;
use App\Services\AnalyticsEngine;
use App\Services\GracePeriodEngine;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function __construct(
        private GracePeriodEngine $graceEngine,
        private AnalyticsEngine $analytics
    ) {}

    public function show(Request $request)
    {
        $nasId = $request->query('nas_id');
        $mac = $request->query('client_mac') ?? $request->query('mac') ?? $request->query('callingstationid') ?? 'unknown';
        $linkLogin = $request->query('link_login');
        $dstUrl = $request->query('dst') ?? $request->query('redirect') ?? 'https://www.google.com';

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
                'dstUrl' => 'https://www.google.com',
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

        $this->analytics->track('portal_opened', [
            'tenant_id' => $router->tenant_id,
            'router_id' => $router->id,
            'mac' => $mac,
            'ip' => $clientIp ?: null,
        ]);

        $graceResult = $this->graceEngine->check($request, $router);

        $cookie = $request->cookie('luma_session');
        $activeSession = $cookie
            ? UserSession::where('cookie_token', $cookie)
                ->where('router_id', $router->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->first()
            : null;

        if ($activeSession) {
            $user = User::find($activeSession->user_id);
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

    private function silentAutoLogin(Request $request, $session, Router $router, ?string $linkLogin, string $dstUrl, string $clientIp)
    {
        $user = User::find($session->user_id);

        if (! $user) {
            return redirect($dstUrl);
        }

        $session->update([
            'status' => 'active',
            'last_seen_at' => now(),
        ]);

        $username = $user->identity_value;
        $password = $user->identity_value;

        $redirectUrl = $this->buildMikroTikLoginUrl($router, $username, $password, $linkLogin, $dstUrl);

        $this->analytics->track('auto_reconnect', [
            'tenant_id' => $router->tenant_id,
            'router_id' => $router->id,
            'user_id' => $session->user_id,
            'device_id' => $session->device_id,
            'mac' => $session->mac_address,
            'ip' => $clientIp,
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

    private function buildMikroTikLoginUrl(Router $router, string $username, string $password, ?string $linkLogin, string $dstUrl): string
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
        return str_contains($ua, 'CaptiveNetworkSupport')
            || str_contains($ua, 'wispr')
            || str_contains($ua, 'CaptiveNetwork');
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
}