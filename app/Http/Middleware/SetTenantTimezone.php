<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetTenantTimezone
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = filament()?->getTenant();

        if ($tenant && $tenant->timezone) {
            config(['app.timezone' => $tenant->timezone]);
            date_default_timezone_set($tenant->timezone);
        }

        return $next($request);
    }
}
