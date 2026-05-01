<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\ActiveSessionsWidget;
use App\Filament\Admin\Widgets\AlertsWidget;
use App\Filament\Admin\Widgets\FingerprintScoreWidget;
use App\Filament\Admin\Widgets\GracePeriodLogWidget;
use App\Filament\Admin\Widgets\PlatformStatsWidget;
use App\Filament\Admin\Widgets\RadiusAccountingWidget;
use App\Filament\Admin\Widgets\RadiusAuthWidget;
use App\Filament\Admin\Widgets\RealtimeVisitorWidget;
use App\Filament\Admin\Widgets\ROIAggregateWidget;
use App\Filament\Admin\Widgets\TenantGrowthChartWidget;
use App\Filament\Admin\Widgets\TopVenuesWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationGroup = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected static string $routePath = '/';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        return [
            PlatformStatsWidget::class,
            ActiveSessionsWidget::class,
            RadiusAuthWidget::class,
            RadiusAccountingWidget::class,
            GracePeriodLogWidget::class,
            FingerprintScoreWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
            'xl' => 3,
        ];
    }
}