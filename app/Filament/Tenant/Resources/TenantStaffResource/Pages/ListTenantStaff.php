<?php

namespace App\Filament\Tenant\Resources\TenantStaffResource\Pages;

use App\Filament\Tenant\Resources\TenantStaffResource\TenantStaffResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenantStaff extends ListRecords
{
    protected static string $resource = TenantStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['tenant_id'] = filament()->getTenant()?->id;

                    return $data;
                }),
        ];
    }
}
