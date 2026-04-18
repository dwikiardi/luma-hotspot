<?php

namespace App\Filament\Tenant\Resources\RouterResource\Pages;

use App\Filament\Tenant\Resources\RouterResource\RouterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRouter extends CreateRecord
{
    protected static string $resource = RouterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth('tenant_users')->user()->tenant_id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
