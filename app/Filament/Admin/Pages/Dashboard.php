<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\AlertsWidget;
use App\Filament\Admin\Widgets\PlatformStatsWidget;
use App\Filament\Admin\Widgets\RealtimeVisitorWidget;
use App\Filament\Admin\Widgets\ROIAggregateWidget;
use App\Filament\Admin\Widgets\TenantGrowthChartWidget;
use App\Filament\Admin\Widgets\TopVenuesWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Luma Network — Overview';

    protected static ?string $navigationGroup = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected static string $routePath = '/';

    protected function getHeaderWidgets(): array
    {
        return [
            PlatformStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            TenantGrowthChartWidget::class,
            RealtimeVisitorWidget::class,
            ROIAggregateWidget::class,
            AlertsWidget::class,
            TopVenuesWidget::class,
        ];
    }
}
