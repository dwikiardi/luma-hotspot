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

// iOS CNA Escape: serve .mobileconfig Web Clip untuk keluar dari CNA ke Safari
Route::get('/cna-escape', function (\Illuminate\Http\Request $request) {
    $nasId = $request->query('nas_id', '');
    $mac = $request->query('client_mac', '');
    $linkLogin = $request->query('link_login', '');
    $dst = $request->query('dst', '');

    $portalParams = http_build_query(array_filter([
        'nas_id' => $nasId,
        'client_mac' => $mac,
        'link_login' => $linkLogin,
        'dst' => $dst,
        'browser' => '1',
    ]));
    $portalUrl = url('/portal?' . $portalParams);

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
            <key>URL</key>
            <string>' . htmlspecialchars($portalUrl) . '</string>
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