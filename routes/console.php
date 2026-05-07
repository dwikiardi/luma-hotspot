<?php

use App\Models\Tenant;
use App\Models\UserSession;
use App\Services\AnalyticsEngine;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    Tenant::with('routers')->get()->each(function ($tenant) {
        $tenant->routers->each(function ($router) use ($tenant) {
            app(AnalyticsEngine::class)->aggregateDaily(
                $tenant->id,
                $router->id,
                now()->subDay()->toDateString()
            );
        });
    });
})->dailyAt('00:05');

Schedule::call(function () {
    UserSession::where('status', 'disconnected')
        ->where('expires_at', '<', now())
        ->update(['status' => 'expired']);
})->everyFiveMinutes();

Schedule::call(function () {
    UserSession::where('status', 'active')
        ->where('last_seen_at', '<', now()->subHours(24))
        ->update(['status' => 'expired']);
})->hourly();

Schedule::call(function () {
    // Dapatkan grace period dari portal config tenant active (default 48 jam)
    $grace = 172800;
    try {
        $config = \App\Models\PortalConfig::whereHas('tenant', function ($q) {
            $q->whereHas('routers');
        })->where('grace_period_seconds', '>', 0)->first();
        if ($config) {
            $grace = $config->grace_period_seconds;
        }
    } catch (\Throwable) {}

    UserSession::where('status', 'active')
        ->where('expires_at', '<', now())
        ->update([
            'status' => 'disconnected', 
            'disconnected_at' => \Illuminate\Support\Facades\DB::raw('expires_at'),
            'expires_at' => \Illuminate\Support\Facades\DB::raw("expires_at + interval '{$grace} seconds'"),
        ]);
})->everyMinute();

// Sync radacct to user_sessions: update MAC/IP untuk session yang match MAC
Schedule::call(function () {
    $records = \Illuminate\Support\Facades\DB::table('radacct')
        ->whereNotNull('callingstationid')
        ->where('callingstationid', '!=', '')
        ->whereNotNull('framedipaddress')
        ->where('framedipaddress', '!=', '')
        ->orderByDesc('radacctid')
        ->limit(50)
        ->get();

    foreach ($records as $rec) {
        $ip = preg_replace('/\/\d+$/', '', $rec->framedipaddress);

        // Update session yang match dengan MAC ini
        \App\Models\UserSession::where('mac_address', $rec->callingstationid)
            ->whereIn('status', ['active', 'disconnected'])
            ->update([
                'mac_address' => $rec->callingstationid,
                'ip_address' => $ip,
            ]);
    }
})->everyMinute();

// Hapus duplikat session: hanya keep 1 disconnected + 1 active per user per router
Schedule::call(function () {
    $users = \Illuminate\Support\Facades\DB::table('user_sessions')
        ->select(\Illuminate\Support\Facades\DB::raw('DISTINCT user_id, router_id'))
        ->where('status', 'disconnected')
        ->get();
    
    foreach ($users as $u) {
        $latest = \App\Models\UserSession::where('user_id', $u->user_id)
            ->where('router_id', $u->router_id)
            ->where('status', 'disconnected')
            ->orderByDesc('login_at')
            ->first();
        
        if ($latest) {
            \App\Models\UserSession::where('user_id', $u->user_id)
                ->where('router_id', $u->router_id)
                ->where('status', 'disconnected')
                ->where('id', '!=', $latest->id)
                ->update(['status' => 'expired']);
        }
    }
})->everyFiveMinutes();

// Deteksi disconnect dari radacct → update user_sessions ke disconnected
Schedule::call(function () {
    $stopped = \Illuminate\Support\Facades\DB::table('radacct')
        ->whereNotNull('acctstoptime')
        ->where('acctstoptime', '>=', now()->subMinutes(10))
        ->orderByDesc('radacctid')
        ->get();

    if ($stopped->isNotEmpty()) {
        \App\Services\ActivityLogger::log('scheduler', 'disconnect_check', "Checking {$stopped->count()} stopped radacct records");
    }

    foreach ($stopped as $rec) {
        $user = \Illuminate\Support\Facades\DB::table('users')
            ->where('identity_value', $rec->username)
            ->first();
        if (! $user) continue;

        // Dapatkan grace period dari session's router's tenant
        $session = \App\Models\UserSession::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('login_at', '<', now()->subSeconds(60))
            ->orderByDesc('login_at')
            ->first();
        if (! $session) continue;

        $graceSeconds = 172800; // default 48h
        if ($session->router && $session->router->tenant) {
            $config = \App\Models\PortalConfig::where('tenant_id', $session->router->tenant_id)
                ->where('grace_period_seconds', '>', 0)
                ->first();
            if ($config) $graceSeconds = $config->grace_period_seconds;
        }

        // Disconnect session active > 60s untuk user ini,
        // hanya yg login sebelum disconnect (login_at < acctstoptime)
        $expiresAt = \Carbon\Carbon::parse($rec->acctstoptime)->addSeconds($graceSeconds);
        $updated = \App\Models\UserSession::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('login_at', '<', now()->subSeconds(60))
            ->where('login_at', '<', $rec->acctstoptime)
            ->update([
                'status' => 'disconnected',
                'disconnected_at' => $rec->acctstoptime,
                'expires_at' => $expiresAt,
                'mac_address' => $rec->callingstationid ?: \Illuminate\Support\Facades\DB::raw('mac_address'),
                'ip_address' => $rec->framedipaddress 
                    ? preg_replace('/\/\d+$/', '', $rec->framedipaddress) 
                    : \Illuminate\Support\Facades\DB::raw('ip_address'),
            ]);
        if ($updated > 0) {
            \App\Services\ActivityLogger::disconnectSession(
                $rec->username, $user->id, $expiresAt->toDateTimeString()
            );
            \App\Services\ActivityLogger::disconnectDetected(
                $rec->username,
                $rec->callingstationid ?: '?',
                $rec->framedipaddress ?: '?',
                $rec->acctterminatecause ?: 'unknown'
            );
        }
    }
})->everyMinute();

// Cleanup pending_connections > 2 minutes old
Schedule::call(function () {
    \App\Models\PendingConnection::where('created_at', '<', now()->subMinutes(2))->delete();
})->everyMinute();

// Sync MikroTik active → create missing DB sessions
Schedule::call(function () {
    try {
        $service = app(\App\Services\MikroTikApiService::class);
        
        // Sync per router — hanya yg punya nas_identifier yg match dgn MikroTik
        $routers = \App\Models\Router::where('is_active', true)->get();
        $users = $service->getActiveUsers($routers->first());

        if (empty($users)) return;

        \App\Services\ActivityLogger::syncActiveUsers(count($users), array_column($users, 'user'));

        // Ambil router pertama yg ada nas_identifier-nya (yg benar terhubung ke MikroTik)
        $router = $routers->firstWhere('nas_identifier', 'eden-canggu') 
               ?? $routers->firstWhere('hotspot_address', '!=', null)
               ?? $routers->first();

        foreach ($users as $u) {
            $identity = $u['user'];
            $ip = $u['address'] ?? '0.0.0.0';

            $user = \App\Models\User::where('identity_value', $identity)->first();
            if (! $user) continue;

            // Get MAC from radacct
            $rad = \Illuminate\Support\Facades\DB::table('radacct')
                ->where('username', $identity)
                ->orderByDesc('radacctid')
                ->first();
            $mac = $rad?->callingstationid ?? 'unknown';

            // Find existing session for this user+router (active or disconnected)
            $session = \App\Models\UserSession::where('user_id', $user->id)
                ->where('router_id', $router->id)
                ->whereIn('status', ['active', 'disconnected'])
                ->first();

            if ($session) {
                // Reactivate if disconnected, otherwise just update
                $updates = [
                    'last_seen_at' => now(),
                    'ip_address' => $ip,
                ];
                if ($session->status === 'disconnected') {
                    $updates['status'] = 'active';
                    $updates['login_at'] = now();
                    $updates['expires_at'] = now()->addHours(4);
                    $updates['disconnected_at'] = null;
                }
                if ($mac !== 'unknown' && $session->mac_address !== $mac) {
                    $oldMac = $session->mac_address;
                    $updates['mac_address'] = $mac;
                    \App\Services\ActivityLogger::syncMacUpdated($identity, $session->id, $oldMac, $mac);
                }
                $session->update($updates);
                \App\Services\ActivityLogger::syncReactivate($identity, $session->id, $mac);
            }
            // No else: never create new session from sync
            // Session creation only happens via user login (createSession)
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('MikroTik sync failed', ['error' => $e->getMessage()]);
    }
})->everyMinute();
