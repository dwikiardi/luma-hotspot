<?php

namespace App\Filament\Admin\Resources\AdminUserResource\Pages;

use App\Filament\Admin\Resources\AdminUserResource\AdminUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['role']) && $data['role'] === 'super_admin') {
            if (! auth('admin')->user()?->isSuperAdmin()) {
                $data['role'] = 'admin';
            }
        }

        return $data;
    }
}
