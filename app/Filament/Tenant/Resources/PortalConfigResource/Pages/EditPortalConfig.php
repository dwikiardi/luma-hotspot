<?php

namespace App\Filament\Tenant\Resources\PortalConfigResource\Pages;

use App\Filament\Tenant\Resources\PortalConfigResource\PortalConfigResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPortalConfig extends EditRecord
{
    protected static string $resource = PortalConfigResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getTitle(): string
    {
        return 'Konfigurasi Portal';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $config = $this->record;
        $data['branding'] = $config->branding ?? ['name' => '', 'color' => '#6366f1', 'logo' => null];
        $data['active_login_methods'] = $config->active_login_methods ?? ['google' => true, 'wa' => true, 'room' => false, 'email' => false];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

        return $record;
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
