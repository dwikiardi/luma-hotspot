<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SetTenantTimezone
{
    public function handle(Request $request, Closure $next)
    {
        $timezone = null;

        try {
            $tenant = filament()?->getTenant();
            if ($tenant?->timezone) {
                $timezone = $tenant->timezone;
            }
        } catch (\Throwable) {}

        if (! $timezone) {
            $tenantId = session("current_tenant_id")
                ?? auth("tenant_users")->user()?->tenant_id
                ?? null;
            if ($tenantId) {
                $timezone = Tenant::where("id", $tenantId)->value("timezone");
            }
        }

        if ($timezone) {
            config(["app.timezone" => $timezone]);
            date_default_timezone_set($timezone);
        }

        $response = $next($request);
        $response->headers->set("X-Timezone", $timezone ?? "null");
        return $response;
    }
}
