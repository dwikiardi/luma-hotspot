<?php

namespace App\Filament\Admin\Widgets;

use App\Models\AnalyticsEvent;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '60';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $activeTenants = Tenant::where('is_active', true)->count();
        $tenantsThisMonth = Tenant::where('created_at', '>=', now()->startOfMonth())->count();

        $todayVisitors = AnalyticsEvent::whereDate('occurred_at', today())
            ->where('event_type', 'login_success')
            ->distinct('user_id')
            ->count('user_id');

        $yesterdayVisitors = AnalyticsEvent::whereDate('occurred_at', today()->subDay())
            ->where('event_type', 'login_success')
            ->distinct('user_id')
            ->count('user_id');

        $visitorChange = $yesterdayVisitors > 0
            ? round((($todayVisitors - $yesterdayVisitors) / $yesterdayVisitors) * 100)
            : 0;

        $autoReconnects = AnalyticsEvent::whereDate('occurred_at', today())
            ->where('event_type', 'auto_reconnect')->count();
        $forcedRelogins = AnalyticsEvent::whereDate('occurred_at', today())
            ->where('event_type', 'forced_relogin')->count();
        $totalAttempts = $autoReconnects + $forcedRelogins;
        $seamlessRate = $totalAttempts > 0
            ? round(($autoReconnects / $totalAttempts) * 100, 1)
            : 100;

        $monthlyEvents = AnalyticsEvent::where('occurred_at', '>=', now()->subDays(30))
            ->where('event_type', 'login_success')
            ->count();
        $estimatedRoi = $monthlyEvents * 12500;

        return [
            Stat::make('Total Tenant Aktif', $activeTenants)
                ->description("{$tenantsThisMonth} tenant baru bulan ini")
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary')
                ->chart($this->getTenantSparkline()),

            Stat::make('Visitor Hari Ini', number_format($todayVisitors))
                ->description(($visitorChange >= 0 ? '+' : '')."{$visitorChange}% vs kemarin")
                ->descriptionIcon('heroicon-m-users')
                ->color($visitorChange >= 0 ? 'success' : 'danger')
                ->chart($this->getVisitorSparkline()),

            Stat::make('ROI Platform (30 hari)', 'Rp '.$this->formatRupiah($estimatedRoi))
                ->description('nilai bisnis tergenerasi')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('warning'),

            Stat::make('Seamless Rate', $seamlessRate.'%')
                ->description('tamu tidak perlu login ulang')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($seamlessRate >= 90 ? 'success' : ($seamlessRate >= 70 ? 'warning' : 'danger'))
                ->chart($this->getSeamlessSparkline()),
        ];
    }

    private function getTenantSparkline(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $data[] = Tenant::whereDate('created_at', today()->subDays($i))->count();
        }

        return $data;
    }

    private function getVisitorSparkline(): array
    {
        $data = [];
        for ($h = 0; $h < 24; $h++) {
            $data[] = AnalyticsEvent::whereDate('occurred_at', today())
                ->whereRaw('EXTRACT(HOUR FROM occurred_at) = ?', [$h])
                ->where('event_type', 'login_success')
                ->count();
        }

        return $data;
    }

    private function getSeamlessSparkline(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $auto = AnalyticsEvent::whereDate('occurred_at', $date)
                ->where('event_type', 'auto_reconnect')->count();
            $forced = AnalyticsEvent::whereDate('occurred_at', $date)
                ->where('event_type', 'forced_relogin')->count();
            $total = $auto + $forced;
            $data[] = $total > 0 ? round(($auto / $total) * 100) : 100;
        }

        return $data;
    }

    private function formatRupiah(int $amount): string
    {
        if ($amount >= 1000000000) {
            return number_format($amount / 1000000000, 1).' M';
        }
        if ($amount >= 1000000) {
            return number_format($amount / 1000000, 1).' Jt';
        }
        if ($amount >= 1000) {
            return number_format($amount / 1000, 0).' Rb';
        }

        return number_format($amount);
    }
}
