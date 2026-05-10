<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MikroTikFileController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\FingerprintController;
use App\Http\Controllers\RadiusAccountingController;
use App\Models\TenantUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/hotspot-detect.html', fn () => response('Success', 200));
Route::get('/generate_204', fn () => response('', 204));
Route::get('/ncsi.txt', fn () => response('Microsoft NCSI', 200));
Route::get('/success.txt', fn () => response('success', 200));
Route::get('/connecttest.txt', fn () => response('Microsoft Connect Test', 200));

Route::get('/portal', [PortalController::class, 'show'])->name('portal');

// iOS CNA Login — create session from welcome page before profile download
Route::post('/cna-login', function (\Illuminate\Http\Request $request) {
    $roomNumber = $request->input('room_number');
    $nasId = $request->input('nas_id');
    $mac = $request->input('client_mac', 'unknown');

    $router = \App\Models\Router::where('nas_identifier', $nasId)->first();
    if (! $router) {
        return response()->json(['success' => false, 'message' => 'Invalid venue']);
    }

    // Create user + session
    $user = \App\Models\User::updateOrCreate(
        ['identity_value' => $roomNumber, 'identity_type' => 'room'],
        ['name' => 'Room ' . $roomNumber]
    );

    // Setup RADIUS
    \Illuminate\Support\Facades\DB::table('radcheck')->updateOrInsert(
        ['username' => $user->identity_value],
        ['attribute' => 'Cleartext-Password', 'op' => ':=', 'value' => $user->identity_value]
    );

    // Create/reactivate session
    $device = \App\Models\Device::firstOrCreate(
        ['fingerprint_hash' => 'fp-cna-' . substr(md5($mac), 0, 12)],
        ['user_id' => $user->id]
    );

    $engine = app(\App\Services\GracePeriodEngine::class);
    $session = $engine->createSession($request, $user, $device, $router);

    // Build connect URL with room param for personalized connected page
    $escapeParams = http_build_query(array_filter([
        'nas_id' => $nasId,
        'client_mac' => $mac,
        'room' => $roomNumber,
        'session_token' => $session->cookie_token,
    ]));

    return response()->json([
        'success' => true,
        'connect_url' => url('/cna-escape?' . $escapeParams),
        'message' => 'Room ' . $roomNumber . ' ready',
    ]);
});

// Personalized connected page — shows after profile install + Web Clip tap
Route::get('/connected', function (\Illuminate\Http\Request $request) {
    $room = $request->query('room', '');
    $sessionToken = $request->query('session_token', '');
    $nasId = $request->query('nas_id', '');
    $dst = $request->query('dst', 'https://www.google.com');
    if (empty($dst)) $dst = 'https://www.google.com';

    $router = $nasId ? \App\Models\Router::where('nas_identifier', $nasId)->first() : null;
    $branding = $router?->tenant?->portalConfig?->branding ?? [];
    $color = $branding['color'] ?? '#6366f1';
    $venueName = $branding['name'] ?? $router?->tenant?->name ?? 'Luma Network';

    // Redirect ke PAP login MikroTik (dengan cookie sekarang — gak akan loop)
    // Cookie mencegah intercept ulang karena browser kirim cookie di setiap request
    $redirectUrl = $dst;
    if ($router) {
        $session = \App\Models\UserSession::where('cookie_token', $sessionToken)
            ->where('router_id', $router->id)
            ->where('status', 'active')
            ->first();
        if ($session) {
            $user = \App\Models\User::find($session->user_id);
            if ($user && $router->hotspot_address) {
                $redirectUrl = 'http://' . $router->hotspot_address . '/login?username='
                    . urlencode($user->identity_value)
                    . '&password=' . urlencode($user->identity_value)
                    . '&dst=' . urlencode($dst);
            }
        }
    }

    return response()->view('portal.connected', [
        'room' => $room,
        'venueName' => $venueName,
        'logo' => $branding['logo'] ?? null,
        'color' => $color,
        'colorDark' => \App\Http\Controllers\PortalController::adjustColorStatic($color, -30),
        'redirectUrl' => $redirectUrl,
    ]);
})->name('connected');

// iOS CNA Escape: serve .mobileconfig Web Clip untuk keluar dari CNA ke Safari
Route::get('/cna-escape', function (\Illuminate\Http\Request $request) {
    $nasId = $request->query('nas_id', '');
    $mac = $request->query('client_mac', '');
    $linkLogin = $request->query('link_login', '');
    $dst = $request->query('dst', '');
    if (empty($dst)) $dst = 'https://www.google.com';
    $room = $request->query('room', '');
    $sessionToken = $request->query('session_token', '');

    // Build connected URL with all params including room
    $connectedParams = http_build_query(array_filter([
        'room' => $room,
        'session_token' => $sessionToken,
        'nas_id' => $nasId,
        'dst' => $dst,
    ]));
    $connectedUrl = url('/connected?' . $connectedParams);

    $innerUuid = str_replace('-', '', \Illuminate\Support\Str::uuid()->toString());
    $outerUuid = str_replace('-', '', \Illuminate\Support\Str::uuid()->toString());

    $mobileconfig = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>PayloadContent</key>
    <array>
        <dict>
            <key>FullScreen</key>
            <false/>
            <key>IsRemovable</key>
            <true/>
            <key>Label</key>
            <string>WiFi Portal</string>
            <key>PayloadIdentifier</key>
            <string>id.lumanetwork.portal.' . $innerUuid . '</string>
            <key>PayloadType</key>
            <string>com.apple.webClip.managed</string>
            <key>PayloadUUID</key>
            <string>' . $innerUuid . '</string>
            <key>PayloadVersion</key>
            <integer>1</integer>
            <key>Icon</key>
            <data>iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAIAAABvFaqvAAAAH0lEQVR4nGNITvtIFcQwatCoQaMGjRo0atCoQQNvEACcruKuF8L3sgAAAABJRU5ErkJggg==</data>
            <key>URL</key>
            <string>' . htmlspecialchars($connectedUrl) . '</string>
        </dict>
    </array>
    <key>PayloadDescription</key>
    <string>Opens the WiFi login portal in Safari for auto-login.</string>
    <key>PayloadDisplayName</key>
    <string>Luma WiFi Portal</string>
    <key>PayloadIdentifier</key>
    <string>id.lumanetwork.portal</string>
    <key>PayloadOrganization</key>
    <string>Luma Network</string>
    <key>PayloadRemovalDisallowed</key>
    <false/>
    <key>PayloadScope</key>
    <string>System</string>
    <key>PayloadType</key>
    <string>Configuration</string>
    <key>PayloadUUID</key>
    <string>' . $outerUuid . '</string>
    <key>PayloadVersion</key>
    <integer>1</integer>
    <key>TargetDeviceType</key>
    <integer>1</integer>
</dict>
</plist>';

    return response($mobileconfig, 200)
        ->header('Content-Type', 'application/x-apple-aspen-config')
        ->header('Content-Disposition', 'attachment; filename="wifi-portal.mobileconfig"');
});

Route::get('/auth/google', [AuthController::class, 'googleRedirect'])->name('login.google');
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
Route::get('/auth/callback', [AuthController::class, 'googleCallback']);
Route::get('/callback', [AuthController::class, 'googleCallback']);
Route::post('/auth/wa/request', [AuthController::class, 'waRequest']);
Route::post('/auth/wa/verify', [AuthController::class, 'waVerify']);
Route::post('/auth/room', [AuthController::class, 'room']);

Route::post('/radius/accounting', [RadiusAccountingController::class, 'handle']);

Route::post('/api/fingerprint/analyze', [FingerprintController::class, 'analyze']);
Route::post('/api/fingerprint', function (\Illuminate\Http\Request $request) {
    $response = Http::post(env('FASTAPI_URL').'/api/fingerprint', $request->all());

    return response()->json($response->json());
});

Route::get('/walkthrough', fn () => view('walkthrough.index'))->name('walkthrough');
Route::get('/mikrotik-setup', fn () => view('mikrotik-setup'))->name('mikrotik-setup');

Route::get('/mikrotik/hotspot-files', [MikroTikFileController::class, 'downloadHotspotFiles'])->name('mikrotik.hotspot-files');

Route::get('/impersonate/{userId}', function (int $userId) {
    $user = TenantUser::with('tenant')->findOrFail($userId);

    Auth::guard('tenant_users')->login($user);
    session()->regenerate();

    $slug = $user->tenant?->slug ?? 'unknown';

    return redirect()->to("/dashboard/venue/{$slug}");
})->name('impersonate');

Route::post('/tenant/mikrotik/disconnect', function (\Illuminate\Http\Request $request) {
    $nasId = $request->input('nas_id');
    $username = $request->input('username');

    $router = \App\Models\Router::where('nas_identifier', $nasId)->first();
    if (! $router) {
        return response()->json(['success' => false, 'message' => 'Router not found'], 404);
    }

    try {
        app(\App\Services\MikroTikApiService::class)->disconnectUser($username, $router);
        return response()->json(['success' => true, 'message' => "User $username disconnected"]);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
})->middleware('web');