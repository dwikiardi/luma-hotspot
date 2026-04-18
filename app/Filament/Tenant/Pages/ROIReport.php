<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;

class ROIReport extends Page
{
    protected static string $view = 'filament.tenant.pages.roi-report';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Laporan ROI';

    protected static ?string $navigationGroup = 'Overview';

    protected static ?int $navigationSort = 2;
}
