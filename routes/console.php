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

// Sync radacct to user_sessions: update MAC/IP untuk semua session aktif/baru
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
        $userId = \Illuminate\Support\Facades\DB::table('users')
            ->where('identity_value', $rec->username)
            ->value('id');

        if (! $userId) {
            continue;
        }

        $ip = preg_replace('/\/\d+$/', '', $rec->framedipaddress);

        \App\Models\UserSession::where('user_id', $userId)
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
