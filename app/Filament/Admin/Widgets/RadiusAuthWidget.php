<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class RadiusAuthWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.radius-auth';

    protected static ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'auths' => $this->getRecentAuths(),
            'stats' => $this->getAuthStats(),
        ];
    }

    public function getRecentAuths()
    {
        return DB::table('radpostauth')
            ->select([
                'id',
                'username',
                'pass',
                'reply',
                'authdate',
            ])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->authdate = \Carbon\Carbon::parse($row->authdate);
                $row->is_accept = $row->reply === 'Access-Accept';
                return $row;
            });
    }

    public function getAuthStats()
    {
        $today = now()->format('Y-m-d');

        $accepts = DB::table('radpostauth')
            ->whereRaw("authdate::timestamp::date = ?::date", [$today])
            ->where('reply', 'Access-Accept')
            ->count();

        $rejects = DB::table('radpostauth')
            ->whereRaw("authdate::timestamp::date = ?::date", [$today])
            ->where('reply', 'Access-Reject')
            ->count();

        $uniqueUsers = DB::table('radpostauth')
            ->whereRaw("authdate::timestamp::date = ?::date", [$today])
            ->where('reply', 'Access-Accept')
            ->distinct('username')
            ->count('username');

        return (object) [
            'accepts' => $accepts,
            'rejects' => $rejects,
            'unique_users' => $uniqueUsers,
            'rate' => ($accepts + $rejects) > 0
                ? round(($accepts / ($accepts + $rejects)) * 100, 1)
                : 100,
        ];
    }
}