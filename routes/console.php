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
    // Dapatkan grace period (default 48 jam)
    $grace = 172800;
    try { 
        $r = \App\Models\Router::first(); 
        if ($r?->tenant?->portalConfig?->grace_period_seconds > 0) {
            $grace = $r->tenant->portalConfig->grace_period_seconds;
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

    // Dapatkan grace period dari portal config (default 48 jam)
    $graceSeconds = 172800; // default 2 hari
    try {
        $firstRouter = \App\Models\Router::first();
        if ($firstRouter && $firstRouter->tenant) {
            $config = $firstRouter->tenant->portalConfig ?? null;
            if ($config && $config->grace_period_seconds > 0) {
                $graceSeconds = $config->grace_period_seconds;
            }
        }
    } catch (\Throwable) {}

    foreach ($stopped as $rec) {
        $user = \Illuminate\Support\Facades\DB::table('users')
            ->where('identity_value', $rec->username)
            ->first();
        if (! $user) continue;

        \App\Models\UserSession::where('user_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'disconnected',
                'disconnected_at' => $rec->acctstoptime,
                'expires_at' => \Illuminate\Support\Facades\DB::raw("now() + interval '{$graceSeconds} seconds'"),
                'mac_address' => $rec->callingstationid ?: \Illuminate\Support\Facades\DB::raw('mac_address'),
                'ip_address' => $rec->framedipaddress 
                    ? preg_replace('/\/\d+$/', '', $rec->framedipaddress) 
                    : \Illuminate\Support\Facades\DB::raw('ip_address'),
            ]);
    }
})->everyMinute();

// Sync MikroTik active → create missing DB sessions
Schedule::call(function () {
    try {
        $service = app(\App\Services\MikroTikApiService::class);
        $routers = \App\Models\Router::where('is_active', true)->get();

        foreach ($routers as $router) {
            $users = $service->getActiveUsers($router);
            foreach ($users as $u) {
                $identity = $u['user'];
                $ip = $u['address'] ?? '0.0.0.0';

                $user = \App\Models\User::where('identity_value', $identity)->first();
                if (! $user) continue;

                // Skip if already active in DB
                $exists = \App\Models\UserSession::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->exists();
                if ($exists) continue;

                // Get MAC from radacct
                $rad = \Illuminate\Support\Facades\DB::table('radacct')
                    ->where('username', $identity)
                    ->orderByDesc('radacctid')
                    ->first();
                $mac = $rad?->callingstationid ?? 'unknown';

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
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('MikroTik sync failed', ['error' => $e->getMessage()]);
    }
})->everyMinute();
