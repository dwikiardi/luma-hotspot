<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class ActiveSessionsWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.active-sessions';

    protected static ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'sessions' => $this->getActiveSessions(),
            'total' => $this->getTotalActive(),
        ];
    }

    public function getActiveSessions()
    {
        return DB::table('radacct')
            ->whereNull('acctstoptime')
            ->select([
                'radacctid',
                'username',
                'nasipaddress',
                'callingstationid as mac',
                'framedipaddress as client_ip',
                'acctstarttime as login_at',
                'acctinputoctets as bytes_in',
                'acctoutputoctets as bytes_out',
                DB::raw("EXTRACT(EPOCH FROM (NOW() - acctstarttime))::int as session_seconds"),
            ])
            ->orderBy('acctstarttime', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                $row->duration = $this->formatDuration($row->session_seconds ?? 0);
                $row->traffic = $this->formatBytes($row->bytes_in ?? 0) . ' / ' . $this->formatBytes($row->bytes_out ?? 0);
                $row->client_ip = $this->stripCidr($row->client_ip ?? '');
                return $row;
            });
    }

    public function getTotalActive(): int
    {
        return DB::table('radacct')->whereNull('acctstoptime')->count();
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) return $seconds . 's';
        if ($seconds < 3600) return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 1) . ' GB';
    }

    private function stripCidr(string $ip): string
    {
        return preg_replace('/\/\d+$/', '', $ip);
    }
}