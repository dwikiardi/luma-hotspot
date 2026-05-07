<?php

namespace App\Filament\Tenant\Widgets;

use Filament\Widgets\Widget;

class ActivityLogWidget extends Widget
{
    protected static string $view = 'filament.tenant.widgets.activity-log';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 99;

    public function getHeading(): string
    {
        return 'Activity Log';
    }
}
