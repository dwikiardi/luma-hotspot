<?php

namespace App\Filament\Tenant\Widgets;

use App\Services\AnalyticsEngine;
use Filament\Widgets\ChartWidget;

class LoginMethodChartWidget extends ChartWidget
{
    protected function getData(): array
    {
        $tenant = filament()->getTenant();
        $analytics = app(AnalyticsEngine::class);
        $data = $analytics->getDashboardSummary($tenant->id, '7days');

        return [
            'datasets' => [
                [
                    'data' => [
                        $data['login_methods']['google'] ?? 0,
                        $data['login_methods']['wa'] ?? 0,
                        $data['login_methods']['room'] ?? 0,
                        $data['login_methods']['email'] ?? 0,
                    ],
                    'backgroundColor' => ['#4285F4', '#25D366', '#6366f1', '#f59e0b'],
                ],
            ],
            'labels' => ['Google', 'WhatsApp', 'Nomor Kamar', 'Email'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
