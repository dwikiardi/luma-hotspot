<?php

namespace App\Filament\Tenant\Resources\RouterResource\Pages;

use App\Filament\Tenant\Resources\RouterResource\RouterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRouter extends EditRecord
{
    protected static string $resource = RouterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
