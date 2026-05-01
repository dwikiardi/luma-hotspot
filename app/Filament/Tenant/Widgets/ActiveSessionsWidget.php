<?php

namespace App\Filament\Tenant\Widgets;

use App\Models\Router;
use App\Models\UserSession;
use Filament\Widgets\Widget;

class ActiveSessionsWidget extends Widget
{
    protected static string $view = 'filament.tenant.widgets.active-sessions';

    protected static ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'sessions' => $this->getActiveSessions(),
            'total' => $this->getTotalActive(),
        ];
    }

    private function getActiveSessions()
    {
        $tenant = filament()->getTenant();
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return [];
        }

        $routerIds = Router::where('tenant_id', $tenantId)->pluck('id')->toArray();
        if (empty($routerIds)) {
            return [];
        }

        return UserSession::whereIn('router_id', $routerIds)
            ->where('status', 'active')
            ->with(['user', 'router'])
            ->orderByDesc('login_at')
            ->limit(20)
            ->get()
            ->map(fn ($s) => [
                'name' => $s->user?->name ?? $s->user?->identity_value ?? '-',
                'identity' => $s->user?->identity_value ?? '-',
                'method' => $s->login_method ?? '-',
                'mac' => $s->mac_address ?? '-',
                'ip' => $s->ip_address ?? '-',
                'router' => $s->router?->name ?? '-',
                'status' => $s->status,
                'login_at' => $s->login_at?->format('d M H:i'),
                'duration' => $s->login_at ? $s->login_at->diffForHumans(now(), true) : '-',
            ]);
    }

    private function getTotalActive(): int
    {
        $tenant = filament()->getTenant();
        $tenantId = $tenant?->id;
        if (! $tenantId) {
            return 0;
        }

        $routerIds = Router::where('tenant_id', $tenantId)->pluck('id')->toArray();
        if (empty($routerIds)) {
            return 0;
        }

        return UserSession::whereIn('router_id', $routerIds)
            ->where('status', 'active')
            ->distinct('user_id')
            ->count('user_id');
    }
}