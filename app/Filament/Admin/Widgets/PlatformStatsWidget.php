<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PlatformStatsWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '60';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $activeTenants = Tenant::where('is_active', true)->count();
        $tenantsThisMonth = Tenant::where('created_at', '>=', now()->startOfMonth())->count();

        try {
            $todayLogins = DB::table('radpostauth')
                ->whereRaw("authdate::timestamp::date = now()::date")
                ->where('reply', 'Access-Accept')
                ->distinct('username')
                ->count('username');
        } catch (\Exception $e) {
            $todayLogins = 0;
        }

        try {
            $yesterdayLogins = DB::table('radpostauth')
                ->whereRaw("authdate::timestamp::date = (now() - interval '1 day')::date")
                ->where('reply', 'Access-Accept')
                ->distinct('username')
                ->count('username');
        } catch (\Exception $e) {
            $yesterdayLogins = 0;
        }

        $loginChange = $yesterdayLogins > 0
            ? round((($todayLogins - $yesterdayLogins) / $yesterdayLogins) * 100)
            : ($todayLogins > 0 ? 100 : 0);

        try {
            $activeSessions = DB::table('radacct')
                ->whereNull('acctstoptime')
                ->count();
        } catch (\Exception $e) {
            $activeSessions = 0;
        }

        try {
            $todayRejects = DB::table('radpostauth')
                ->whereRaw("authdate::timestamp::date = now()::date")
                ->where('reply', 'Access-Reject')
                ->count();
        } catch (\Exception $e) {
            $todayRejects = 0;
        }

        try {
            $todayAccepts = DB::table('radpostauth')
                ->whereRaw("authdate::timestamp::date = now()::date")
                ->where('reply', 'Access-Accept')
                ->count();
        } catch (\Exception $e) {
            $todayAccepts = 0;
        }

        $authRate = ($todayAccepts + $todayRejects) > 0
            ? round(($todayAccepts / ($todayAccepts + $todayRejects)) * 100)
            : 100;

        return [
            Stat::make('Total Tenant Aktif', $activeTenants)
                ->description("+{$tenantsThisMonth} bulan ini")
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary'),

            Stat::make('Login Hari Ini', number_format($todayLogins))
                ->description(($loginChange >= 0 ? '+' : '').$loginChange.'% vs kemarin')
                ->descriptionIcon($loginChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($loginChange >= 0 ? 'success' : 'danger'),

            Stat::make('Sesi Aktif', $activeSessions)
                ->description('pengguna online')
                ->descriptionIcon('heroicon-m-wifi')
                ->color($activeSessions > 0 ? 'success' : 'gray'),

            Stat::make('Auth Rate', $authRate.'%')
                ->description($todayRejects.' reject')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($authRate >= 90 ? 'success' : ($authRate >= 70 ? 'warning' : 'danger')),
        ];
    }
}