<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Router;
use App\Models\UserSession;
use Filament\Widgets\ChartWidget;

class PeakHourChartWidget extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    protected function getData(): array
    {
        $tenant = filament()->getTenant();
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return ['datasets' => [['data' => array_fill(0, 24, 0)]], 'labels' => array_map(fn ($h) => $h.':00', range(0, 23))];
        }

        $routerIds = Router::where('tenant_id', $tenantId)->pluck('id')->toArray();

        $hourlyData = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyData[$h] = 0;
        }

        if (! empty($routerIds)) {
            $rows = UserSession::whereIn('router_id', $routerIds)
                ->where('login_at', '>=', now()->subDays(7))
                ->selectRaw('EXTRACT(HOUR FROM login_at) as hour, COUNT(*) as cnt')
                ->groupBy('hour')
                ->pluck('cnt', 'hour')
                ->toArray();

            foreach ($rows as $hour => $cnt) {
                $hourlyData[(int) $hour] = $cnt;
            }
        }

        $peakHour = array_keys($hourlyData, max($hourlyData))[0];

        $colors = [];
        for ($h = 0; $h < 24; $h++) {
            $colors[] = $h === $peakHour ? '#6366f1' : '#e2e8f0';
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pengunjung',
                    'data' => array_values($hourlyData),
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