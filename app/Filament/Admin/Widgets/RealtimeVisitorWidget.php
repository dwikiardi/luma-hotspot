<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class RealtimeVisitorWidget extends ChartWidget
{
    protected static ?string $heading = 'Login Realtime (24 jam terakhir)';

    protected int|string|array $columnSpan = 4;

    protected static ?string $pollingInterval = '60';

    protected function getHeight(): string
    {
        return '320px';
    }

    protected function getData(): array
    {
        $labels = [];
        $data = [];
        $colors = [];
        $currentHour = now()->hour;

        for ($h = 0; $h < 24; $h++) {
            $labels[] = str_pad($h, 2, '0', STR_PAD_LEFT).':00';

            try {
                $count = DB::table('radpostauth')
                    ->whereRaw("authdate::timestamp >= now() - interval '24 hours'")
                    ->whereRaw("EXTRACT(HOUR FROM authdate::timestamp) = ?", [$h])
                    ->where('reply', 'Access-Accept')
                    ->count();
            } catch (\Exception $e) {
                $count = 0;
            }
            $data[] = $count;

            $colors[] = $h === $currentHour ? '#f59e0b' : 'rgba(99, 102, 241, 0.7)';
        }

        return [
            'datasets' => [
                [
                    'label' => 'Login',
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }
}