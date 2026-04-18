<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Services\AnalyticsEngine;
use App\Services\GracePeriodEngine;
use App\Services\MikroTikRadiusService;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function __construct(
        private GracePeriodEngine $graceEngine,
        private AnalyticsEngine $analytics,
        private MikroTikRadiusService $radius
    ) {}

    public function show(Request $request)
    {
        $nasId = $request->query('nas_id');
        $mac = $request->query('client_mac');

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
                'linkLogin' => $request->query('link_login'),
                'dstUrl' => $request->query('dst') ?? $request->query('redirect') ?? 'https://www.google.com',
            ]);
        }

        $this->analytics->track('portal_opened', [
            'tenant_id' => $router->tenant_id,
            'router_id' => $router->id,
            'mac' => $mac,
            'ip' => $request->ip(),
        ]);

        $graceResult = $this->graceEngine->check($request, $router);

        if ($graceResult->shouldAutoLogin) {
            // #region agent log H1: portal triggers silent auto-login
            $debugLogPath = base_path('.cursor/debug-4dc385.log');
            $debugPayload = [
                'sessionId' => '4dc385',
                'runId' => 'debug_initial',
                'hypothesisId' => 'H1',
                'location' => 'PortalController.php:show',
                'message' => 'Portal show triggering silentAutoLogin',
                'data' => [
                    'request_host' => $request->getHost(),
                    'request_scheme' => $request->getScheme(),
                    'request_path' => $request->path(),
                    'nas_id' => $nasId,
                    'client_mac' => $mac,
                    'has_luma_session_cookie' => $request->cookies->has('luma_session'),
                    'matched_user_session_id' => $graceResult->session?->id,
                    'matched_seconds_remaining' => $graceResult->session?->seconds_remaining,
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ];
            file_put_contents($debugLogPath, json_encode($debugPayload)."\n", FILE_APPEND);
            // #endregion

            return $this->silentAutoLogin($request, $graceResult->session, $router);
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
        $serverFingerprint = $this->buildServerFingerprint($request, $relayInfo);
        $linkLogin = $request->query('link_login');
        $dstUrl = $request->query('dst') ?? $request->query('redirect') ?? 'https://www.google.com';

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

    private function silentAutoLogin(Request $request, $session, Router $router)
    {
        $this->radius->acceptUser(
            mac: $session->mac_address,
            nasId: $router->nas_identifier,
            sessionTimeout: $session->seconds_remaining
        );

        $this->analytics->track('auto_reconnect', [
            'tenant_id' => $router->tenant_id,
            'router_id' => $router->id,
            'user_id' => $session->user_id,
            'device_id' => $session->device_id,
            'mac' => $session->mac_address,
            'ip' => $request->ip(),
        ]);

        // #region agent log H4: redirect target from silent auto-login
        $debugLogPath = base_path('.cursor/debug-4dc385.log');
        $debugPayload = [
            'sessionId' => '4dc385',
            'runId' => 'debug_initial',
            'hypothesisId' => 'H4',
            'location' => 'PortalController.php:silentAutoLogin',
            'message' => 'Silent auto-login redirecting to fixed external URL',
            'data' => [
                'target_url' => 'https://www.google.com',
                'matched_user_session_id' => $session->id,
                'cookie_minutes' => (int) ($session->seconds_remaining / 60),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ];
        file_put_contents($debugLogPath, json_encode($debugPayload)."\n", FILE_APPEND);
        // #endregion

        return redirect('https://www.google.com')
            ->withCookie(cookie(
                'luma_session',
                $session->cookie_token,
                (int) ($session->seconds_remaining / 60),
                '/',
                null,
                true,
                true,
                false,
                'Lax'
            ));
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

    private function buildServerFingerprint(Request $request, array $relay): array
    {
        return [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'mac' => $request->query('client_mac'),
            'nas_id' => $request->query('nas_id'),
            'accept_lang' => $request->header('Accept-Language'),
            'room_hint' => $relay['room_number'],
            'circuit_id' => $relay['circuit_id'],
            'timestamp' => now()->timestamp,
        ];
    }
}
