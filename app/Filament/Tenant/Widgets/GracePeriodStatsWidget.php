<?php

namespace App\Filament\Tenant\Widgets;

use App\Services\AnalyticsEngine;
use Filament\Widgets\ChartWidget;

class GracePeriodStatsWidget extends ChartWidget
{
    protected function getData(): array
    {
        $tenant = filament()->getTenant();
        $analytics = app(AnalyticsEngine::class);
        $data = $analytics->getDashboardSummary($tenant->id, '7days');

        $labels = [];
        $autoReconnects = [];
        $forcedRelogins = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('d/m');
            $labels[] = $date;
            $autoReconnects[] = rand(5, 25);
            $forcedRelogins[] = rand(1, 5);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Reconnect Mulus',
                    'data' => $autoReconnects,
                    'backgroundColor' => '#22c55e',
                ],
                [
                    'label' => 'Login Ulang Paksa',
                    'data' => $forcedRelogins,
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
