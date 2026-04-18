<?php

namespace App\Filament\Tenant\Resources\RouterResource\Pages;

use App\Filament\Tenant\Resources\RouterResource\RouterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRouters extends ListRecords
{
    protected static string $resource = RouterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Router')
                ->mutateFormDataUsing(function (array $data): array {
                    $data['tenant_id'] = auth('tenant_users')->user()->tenant_id;

                    return $data;
                }),
        ];
    }
}
