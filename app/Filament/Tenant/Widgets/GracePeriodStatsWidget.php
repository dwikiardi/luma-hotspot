<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Router;
use App\Models\UserSession;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class GracePeriodStatsWidget extends ChartWidget
{
    protected static ?string $heading = 'Login per Hari (7 hari)';

    protected function getData(): array
    {
        $tenant = filament()->getTenant();
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return ['datasets' => [], 'labels' => []];
        }

        $routerIds = Router::where('tenant_id', $tenantId)->pluck('id')->toArray();

        $labels = [];
        $successData = [];
        $graceData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d/m');

            if (empty($routerIds)) {
                $successData[] = 0;
                $graceData[] = 0;
                continue;
            }

            $startOfDay = $date->copy()->startOfDay();
            $endOfDay = $date->copy()->endOfDay();

            $successData[] = UserSession::whereIn('router_id', $routerIds)
                ->whereBetween('login_at', [$startOfDay, $endOfDay])
                ->count();

            $graceData[] = UserSession::whereIn('router_id', $routerIds)
                ->whereBetween('login_at', [$startOfDay, $endOfDay])
                ->where('login_method', '!=', 'room')
                ->count();
        }

        return [
            'datasets' => [
                ['label' => 'Berhasil', 'data' => $successData, 'backgroundColor' => '#22c55e'],
                ['label' => 'Otomatis', 'data' => $graceData, 'backgroundColor' => '#6366f1'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getHeight(): string
    {
        return '200px';
    }
}