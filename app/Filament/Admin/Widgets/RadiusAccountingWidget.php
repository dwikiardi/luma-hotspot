<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class RadiusAccountingWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.radius-accounting';

    protected static ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'rows' => $this->getRecentAccounting(),
            'stats' => $this->getAccountingStats(),
        ];
    }

    public function getRecentAccounting()
    {
        return DB::table('radacct')
            ->select([
                'radacctid',
                'username',
                'nasipaddress',
                'callingstationid as mac',
                'framedipaddress as client_ip',
                'acctstarttime',
                'acctstoptime',
                'acctsessiontime',
                'acctinputoctets as bytes_in',
                'acctoutputoctets as bytes_out',
                'acctterminatecause',
            ])
            ->orderByDesc('radacctid')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->is_active = $row->acctstoptime === null;
                $row->duration = $row->acctsessiontime
                    ? $this->formatDuration($row->acctsessiontime)
                    : ($row->is_active
                        ? $this->formatDuration((int) now()->diffInSeconds(\Carbon\Carbon::parse($row->acctstarttime)))
                        : '-');
                $row->traffic = $this->formatBytes($row->bytes_in ?? 0) . ' / ' . $this->formatBytes($row->bytes_out ?? 0);
                $row->client_ip = $this->stripCidr($row->client_ip ?? '');
                $row->mac = $row->mac ?: '-';
                $row->terminate = $row->acctterminatecause ?: ($row->is_active ? 'Active' : '-');
                return $row;
            });
    }

    public function getAccountingStats()
    {
        $active = DB::table('radacct')->whereNull('acctstoptime')->count();
        $todayStarts = DB::table('radacct')
            ->whereRaw("acctstarttime::timestamp::date = now()::date")
            ->count();
        $todayStops = DB::table('radacct')
            ->whereRaw("acctstoptime::timestamp::date = now()::date")
            ->whereNotNull('acctstoptime')
            ->count();

        $totalIn = DB::table('radacct')
            ->whereNull('acctstoptime')
            ->sum('acctinputoctets') ?? 0;
        $totalOut = DB::table('radacct')
            ->whereNull('acctstoptime')
            ->sum('acctoutputoctets') ?? 0;

        return (object) [
            'active' => $active,
            'today_starts' => $todayStarts,
            'today_stops' => $todayStops,
            'traffic_in' => $this->formatBytes($totalIn),
            'traffic_out' => $this->formatBytes($totalOut),
        ];
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