<?php

namespace App\Filament\Admin\Widgets;

use App\Models\AnalyticsEvent;
use App\Models\Tenant;
use Filament\Widgets\Widget;

class AlertsWidget extends Widget
{
    protected static ?string $heading = 'Alerts & Notifikasi';

    protected int|string|array $columnSpan = 4;

    protected static ?string $pollingInterval = '60';

    protected static string $view = 'filament.admin.widgets.alerts';

    protected function getViewData(): array
    {
        $alerts = [];

        // Alert 1: Tenant baru belum setup portal config
        $newTenants = Tenant::where('created_at', '>=', now()->subDays(7))
            ->whereDoesntHave('portalConfig')
            ->get();

        foreach ($newTenants as $tenant) {
            $alerts[] = [
                'severity' => 'info',
                'icon' => '🔵',
                'message' => "{$tenant->name} belum konfigurasi portal login",
                'action_label' => 'Setup Sekarang',
                'action_url' => route('filament.admin.resources.tenants.view', ['record' => $tenant->id]),
            ];
        }

        // Alert 2: Seamless Rate rendah per tenant
        $tenants = Tenant::where('is_active', true)->get();
        foreach ($tenants as $tenant) {
            $autoReconnects = AnalyticsEvent::where('tenant_id', $tenant->id)
                ->where('occurred_at', '>=', now()->subDay())
                ->where('event_type', 'auto_reconnect')
                ->count();
            $forcedRelogins = AnalyticsEvent::where('tenant_id', $tenant->id)
                ->where('occurred_at', '>=', now()->subDay())
                ->where('event_type', 'forced_relogin')
                ->count();
            $totalAttempts = $autoReconnects + $forcedRelogins;

            if ($totalAttempts > 10) {
                $rate = round(($autoReconnects / $totalAttempts) * 100, 1);
                if ($rate < 70) {
                    $alerts[] = [
                        'severity' => 'warning',
                        'icon' => '🟡',
                        'message' => "Seamless rate {$tenant->name} turun ke {$rate}%",
                        'action_label' => 'Lihat Detail',
                        'action_url' => route('filament.admin.resources.tenants.view', ['record' => $tenant->id]),
                    ];
                }
            }

            // Alert 3: Complaint tinggi tapi grace period disabled
            $config = $tenant->portalConfig;
            if ($config && ! $config->grace_period_enabled) {
                if ($forcedRelogins > 50) {
                    $alerts[] = [
                        'severity' => 'warning',
                        'icon' => '🟡',
                        'message' => "{$tenant->name} punya {$forcedRelogins} komplain login hari ini. Aktifkan Grace Period?",
                        'action_label' => 'Aktifkan Sekarang',
                        'action_url' => route('filament.admin.resources.tenants.view', ['record' => $tenant->id]),
                    ];
                }
            }
        }

        return [
            'alerts' => $alerts,
        ];
    }
}
