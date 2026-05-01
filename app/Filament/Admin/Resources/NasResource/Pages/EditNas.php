<?php

namespace App\Filament\Admin\Resources\NasResource\Pages;

use App\Filament\Admin\Resources\NasResource\NasResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditNas extends EditRecord
{
    protected static string $resource = NasResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $router = $this->record;
        $nasIp = $data['nas_ip'] ?? null;
        $nasSecret = $data['nas_secret'] ?? null;

        unset($data['nas_ip'], $data['nas_secret']);

        if ($nasIp !== null) {
            DB::table('nas')->updateOrInsert(
                ['shortname' => $router->nas_identifier],
                [
                    'nasname' => $nasIp,
                    'secret' => $nasSecret ?: DB::table('nas')->where('shortname', $router->nas_identifier)->value('secret') ?? 'luma_radius_secret',
                    'type' => 'other',
                    'ports' => 0,
                    'community' => '',
                    'description' => $router->name . ' - ' . ($router->tenant?->name ?? ''),
                ]
            );
        }

        return $data;
    }
}