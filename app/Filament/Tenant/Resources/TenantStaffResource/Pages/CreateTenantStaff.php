<?php

namespace App\Filament\Tenant\Resources\TenantStaffResource\Pages;

use App\Filament\Tenant\Resources\TenantStaffResource\TenantStaffResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantStaff extends CreateRecord
{
    protected static string $resource = TenantStaffResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = filament()->getTenant()?->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
