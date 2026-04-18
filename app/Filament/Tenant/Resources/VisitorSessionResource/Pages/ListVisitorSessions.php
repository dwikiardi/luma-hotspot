<?php

namespace App\Filament\Tenant\Resources\VisitorSessionResource\Pages;

use App\Filament\Tenant\Resources\VisitorSessionResource\VisitorSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListVisitorSessions extends ListRecords
{
    protected static string $resource = VisitorSessionResource::class;
}
