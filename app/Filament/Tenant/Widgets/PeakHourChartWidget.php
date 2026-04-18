<?php

namespace App\Filament\Tenant\Widgets;

use App\Services\AnalyticsEngine;
use Filament\Widgets\ChartWidget;

class PeakHourChartWidget extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    protected function getData(): array
    {
        $tenant = filament()->getTenant();
        $analytics = app(AnalyticsEngine::class);
        $data = $analytics->getDashboardSummary($tenant->id, '7days');

        $distribution = $data['peak_hours']['hourly_distribution'] ?? [];
        $peakHour = $data['peak_hours']['peak_hour'] ?? 12;

        $colors = [];
        for ($h = 0; $h < 24; $h++) {
            $colors[] = $h === $peakHour ? '#6366f1' : '#e2e8f0';
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pengunjung',
                    'data' => array_values($distribution),
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => array_map(fn ($h) => $h.':00', range(0, 23)),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
