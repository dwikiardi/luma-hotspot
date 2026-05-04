<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Router;
use App\Models\UserSession;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ROIReport extends Page
{
    protected static string $view = 'filament.tenant.pages.roi-report';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $navigationGroup = 'Overview';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Laporan';

    protected static ?string $pollingInterval = '30s';

    public function getViewData(): array
    {
        $tenant = filament()->getTenant();
        $tenantId = $tenant?->id;

        if (! $tenantId) {
            return $this->emptyData();
        }

        $routerIds = Router::where('tenant_id', $tenantId)->pluck('id')->toArray();
        if (empty($routerIds)) {
            return $this->emptyData();
        }

        return array_merge(
            $this->getStats($routerIds),
            $this->getRepeatLoginStats($routerIds),
            $this->getAutoReconnectStats($routerIds),
            $this->getIdentityStats($routerIds),
        );
    }

    private function getStats(array $routerIds): array
    {
        $sevenDaysAgo = now()->subDays(7);
        $thirtyDaysAgo = now()->subDays(30);

        return [
            'totalUsers' => UserSession::whereIn('router_id', $routerIds)->distinct('user_id')->count('user_id'),
            'uniqueUsers7d' => UserSession::whereIn('router_id', $routerIds)->where('login_at', '>=', $sevenDaysAgo)->distinct('user_id')->count('user_id'),
            'totalSessions' => UserSession::whereIn('router_id', $routerIds)->count(),
            'sessionsToday' => UserSession::whereIn('router_id', $routerIds)->where('login_at', '>=', today())->count(),
            'activeNow' => UserSession::whereIn('router_id', $routerIds)->where('status', 'active')->count(),
            'inGrace' => UserSession::whereIn('router_id', $routerIds)->where('status', 'disconnected')->where('expires_at', '>', now())->count(),
        ];
    }

    private function getRepeatLoginStats(array $routerIds): array
    {
        // User dengan MAC yang sama login berkali-kali (repeat login)
        $repeatMacs = UserSession::whereIn('router_id', $routerIds)
            ->where('mac_address', '!=', 'unknown')
            ->where('mac_address', '!=', '')
            ->selectRaw('mac_address, COUNT(*) as cnt')
            ->groupBy('mac_address')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('cnt')
            ->limit(20)
            ->get();

        $totalRepeat = UserSession::whereIn('router_id', $routerIds)
            ->where('mac_address', '!=', 'unknown')
            ->where('mac_address', '!=', '')
            ->selectRaw('COUNT(*) as total_count, COUNT(DISTINCT mac_address) as unique_macs')
            ->groupBy('mac_address')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        return [
            'repeatMacCount' => $totalRepeat,
            'repeatMacs' => $repeatMacs->map(fn ($r) => ['mac' => $r->mac_address, 'count' => $r->cnt, 'users' => $this->getUsersForMac($r->mac_address)])->toArray(),
        ];
    }

    private function getUsersForMac(string $mac): array
    {
        return UserSession::where('mac_address', $mac)
            ->with('user')
            ->distinct('user_id')
            ->get()
            ->map(fn ($s) => $s->user?->identity_value ?? '-')
            ->unique()
            ->toArray();
    }

    private function getAutoReconnectStats(array $routerIds): array
    {
        // Sesi yg statusnya grace (disconnected, masih dalam grace period)
        $inGrace = UserSession::whereIn('router_id', $routerIds)
            ->where('status', 'disconnected')
            ->where('expires_at', '>', now())
            ->count();

        return [
            'inGracePeriod' => $inGrace,
        ];
    }

    private function getIdentityStats(array $routerIds): array
    {
        $byType = UserSession::whereIn('router_id', $routerIds)
            ->whereHas('user')
            ->join('users', 'user_sessions.user_id', '=', 'users.id')
            ->selectRaw("COALESCE(users.identity_type, 'unknown') as id_type, COUNT(*) as login_count")
            ->groupBy('users.identity_type')
            ->pluck('login_count', 'id_type')
            ->toArray();

        return [
            'identityTypes' => [
                'room' => $byType['room'] ?? 0,
                'google' => $byType['google'] ?? 0,
                'wa' => $byType['wa'] ?? 0,
                'email' => $byType['email'] ?? 0,
            ],
        ];
    }

    private function emptyData(): array
    {
        return [
            'totalUsers' => 0, 'uniqueUsers7d' => 0, 'totalSessions' => 0,
            'sessionsToday' => 0, 'activeNow' => 0, 'inGrace' => 0,
            'repeatMacCount' => 0, 'repeatMacs' => [],
            'inGracePeriod' => 0, 'autoReconnects7d' => 0,
            'identityTypes' => ['room' => 0, 'google' => 0, 'wa' => 0, 'email' => 0],
        ];
    }
}