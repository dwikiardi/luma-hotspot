<?php

namespace App\Services;

use App\Models\AnalyticsDaily;
use App\Models\AnalyticsEvent;
use App\Models\VisitorProfile;
use Carbon\Carbon;

class AnalyticsEngine
{
    public function track(string $eventType, array $context): void
    {
        AnalyticsEvent::create([
            'tenant_id' => $context['tenant_id'],
            'router_id' => $context['router_id'],
            'user_id' => $context['user_id'] ?? null,
            'device_id' => $context['device_id'] ?? null,
            'event_type' => $eventType,
            'mac_address' => $context['mac'] ?? null,
            'ip_address' => $context['ip'] ?? null,
            'login_method' => $context['login_method'] ?? null,
            'meta' => $context['meta'] ?? null,
            'occurred_at' => now(),
        ]);
    }

    public function getDashboardSummary(int $tenantId, string $period): array
    {
        $days = match ($period) {
            'today' => 1,
            '7days' => 7,
            '30days' => 30,
            default => 7,
        };

        $startDate = now()->subDays($days);

        $dailyData = AnalyticsDaily::where('tenant_id', $tenantId)
            ->where('date', '>=', $startDate)
            ->get();

        $uniqueVisitors = $dailyData->sum('unique_visitors');
        $totalSessions = $dailyData->sum('total_sessions');
        $newVisitors = $dailyData->sum('new_visitors');
        $returningVisitors = $dailyData->sum('returning_visitors');
        $autoReconnects = $dailyData->sum('auto_reconnects');
        $forcedRelogins = $dailyData->sum('forced_relogins');

        $totalLogins = $dailyData->sum('login_google')
            + $dailyData->sum('login_wa')
            + $dailyData->sum('login_room')
            + $dailyData->sum('login_email');

        $loginMethods = [
            'google' => $dailyData->sum('login_google'),
            'wa' => $dailyData->sum('login_wa'),
            'room' => $dailyData->sum('login_room'),
            'email' => $dailyData->sum('login_email'),
        ];

        $totalAttempts = $autoReconnects + $forcedRelogins;
        $seamlessRate = $totalAttempts > 0
            ? round(($autoReconnects / $totalAttempts) * 100, 1)
            : 100;

        $peakHour = $dailyData->avg('peak_hour') ?? 12;

        $hourlyDistribution = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyDistribution[$h] = rand(5, 50);
        }

        return [
            'visitors' => [
                'unique' => $uniqueVisitors,
                'total_sessions' => $totalSessions,
                'new' => $newVisitors,
                'returning' => $returningVisitors,
                'returning_rate' => $uniqueVisitors > 0
                    ? round(($returningVisitors / $uniqueVisitors) * 100, 1)
                    : 0,
            ],
            'peak_hours' => [
                'peak_hour' => (int) $peakHour,
                'hourly_distribution' => $hourlyDistribution,
            ],
            'login_methods' => $loginMethods,
            'grace_period' => [
                'auto_reconnects' => $autoReconnects,
                'forced_relogins' => $forcedRelogins,
                'seamless_rate' => $seamlessRate,
                'complaints_saved' => $autoReconnects,
            ],
            'top_days' => $dailyData->sortByDesc('total_sessions')
                ->take(3)
                ->map(fn ($d) => [
                    'date' => $d->date->format('Y-m-d'),
                    'sessions' => $d->total_sessions,
                ])
                ->values()
                ->toArray(),
        ];
    }

    public function upsertVisitorProfile(int $tenantId, int $userId, string $loginMethod): void
    {
        $profile = VisitorProfile::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if ($profile) {
            $profile->increment('total_visits');
            $profile->increment('total_sessions');
            $profile->update([
                'last_visit_at' => now(),
                'preferred_login_method' => $loginMethod,
            ]);
        } else {
            VisitorProfile::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'total_visits' => 1,
                'total_sessions' => 1,
                'first_visit_at' => now(),
                'last_visit_at' => now(),
                'avg_session_minutes' => 0,
                'preferred_login_method' => $loginMethod,
                'visitor_type' => 'new',
            ]);
        }

        $this->classifyVisitor($tenantId, $userId);
    }

    public function classifyVisitor(int $tenantId, int $userId): void
    {
        $profile = VisitorProfile::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if (! $profile) {
            return;
        }

        $visitorType = match (true) {
            $profile->total_visits >= 10 => 'loyal',
            $profile->total_visits >= 5 => 'regular',
            $profile->total_visits >= 2 => 'returning',
            default => 'new',
        };

        $profile->update(['visitor_type' => $visitorType]);
    }

    public function aggregateDaily(int $tenantId, int $routerId, string $date): void
    {
        $dateObj = Carbon::parse($date);
        $startOfDay = $dateObj->copy()->startOfDay();
        $endOfDay = $dateObj->copy()->endOfDay();

        $events = AnalyticsEvent::where('tenant_id', $tenantId)
            ->where('router_id', $routerId)
            ->whereBetween('occurred_at', [$startOfDay, $endOfDay])
            ->get();

        $uniqueMacs = $events->where('mac_address', '!=', null)
            ->unique('mac_address')
            ->count();

        $sessionStarts = $events->where('event_type', 'session_start')->count();
        $autoReconnects = $events->where('event_type', 'auto_reconnect')->count();
        $forcedRelogins = $events->where('event_type', 'forced_relogin')->count();

        $loginGoogle = $events->where('event_type', 'login_success')
            ->where('login_method', 'google')->count();
        $loginWa = $events->where('event_type', 'login_success')
            ->where('login_method', 'wa')->count();
        $loginRoom = $events->where('event_type', 'login_success')
            ->where('login_method', 'room')->count();
        $loginEmail = $events->where('event_type', 'login_success')
            ->where('login_method', 'email')->count();

        $totalAttempts = $autoReconnects + $forcedRelogins;
        $reconnectRate = $totalAttempts > 0
            ? round(($autoReconnects / $totalAttempts) * 100, 2)
            : 0;

        AnalyticsDaily::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'router_id' => $routerId,
                'date' => $dateObj->toDateString(),
            ],
            [
                'unique_visitors' => $uniqueMacs,
                'total_sessions' => $sessionStarts,
                'new_visitors' => 0,
                'returning_visitors' => 0,
                'auto_reconnects' => $autoReconnects,
                'forced_relogins' => $forcedRelogins,
                'reconnect_rate' => $reconnectRate,
                'login_google' => $loginGoogle,
                'login_wa' => $loginWa,
                'login_room' => $loginRoom,
                'login_email' => $loginEmail,
                'avg_session_minutes' => 0,
                'peak_hour' => 12,
            ]
        );
    }
}
