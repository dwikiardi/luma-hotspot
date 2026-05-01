<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Widgets\ActiveSessionsWidget;
use App\Filament\Tenant\Widgets\GracePeriodStatsWidget;
use App\Filament\Tenant\Widgets\LoginMethodChartWidget;
use App\Filament\Tenant\Widgets\PeakHourChartWidget;
use App\Filament\Tenant\Widgets\StatsOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class AnalyticsDashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationGroup = 'Overview';

    protected static ?int $navigationSort = 1;

    public function getWidgets(): array
    {
        return [
            ActiveSessionsWidget::class,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            PeakHourChartWidget::class,
            LoginMethodChartWidget::class,
            GracePeriodStatsWidget::class,
        ];
    }
}