<?php

namespace App\Filament\Tenant\Resources\TenantStaffResource\Pages;

use App\Filament\Tenant\Resources\TenantStaffResource\TenantStaffResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenantStaff extends EditRecord
{
    protected static string $resource = TenantStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record->id === auth('tenant_users')->id()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['password'])) {
            unset($data['password'], $data['password_confirmation']);
        }
        $data['tenant_id'] = filament()->getTenant()?->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
