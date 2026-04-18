<?php

namespace App\Services;

use App\Models\AnalyticsDaily;
use App\Models\VisitorProfile;

class ROICalculator
{
    public function calculate(int $tenantId, array $assumptions = []): array
    {
        $defaults = [
            'data_value_per_contact' => 15000,
            'complaint_handling_cost' => 25000,
            'happy_guest_spend_multiplier' => 1.3,
        ];

        $assumptions = array_merge($defaults, $assumptions);

        $startDate = now()->subDays(30);

        $dailyData = AnalyticsDaily::where('tenant_id', $tenantId)
            ->where('date', '>=', $startDate)
            ->get();

        $uniqueVisitors = $dailyData->sum('unique_visitors');
        $totalSessions = $dailyData->sum('total_sessions');
        $autoReconnects = $dailyData->sum('auto_reconnects');
        $forcedRelogins = $dailyData->sum('forced_relogins');

        $dataValue = $uniqueVisitors * $assumptions['data_value_per_contact'];

        $complaintsPrevented = $autoReconnects;
        $complaintSavings = $complaintsPrevented * $assumptions['complaint_handling_cost'];

        $visitorProfiles = VisitorProfile::where('tenant_id', $tenantId)
            ->where('visitor_type', '!=', 'new')
            ->count();
        $repeatVisitorValue = $visitorProfiles * $assumptions['data_value_per_contact']
            * $assumptions['happy_guest_spend_multiplier'];

        $totalROI = $dataValue + $complaintSavings + $repeatVisitorValue;

        $competitorLoss = $uniqueVisitors * $assumptions['data_value_per_contact']
            + $forcedRelogins * $assumptions['complaint_handling_cost'];

        return [
            'summary' => [
                'total_roi' => $totalROI,
                'total_roi_display' => 'Rp '.number_format($totalROI, 0, ',', '.'),
                'headline' => 'Luma Network menghasilkan nilai Rp '
                    .number_format($totalROI, 0, ',', '.').' dalam 30 hari',
            ],
            'breakdown' => [
                [
                    'label' => 'Data Tamu Terkumpul',
                    'value' => $dataValue,
                    'display' => 'Rp '.number_format($dataValue, 0, ',', '.'),
                    'detail' => "{$uniqueVisitors} tamu × Rp "
                        .number_format($assumptions['data_value_per_contact'], 0, ',', '.')
                        .' per kontak',
                    'icon' => 'users',
                ],
                [
                    'label' => 'Komplain yang Dicegah',
                    'value' => $complaintSavings,
                    'display' => 'Rp '.number_format($complaintSavings, 0, ',', '.'),
                    'detail' => "{$complaintsPrevented} komplain × Rp "
                        .number_format($assumptions['complaint_handling_cost'], 0, ',', '.')
                        .' per penanganan',
                    'icon' => 'shield-check',
                ],
                [
                    'label' => 'Nilai Repeat Visitor',
                    'value' => $repeatVisitorValue,
                    'display' => 'Rp '.number_format($repeatVisitorValue, 0, ',', '.'),
                    'detail' => "{$visitorProfiles} tamu kembali × multiplier "
                        .$assumptions['happy_guest_spend_multiplier'].'x',
                    'icon' => 'arrow-path',
                ],
            ],
            'vs_competitor' => [
                'data_tamu_tanpa_luma' => 0,
                'komplain_tanpa_handling' => $forcedRelogins * $assumptions['complaint_handling_cost'],
                'repeat_track_tanpa_luma' => 0,
                'total_yang_hilang' => $competitorLoss,
            ],
            'assumptions' => $assumptions,
        ];
    }
}
