<?php

namespace App\Filament\Tenant\Widgets;

use App\Services\AnalyticsEngine;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $tenant = filament()->getTenant();
        $analytics = app(AnalyticsEngine::class);
        $data = $analytics->getDashboardSummary($tenant->id, '7days');

        return [
            Stat::make('Pengunjung Unik', $data['visitors']['unique'])
                ->description('vs minggu lalu')
                ->icon('heroicon-o-users')
                ->color('primary'),
            Stat::make('Tamu Kembali', $data['visitors']['returning_rate'].'%')
                ->description($data['visitors']['returning'].' orang kembali')
                ->icon('heroicon-o-arrow-path')
                ->color('success'),
            Stat::make('Reconnect Mulus', $data['grace_period']['seamless_rate'].'%')
                ->description($data['grace_period']['complaints_saved'].' komplain dicegah')
                ->icon('heroicon-o-shield-check')
                ->color('success'),
            Stat::make('Jam Tersibuk', $data['peak_hours']['peak_hour'].':00')
                ->description('Jadwalkan promo di jam ini')
                ->icon('heroicon-o-clock')
                ->color('warning'),
        ];
    }
}
