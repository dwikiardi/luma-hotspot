<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Router;
use App\Models\UserSession;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverviewWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $tenant = filament()->getTenant();
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return [
                Stat::make('Online', 0)->icon('heroicon-o-users')->color('gray'),
                Stat::make('Sesi Aktif', 0)->icon('heroicon-o-wifi')->color('gray'),
                Stat::make('Login 7hr', 0)->icon('heroicon-o-check')->color('gray'),
                Stat::make('Jam Sibuk', '-')->icon('heroicon-o-clock')->color('gray'),
            ];
        }

        $routerIds = Router::where('tenant_id', $tenantId)->pluck('id')->toArray();

        if (empty($routerIds)) {
            return [
                Stat::make('Online', 0)->description('Belum ada router')->icon('heroicon-o-users')->color('gray'),
                Stat::make('Sesi Aktif', 0)->description('-')->icon('heroicon-o-wifi')->color('gray'),
                Stat::make('Login 7hr', 0)->description('-')->icon('heroicon-o-check')->color('gray'),
                Stat::make('Jam Sibuk', '-')->description('-')->icon('heroicon-o-clock')->color('gray'),
            ];
        }

        $uniqueOnline = UserSession::whereIn('router_id', $routerIds)
            ->where('status', 'active')
            ->distinct('user_id')
            ->count('user_id');

        $activeSessions = UserSession::whereIn('router_id', $routerIds)
            ->where('status', 'active')
            ->count();

        $sevenDaysAgo = now()->subDays(7);
        $loginCount = UserSession::whereIn('router_id', $routerIds)
            ->where('login_at', '>=', $sevenDaysAgo)
            ->count();

        $peakHour = UserSession::whereIn('router_id', $routerIds)
            ->where('login_at', '>=', $sevenDaysAgo)
            ->selectRaw('EXTRACT(HOUR FROM login_at) as hour, COUNT(*) as cnt')
            ->groupBy('hour')
            ->orderByDesc('cnt')
            ->first();

        $graceCount = UserSession::whereIn('router_id', $routerIds)
            ->where('status', 'disconnected')
            ->where('expires_at', '>', now())
            ->count();

        return [
            Stat::make('Online', $uniqueOnline)
                ->description($activeSessions.' sesi aktif')
                ->icon('heroicon-o-users')
                ->color($uniqueOnline > 0 ? 'primary' : 'gray'),
            Stat::make('Sesi Aktif', $activeSessions)
                ->description($graceCount.' dalam grace period')
                ->icon('heroicon-o-wifi')
                ->color($activeSessions > 0 ? 'success' : 'gray'),
            Stat::make('Login 7hr', $loginCount)
                ->description('7 hari terakhir')
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Jam Sibuk', ($peakHour?->hour ?? '-').':00')
                ->description('jam terbanyak pengunjung')
                ->icon('heroicon-o-clock')
                ->color('warning'),
        ];
    }
}