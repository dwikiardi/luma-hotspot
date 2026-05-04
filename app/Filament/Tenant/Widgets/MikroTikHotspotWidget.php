<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Router;
use App\Services\MikroTikApiService;
use Filament\Widgets\Widget;

class MikroTikHotspotWidget extends Widget
{
    protected static string $view = 'filament.tenant.widgets.mikrotik-hotspot';

    protected static ?string $pollingInterval = '10s';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'routers' => $this->getRouterStatus(),
            'totalActive' => $this->getTotalActiveOnMikroTik(),
        ];
    }

    private function getRouterStatus(): array
    {
        $tenant = filament()->getTenant();
        if (! $tenant) return [];

        $routers = Router::where('tenant_id', $tenant->id)->get();
        $service = app(MikroTikApiService::class);
        $result = [];

        foreach ($routers as $router) {
            try {
                $users = $service->getActiveUsers($router);
                $result[] = [
                    'name' => $router->name,
                    'nas_id' => $router->nas_identifier,
                    'ip' => $router->nas_ip ?? $router->hotspot_address ?? '-',
                    'online' => count($users),
                    'users' => $users,
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                $result[] = [
                    'name' => $router->name,
                    'nas_id' => $router->nas_identifier,
                    'ip' => $router->nas_ip ?? $router->hotspot_address ?? '-',
                    'online' => -1,
                    'users' => [],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    private function getTotalActiveOnMikroTik(): int
    {
        $routers = $this->getRouterStatus();
        $total = 0;
        foreach ($routers as $r) {
            if ($r['online'] > 0) $total += $r['online'];
        }
        return $total;
    }
}