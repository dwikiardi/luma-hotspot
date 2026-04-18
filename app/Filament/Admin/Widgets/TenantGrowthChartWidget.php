<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

class TenantGrowthChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Pertumbuhan Tenant';

    protected int|string|array $columnSpan = 6;

    protected function getHeight(): string
    {
        return '300px';
    }

    protected function getData(): array
    {
        $months = [];
        $newTenants = [];
        $cumulativeTenants = [];
        $cumulative = 0;

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M Y');

            $count = Tenant::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            $newTenants[] = $count;

            $cumulative += $count;
            $cumulativeTenants[] = $cumulative;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Tenant Baru',
                    'data' => $newTenants,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Total Kumulatif',
                    'data' => $cumulativeTenants,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'transparent',
                    'fill' => false,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'interaction' => [
                'mode' => 'index',
            ],
        ];
    }
}
