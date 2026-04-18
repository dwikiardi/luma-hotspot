<?php

namespace App\Providers\Filament;

use App\Http\Middleware\HandleImpersonation;
use App\Models\Tenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class TenantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('tenant')
            ->path('dashboard')
            ->login()
            ->colors(['primary' => Color::Violet])
            ->brandName('Luma Network')
            ->darkMode(false)
            ->tenant(
                model: Tenant::class,
                slugAttribute: 'slug',
                ownershipRelationship: 'tenant'
            )
            ->tenantRoutePrefix('venue')
            ->discoverResources(
                in: app_path('Filament/Tenant/Resources'),
                for: 'App\Filament\Tenant\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/Tenant/Pages'),
                for: 'App\Filament\Tenant\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/Tenant/Widgets'),
                for: 'App\Filament\Tenant\Widgets'
            )
            ->navigationGroups([
                'Overview',
                'Pengunjung',
                'Konfigurasi WiFi',
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                HandleImpersonation::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('tenant_users');
    }
}
