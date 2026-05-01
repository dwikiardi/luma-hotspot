<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class GracePeriodLogWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.grace-period-log';

    protected static ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'sessions' => $this->getGraceSessions(),
            'stats' => $this->getStats(),
        ];
    }

    public function getGraceSessions()
    {
        return DB::table('user_sessions')
            ->select([
                'user_sessions.id',
                'users.name as user_name',
                'users.identity_value',
                'user_sessions.status',
                'user_sessions.mac_address',
                'user_sessions.ip_address',
                'user_sessions.fingerprint_hash',
                'user_sessions.cookie_token',
                'user_sessions.login_method',
                'user_sessions.login_at',
                'user_sessions.last_seen_at',
                'user_sessions.expires_at',
                'user_sessions.disconnected_at',
                'routers.name as router_name',
            ])
            ->leftJoin('users', 'user_sessions.user_id', '=', 'users.id')
            ->leftJoin('routers', 'user_sessions.router_id', '=', 'routers.id')
            ->whereIn('user_sessions.status', ['active', 'disconnected'])
            ->orderByDesc('user_sessions.login_at')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                $row->hash_short = $row->fingerprint_hash
                    ? (strlen($row->fingerprint_hash) > 16
                        ? substr($row->fingerprint_hash, 0, 12) . '...'
                        : $row->fingerprint_hash)
                    : '-';
                $row->cookie_short = $row->cookie_token
                    ? substr($row->cookie_token, 0, 8) . '...'
                    : '-';
                $row->has_grace = $row->status === 'disconnected' && $row->expires_at && now()->lt(\Carbon\Carbon::parse($row->expires_at));
                $row->remaining = $row->expires_at
                    ? max(0, (int) now()->diffInSeconds(\Carbon\Carbon::parse($row->expires_at), false))
                    : 0;
                $row->remaining_text = $row->remaining > 0
                    ? ($row->remaining < 60 ? $row->remaining . 's' : floor($row->remaining / 60) . 'm ' . ($row->remaining % 60) . 's')
                    : 'Expired';
                return $row;
            });
    }

    public function getStats()
    {
        $active = DB::table('user_sessions')->where('status', 'active')->count();
        $inGrace = DB::table('user_sessions')
            ->where('status', 'disconnected')
            ->where('expires_at', '>', now())
            ->count();
        $withFingerprint = DB::table('user_sessions')
            ->where('status', 'active')
            ->where('fingerprint_hash', '!=', 'unknown')
            ->whereNotNull('fingerprint_hash')
            ->where('fingerprint_hash', '!=', '')
            ->count();
        $withMac = DB::table('user_sessions')
            ->where('status', 'active')
            ->where('mac_address', '!=', 'unknown')
            ->whereNotNull('mac_address')
            ->where('mac_address', '!=', '')
            ->count();

        return (object) [
            'active' => $active,
            'in_grace' => $inGrace,
            'with_fingerprint' => $withFingerprint,
            'with_mac' => $withMac,
        ];
    }
}