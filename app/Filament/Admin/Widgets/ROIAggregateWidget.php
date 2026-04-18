<?php

namespace App\Filament\Admin\Widgets;

use App\Models\AnalyticsEvent;
use Filament\Widgets\Widget;

class ROIAggregateWidget extends Widget
{
    protected static ?string $heading = 'ROI Platform — 30 Hari';

    protected int|string|array $columnSpan = 4;

    protected static ?string $pollingInterval = '300';

    protected static string $view = 'filament.admin.widgets.roi-aggregate';

    protected function getViewData(): array
    {
        $monthlyEvents = AnalyticsEvent::where('occurred_at', '>=', now()->subDays(30))
            ->where('event_type', 'login_success')
            ->count();

        $totalRoi = $monthlyEvents * 12500;
        $targetRoi = 500000000;
        $progressPercent = $targetRoi > 0 ? min(round(($totalRoi / $targetRoi) * 100), 100) : 0;

        $dataTamu = round($totalRoi * 0.42);
        $komplain = round($totalRoi * 0.37);
        $repeatVisit = round($totalRoi * 0.21);

        return [
            'totalRoi' => $totalRoi,
            'targetRoi' => $targetRoi,
            'progressPercent' => $progressPercent,
            'dataTamu' => $dataTamu,
            'komplain' => $komplain,
            'repeatVisit' => $repeatVisit,
        ];
    }

    private function formatRupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
