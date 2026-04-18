<?php

namespace App\Filament\Admin\Resources\TenantUserResource\Pages;

use App\Filament\Admin\Resources\TenantUserResource\TenantUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenantUsers extends ListRecords
{
    protected static string $resource = TenantUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
