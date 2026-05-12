<?php

use App\Http\Controllers\Api\DashboardController;
use App\Models\PendingConnection;
use App\Models\Router;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')->group(function () {
    Route::get('{tenantId}/summary', [DashboardController::class, 'summary']);
    Route::get('{tenantId}/complaints', [DashboardController::class, 'complaints']);
    Route::get('{tenantId}/roi', [DashboardController::class, 'roi']);
    Route::get('{tenantId}/visitors', [DashboardController::class, 'visitors']);
});

// DHCP lease hook — MikroTik notifikasi device baru connect
Route::post('/dhcp-hook', function (Request $request) {
    $mac = $request->input('mac', 'unknown');
    $ip = $request->input('ip', '0.0.0.0');
    $hostname = $request->input('host', '');
    $dhcpServer = $request->input('server', '');
    $vendorClassId = $request->input('vendor_class_id');
    $parameterRequestList = $request->input('parameter_request_list');
    $clientId = $request->input('client_id');

    // Cari router dari session by MAC, fallback ke default
    $router = null;
    if ($mac && $mac !== 'unknown') {
        $router = \App\Models\UserSession::where('mac_address', $mac)
            ->where('status', 'active')
            ->first()?->router;
    }
    if (! $router) {
        $router = Router::where('nas_identifier', 'eden-canggu')->first();
    }

    if ($router) {
        PendingConnection::create([
            'mac_address' => $mac,
            'ip_address' => $ip,
            'hostname' => $hostname,
            'router_id' => $router->id,
            'dhcp_server' => $dhcpServer,
            'created_at' => now(),
        ]);

        \App\Services\ActivityLogger::log('dhcp_hook', 'lease', "DHCP lease: MAC={$mac} IP={$ip} host={$hostname}", [
            'mac' => $mac, 'ip' => $ip, 'hostname' => $hostname,
        ]);

        // Device DNA: record DHCP fingerprint untuk cross-MAC identification
        try {
            app(\App\Services\DeviceDnaService::class)->recordFingerprint(
                $mac, $ip, $hostname, $vendorClassId, $parameterRequestList, $clientId, $router
            );
        } catch (\Throwable $e) {
            Log::warning('DeviceDNA record failed', ['error' => $e->getMessage()]);
        }

        // Cleanup old entries (> 5 minutes)
        PendingConnection::where('router_id', $router->id)
            ->where('created_at', '<', now()->subMinutes(5))
            ->delete();
    }

    return response()->json(['ok' => true]);
})->withoutMiddleware(['web', 'auth']);
