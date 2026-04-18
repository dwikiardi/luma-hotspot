<?php

namespace App\Services;

use App\Models\AnalyticsEvent;

class ComplaintReducer
{
    public function logAutoReconnect(array $context): void
    {
        AnalyticsEvent::create([
            'tenant_id' => $context['tenant_id'],
            'router_id' => $context['router_id'],
            'user_id' => $context['user_id'] ?? null,
            'device_id' => $context['device_id'] ?? null,
            'event_type' => 'auto_reconnect',
            'mac_address' => $context['mac'] ?? null,
            'ip_address' => $context['ip'] ?? null,
            'meta' => [
                'grace_seconds_remaining' => $context['grace_seconds_remaining'] ?? 0,
                'match_score' => $context['match_score'] ?? 0,
            ],
            'occurred_at' => now(),
        ]);
    }

    public function logForcedRelogin(array $context): void
    {
        AnalyticsEvent::create([
            'tenant_id' => $context['tenant_id'],
            'router_id' => $context['router_id'],
            'user_id' => $context['user_id'] ?? null,
            'device_id' => $context['device_id'] ?? null,
            'event_type' => 'forced_relogin',
            'mac_address' => $context['mac'] ?? null,
            'ip_address' => $context['ip'] ?? null,
            'meta' => [
                'reason' => $context['reason'] ?? 'unknown',
                'time_since_disconnect' => $context['time_since_disconnect'] ?? 0,
            ],
            'occurred_at' => now(),
        ]);
    }

    public function getComplaintReport(int $tenantId, string $period): array
    {
        $days = match ($period) {
            'today' => 1,
            '7days' => 7,
            '30days' => 30,
            default => 7,
        };

        $startDate = now()->subDays($days);

        $events = AnalyticsEvent::where('tenant_id', $tenantId)
            ->whereIn('event_type', ['auto_reconnect', 'forced_relogin'])
            ->where('occurred_at', '>=', $startDate)
            ->get();

        $autoReconnects = $events->where('event_type', 'auto_reconnect')->count();
        $forcedRelogins = $events->where('event_type', 'forced_relogin')->count();

        $totalAttempts = $autoReconnects + $forcedRelogins;
        $seamlessRate = $totalAttempts > 0
            ? round(($autoReconnects / $totalAttempts) * 100, 1)
            : 100;

        $complaintsPrevented = $autoReconnects;
        $estimatedSavings = $complaintsPrevented * 25000;

        $reasons = $events->where('event_type', 'forced_relogin')
            ->groupBy(fn ($e) => $e->meta['reason'] ?? 'unknown')
            ->map->count();

        $dailyStats = [];
        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($i)->toDateString();
            $dayEvents = $events->filter(fn ($e) => $e->occurred_at->toDateString() === $date);

            $dailyStats[] = [
                'date' => $date,
                'auto_reconnects' => $dayEvents->where('event_type', 'auto_reconnect')->count(),
                'forced_relogins' => $dayEvents->where('event_type', 'forced_relogin')->count(),
                'seamless_rate' => $dayEvents->count() > 0
                    ? round(($dayEvents->where('event_type', 'auto_reconnect')->count() / $dayEvents->count()) * 100, 1)
                    : 100,
            ];
        }

        return [
            'period' => $period,
            'complaints_prevented' => $complaintsPrevented,
            'estimated_savings' => $estimatedSavings,
            'seamless_rate' => $seamlessRate,
            'summary' => "Grace Period berhasil mencegah {$complaintsPrevented} komplain tamu. "
                ."Tingkat seamless reconnect: {$seamlessRate}%",
            'auto_reconnects' => $autoReconnects,
            'forced_relogins' => $forcedRelogins,
            'by_reason' => $reasons->toArray(),
            'daily_stats' => $dailyStats,
        ];
    }
}
