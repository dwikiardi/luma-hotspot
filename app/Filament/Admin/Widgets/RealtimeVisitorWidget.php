<?php

namespace App\Filament\Admin\Widgets;

use App\Models\AnalyticsEvent;
use Filament\Widgets\ChartWidget;

class RealtimeVisitorWidget extends ChartWidget
{
    protected static ?string $heading = 'Visitor Realtime (24 jam terakhir)';

    protected int|string|array $columnSpan = 6;

    protected static ?string $pollingInterval = '60';

    protected function getHeight(): string
    {
        return '300px';
    }

    protected function getData(): array
    {
        $labels = [];
        $data = [];
        $colors = [];
        $currentHour = now()->hour;

        for ($h = 0; $h < 24; $h++) {
            $labels[] = str_pad($h, 2, '0', STR_PAD_LEFT).':00';

            $count = AnalyticsEvent::whereDate('occurred_at', today())
                ->whereRaw('EXTRACT(HOUR FROM occurred_at) = ?', [$h])
                ->where('event_type', 'login_success')
                ->count();
            $data[] = $count;

            $colors[] = $h === $currentHour ? '#f59e0b' : 'rgba(99, 102, 241, 0.7)';
        }

        return [
            'datasets' => [
                [
                    'label' => 'Visitor',
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
