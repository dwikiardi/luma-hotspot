<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class DebugRequestLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        // #region agent log H0/H5: request enters + response redirect chain
        try {
            $debugLogPath = base_path('.cursor/debug-4dc385.log');
            $payload = [
                'sessionId' => '4dc385',
                'runId' => 'debug_initial',
                'hypothesisId' => 'H0',
                'location' => 'DebugRequestLogger.php:handle',
                'message' => 'Request entered Laravel middleware',
                'data' => [
                    'method' => $request->method(),
                    'host' => $request->getHost(),
                    'scheme' => $request->getScheme(),
                    'path' => $request->path(),
                    'full_url' => $request->fullUrl(),
                    'has_luma_session_cookie' => $request->cookies->has('luma_session'),
                    'has_session_cookie' => $request->cookies->has(config('session.cookie')),
                    'session_cookie_name' => config('session.cookie'),
                    'admin_guard_check' => Auth::guard('admin')->check(),
                    'web_guard_check' => Auth::guard('web')->check(),
                    'user_agent_prefix' => substr((string) $request->userAgent(), 0, 80),
                    'route_name' => optional($request->route())->getName(),
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ];
            $bytesWritten = file_put_contents($debugLogPath, json_encode($payload) . "\n", FILE_APPEND);
            if ($bytesWritten === false) {
                Log::error('debug NDJSON write failed', [
                    'path' => $debugLogPath,
                    'hypothesisId' => 'H0',
                    'location' => 'DebugRequestLogger.php:handle',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('debug NDJSON logger exception', [
                'hypothesisId' => 'H0',
                'location' => 'DebugRequestLogger.php:handle',
                'error' => $e->getMessage(),
            ]);
        }
        // #endregion

        $response = $next($request);

        // #region agent log H5: response status and Location (if redirect)
        try {
            $debugLogPath = base_path('.cursor/debug-4dc385.log');
            $location = $response->headers->get('Location');
            $payload = [
                'sessionId' => '4dc385',
                'runId' => 'debug_initial',
                'hypothesisId' => 'H5',
                'location' => 'DebugRequestLogger.php:handle',
                'message' => 'Response leaving Laravel middleware',
                'data' => [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'route_name' => optional($request->route())->getName(),
                    'admin_guard_check' => Auth::guard('admin')->check(),
                    'status' => $response->getStatusCode(),
                    'is_redirect' => $response->isRedirection(),
                    'location_header' => $location,
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ];
            @file_put_contents($debugLogPath, json_encode($payload) . "\n", FILE_APPEND);
        } catch (\Throwable) {
            // ignore
        }
        // #endregion

        return $response;
    }
}

