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

    // Build portal URL with all params + browser=1 flag
    $portalParams = http_build_query(array_filter([
        'nas_id' => $nasId,
        'client_mac' => $mac,
        'link_login' => $linkLogin,
        'dst' => $dst,
        'browser' => '1',
    ]));
    $portalUrl = url('/portal?' . $portalParams);

    // Generate Web Clip .mobileconfig that opens Safari
    $uuid = str_replace('-', '', \Illuminate\Support\Str::uuid());
    $mobileconfig = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>PayloadContent</key>
    <array>
        <dict>
            <key>FullScreen</key>
            <false/>
            <key>Icon</key>
            <data>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</data>
            <key>IsRemovable</key>
            <true/>
            <key>Label</key>
            <string>WiFi Portal</string>
            <key>PayloadIdentifier</key>
            <string>id.lumanetwork.portal.{$uuid}</string>
            <key>PayloadType</key>
            <string>com.apple.webClip.managed</string>
            <key>PayloadUUID</key>
            <string>{$uuid}</string>
            <key>PayloadVersion</key>
            <integer>1</integer>
            <key>URL</key>
            <string>' . htmlspecialchars($portalUrl) . '</string>
        </dict>
    </array>
    <key>PayloadDisplayName</key>
    <string>Luma WiFi Portal</string>
    <key>PayloadIdentifier</key>
    <string>id.lumanetwork.portal</string>
    <key>PayloadType</key>
    <string>Configuration</string>
    <key>PayloadUUID</key>
    <string>' . \Illuminate\Support\Str::uuid() . '</string>
    <key>PayloadVersion</key>
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