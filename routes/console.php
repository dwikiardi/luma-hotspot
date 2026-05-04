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
    UserSession::where('status', 'active')
        ->where('expires_at', '<', now())
        ->update(['status' => 'disconnected', 'disconnected_at' => now()]);
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

    foreach ($stopped as $rec) {
        $user = \Illuminate\Support\Facades\DB::table('users')
            ->where('identity_value', $rec->username)
            ->first();
        if (! $user) continue;

        // Dapatkan grace_period dari router yg terkait
        $graceSeconds = 7200; // default 2 jam
        $session = \App\Models\UserSession::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
        if ($session && $session->router) {
            $config = $session->router->tenant->portalConfig ?? null;
            if ($config) {
                $graceSeconds = $config->grace_period_seconds ?? 7200;
            }
        }

        \App\Models\UserSession::where('user_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'disconnected',
                'disconnected_at' => $rec->acctstoptime,
                'expires_at' => \Illuminate\Support\Facades\DB::raw("disconnected_at + interval '{$graceSeconds} seconds'"),
                'mac_address' => $rec->callingstationid ?: \Illuminate\Support\Facades\DB::raw('mac_address'),
                'ip_address' => $rec->framedipaddress 
                    ? preg_replace('/\/\d+$/', '', $rec->framedipaddress) 
                    : \Illuminate\Support\Facades\DB::raw('ip_address'),
            ]);
    }
})->everyMinute();
