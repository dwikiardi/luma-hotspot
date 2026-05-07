<?php

namespace App\Helpers;

use App\Models\Tenant;

class TenantTime
{
    public static function timezone(): string
    {
        try {
            $tz = filament()?->getTenant()?->timezone;
            if ($tz) return $tz;
        } catch (\Throwable) {}

        $tenantId = auth('tenant_users')->user()?->tenant_id;
        if ($tenantId) {
            $tz = Tenant::where('id', $tenantId)->value('timezone');
            if ($tz) return $tz;
        }

        return config('app.timezone', 'UTC');
    }

    public static function format(?string $date, string $format = 'd M H:i'): string
    {
        if (! $date) return '-';
        return \Carbon\Carbon::parse($date, 'UTC')
            ->setTimezone(self::timezone())
            ->format($format);
    }

    public static function now(): \Carbon\Carbon
    {
        return now()->setTimezone(self::timezone());
    }
}
