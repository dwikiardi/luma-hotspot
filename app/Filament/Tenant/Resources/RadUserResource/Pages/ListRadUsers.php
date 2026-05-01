<?php

namespace App\Filament\Tenant\Resources\RadUserResource\Pages;

use App\Filament\Tenant\Resources\RadUserResource\RadUserResource;
use Filament\Resources\Pages\ListRecords;

class ListRadUsers extends ListRecords
{
    protected static string $resource = RadUserResource::class;
}