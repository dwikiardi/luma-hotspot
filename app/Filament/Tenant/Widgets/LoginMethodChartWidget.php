<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Router;
use App\Models\UserSession;
use Filament\Widgets\ChartWidget;

class LoginMethodChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Metode Login';

    protected function getData(): array
    {
        $tenant = filament()->getTenant();
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return ['datasets' => [['data' => [0, 0, 0, 0]]], 'labels' => ['Google', 'WhatsApp', 'Nomor Kamar', 'Email']];
        }

        $routerIds = Router::where('tenant_id', $tenantId)->pluck('id')->toArray();

        if (empty($routerIds)) {
            return ['datasets' => [['data' => [0, 0, 0, 0]]], 'labels' => ['Google', 'WhatsApp', 'Nomor Kamar', 'Email']];
        }

        $methods = UserSession::whereIn('router_id', $routerIds)
            ->selectRaw("login_method, COUNT(*) as cnt")
            ->groupBy('login_method')
            ->pluck('cnt', 'login_method')
            ->toArray();

        return [
            'datasets' => [
                [
                    'data' => [
                        $methods['google'] ?? 0,
                        $methods['wa'] ?? 0,
                        $methods['room'] ?? 0,
                        $methods['email'] ?? 0,
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