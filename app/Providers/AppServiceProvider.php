<?php

namespace App\Providers;

use Filament\Events\ServingFilament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(ServingFilament::class, function (): void {
            try {
                $tenant = filament()?->getTenant();
                if ($tenant?->timezone) {
                    config(['app.timezone' => $tenant->timezone]);
                    date_default_timezone_set($tenant->timezone);
                }
            } catch (\Throwable) {}
        });
    }
}
