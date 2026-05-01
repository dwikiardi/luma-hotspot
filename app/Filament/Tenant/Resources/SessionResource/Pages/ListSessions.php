<?php

namespace App\Filament\Tenant\Resources\SessionResource\Pages;

use App\Filament\Tenant\Resources\SessionResource\SessionResource;
use Filament\Resources\Pages\ListRecords;

class ListSessions extends ListRecords
{
    protected static string $resource = SessionResource::class;
}