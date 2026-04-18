<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MikroTikFileController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\FingerprintController;
use App\Http\Controllers\RadiusAccountingController;
use App\Models\Admin;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/hotspot-detect.html', fn () => response('Success', 200));
Route::get('/generate_204', fn () => response('', 204));
Route::get('/ncsi.txt', fn () => response('Microsoft NCSI', 200));
Route::get('/success.txt', fn () => response('success', 200));
Route::get('/connecttest.txt', fn () => response('Microsoft Connect Test', 200));

Route::get('/portal', [PortalController::class, 'show'])->name('portal');

Route::get('/auth/google', [AuthController::class, 'googleRedirect'])->name('login.google');
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
Route::post('/auth/wa/request', [AuthController::class, 'waRequest']);
Route::post('/auth/wa/verify', [AuthController::class, 'waVerify']);
Route::post('/auth/room', [AuthController::class, 'room']);

Route::post('/radius/accounting', [RadiusAccountingController::class, 'handle']);

Route::post("/api/fingerprint/analyze", [FingerprintController::class, "analyze"]);
Route::post('/api/fingerprint', function (Request $request) {
    $response = Http::post(env('FASTAPI_URL').'/api/fingerprint', $request->all());

    return response()->json($response->json());
});

Route::get('/walkthrough', fn () => view('walkthrough.index'))->name('walkthrough');
Route::get('/mikrotik-setup', fn () => view('mikrotik-setup'))->name('mikrotik-setup');

Route::get('/mikrotik/hotspot-files', [MikroTikFileController::class, 'downloadHotspotFiles'])->name('mikrotik.hotspot-files');

Route::get('/', function (Request $request) {
    // #region agent log H1: root redirect to /portal
    $debugLogPath = base_path('.cursor/debug-4dc385.log');
    $debugPayload = [
        'sessionId' => '4dc385',
        'runId' => 'debug_initial',
        'hypothesisId' => 'H1',
        'location' => 'routes/web.php:/ (root redirect)',
        'message' => 'Root route redirecting to /portal',
        'data' => [
            'request_host' => $request->getHost(),
            'request_scheme' => $request->getScheme(),
            'request_path' => $request->path(),
            'nas_id' => $request->query('nas_id'),
            'client_mac' => $request->query('client_mac'),
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ];
    file_put_contents($debugLogPath, json_encode($debugPayload)."\n", FILE_APPEND);
    // #endregion

    return redirect('/portal');
});

// Remove test-login route - just try browser login directly
// Route::get('/test-login', ...)

Route::get('/test-login', function () {
    $admin = Admin::where('email', 'admin@luma.com')->first();

    if (! $admin) {
        return response()->json(['success' => false, 'message' => 'Admin not found']);
    }

    // Login via guard - set user_id in session
    Auth::guard('admin')->login($admin);
    Illuminate\Support\Facades\Request::session()->regenerate();

    return response()->json([
        'success' => true,
        'message' => 'Logged in via admin guard',
        'user' => Auth::guard('admin')->user()?->email,
    ]);
});

Route::get('/test-login-tenant', function () {
    $tenantUser = TenantUser::where('email', 'owner@hotelmerdeka.com')->first();

    if (! $tenantUser) {
        return response()->json(['success' => false, 'message' => 'Tenant user not found']);
    }

    // Login via tenant_users guard
    Auth::guard('tenant_users')->login($tenantUser);
    Illuminate\Support\Facades\Request::session()->regenerate();

    return response()->json([
        'success' => true,
        'message' => 'Logged in via tenant guard',
        'user' => Auth::guard('tenant_users')->user()?->email,
        'tenant' => $tenantUser->tenant->name,
    ]);
});

// Impersonation route - must be before any tenant routes

Route::get('/impersonate/{userId}', function (int $userId) {
    $user = TenantUser::with('tenant')->findOrFail($userId);
    
    // Clear any existing tenant user auth
    Auth::guard('tenant_users')->logout();
    
    // Login as the tenant user
    Auth::guard('tenant_users')->login($user);
    session()->regenerate();
    
    // Get tenant slug for redirect
    $slug = $user->tenant?->slug ?? 'unknown';
    
    // Redirect to tenant dashboard
    return redirect()->to("/dashboard/venue/{$slug}");
})->name('impersonate');

// DEBUG ROUTE - Remove after fixing
Route::get('/debug/portal', function (Illuminate\Http\Request $request) {
    $data = [
        'query_params' => $request->all(),
        'nas_id' => $request->query('nas_id'),
        'user_agent' => $request->userAgent(),
        'ip' => $request->ip(),
    ];
    
    if ($request->query('nas_id')) {
        $router = App\Models\Router::where('nas_identifier', $request->query('nas_id'))->first();
        if ($router) {
            $config = $router->tenant->portalConfig;
            $data['router'] = ['name' => $router->name];
            $data['config'] = [
                'methods' => $config->active_login_methods,
                'custom_enabled' => $config->custom_login_enabled,
            ];
        } else {
            $data['error'] = 'Router not found';
        }
    }
    
    return response()->json($data);
});
