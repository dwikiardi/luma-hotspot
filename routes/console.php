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

// Sync radacct to user_sessions (fallback if rest module fails)
Schedule::call(function () {
    $records = \Illuminate\Support\Facades\DB::table('radacct')
        ->whereNotNull('acctstoptime')
        ->whereNull('acctsessiontime')
        ->get();

    foreach ($records as $rec) {
        // Find user by username
        $userId = \Illuminate\Support\Facades\DB::table('users')
            ->where('identity_value', $rec->username)
            ->value('id');

        if (! $userId) {
            continue;
        }

        // Update user session if exists and is active/disconnected
        $updated = \App\Models\UserSession::where('user_id', $userId)
            ->whereIn('status', ['active', 'disconnected'])
            ->update([
                'status' => 'disconnected',
                'disconnected_at' => $rec->acctstoptime,
                'mac_address' => $rec->callingstationid ?: \Illuminate\Support\Facades\DB::raw('mac_address'),
                'ip_address' => $rec->framedipaddress ? preg_replace('/\/\d+$/', '', $rec->framedipaddress) : \Illuminate\Support\Facades\DB::raw('ip_address'),
            ]);

        // Mark as processed
        if ($updated) {
            \Illuminate\Support\Facades\DB::table('radacct')
                ->where('radacctid', $rec->radacctid)
                ->update(['acctsessiontime' => 1]);
        }
    }
})->everyFiveMinutes();
