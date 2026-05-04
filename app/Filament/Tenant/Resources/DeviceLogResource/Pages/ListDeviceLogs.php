<?php

namespace App\Filament\Tenant\Resources\DeviceLogResource\Pages;

use App\Filament\Tenant\Resources\DeviceLogResource\DeviceLogResource;
use Filament\Resources\Pages\ListRecords;

class ListDeviceLogs extends ListRecords
{
    protected static string $resource = DeviceLogResource::class;
}