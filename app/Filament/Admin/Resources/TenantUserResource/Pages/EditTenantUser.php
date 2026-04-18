<?php

namespace App\Filament\Admin\Resources\TenantUserResource\Pages;

use App\Filament\Admin\Resources\TenantUserResource\TenantUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenantUser extends EditRecord
{
    protected static string $resource = TenantUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['password'])) {
            unset($data['password'], $data['password_confirmation']);
        }

        return $data;
    }
}
