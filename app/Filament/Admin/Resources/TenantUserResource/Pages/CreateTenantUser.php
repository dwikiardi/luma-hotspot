<?php

namespace App\Filament\Admin\Resources\TenantUserResource\Pages;

use App\Filament\Admin\Resources\TenantUserResource\TenantUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantUser extends CreateRecord
{
    protected static string $resource = TenantUserResource::class;
}
