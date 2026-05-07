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

        // Disconnect semua session active > 60s untuk user ini,
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

            // Reactivate disconnected session jika user masih di MikroTik
            $disconnected = \App\Models\UserSession::where('user_id', $user->id)
                ->where('router_id', $router->id)
                ->where('status', 'disconnected')
                ->where('expires_at', '>', now())
                ->orderByDesc('login_at')
                ->first();

            if ($disconnected) {
                $disconnected->update([
                    'status' => 'active',
                    'login_at' => now(),
                    'last_seen_at' => now(),
                    'expires_at' => now()->addHours(4),
                    'disconnected_at' => null,
                    'ip_address' => $ip,
                    'mac_address' => $mac !== 'unknown' ? $mac : $disconnected->mac_address,
                ]);
                \App\Services\ActivityLogger::syncReactivate($identity, $disconnected->id, $mac);
                continue;
            }

            // Update or skip active session
            $active = \App\Models\UserSession::where('user_id', $user->id)
                ->where('router_id', $router->id)
                ->where('status', 'active')
                ->latest('login_at')
                ->first();

            if ($active) {
                if ($mac !== 'unknown' && $active->mac_address !== $mac) {
                    $oldMac = $active->mac_address;
                    $active->update(['mac_address' => $mac, 'ip_address' => $ip, 'last_seen_at' => now()]);
                    \App\Services\ActivityLogger::syncMacUpdated($identity, $active->id, $oldMac, $mac);
                } else {
                    $active->update(['ip_address' => $ip, 'last_seen_at' => now()]);
                }
                // Expire any duplicate active sessions (safety)
                \App\Models\UserSession::where('user_id', $user->id)
                    ->where('router_id', $router->id)
                    ->where('status', 'active')
                    ->where('id', '!=', $active->id)
                    ->update(['status' => 'expired']);
                continue;
            }

            $device = \App\Models\Device::firstOrCreate(
                ['fingerprint_hash' => 'fp-mk-'.substr(md5($mac), 0, 12)],
                ['user_id' => $user->id]
            );

            \App\Models\UserSession::create([
                'user_id' => $user->id,
                'device_id' => $device->id,
                'router_id' => $router->id,
                'mac_address' => $mac,
                'ip_address' => $ip,
                'login_at' => now(),
                'last_seen_at' => now(),
                'expires_at' => now()->addHours(4),
                'status' => 'active',
                'nas_id' => $router->nas_identifier,
                'login_method' => 'room',
                'cookie_token' => \App\Models\UserSession::generateCookieToken(),
                'fingerprint_hash' => 'fp-mk-'.substr(md5($mac), 0, 12),
            ]);
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('MikroTik sync failed', ['error' => $e->getMessage()]);
    }
})->everyMinute();
