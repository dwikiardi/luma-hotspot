<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\TenantPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    TenantPanelProvider::class,
];
