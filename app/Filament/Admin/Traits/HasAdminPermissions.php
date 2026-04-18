<?php

namespace App\Filament\Admin\Traits;

trait HasAdminPermissions
{
    protected static function isSuperAdmin(): bool
    {
        return auth('admin')->user()?->role === 'super_admin';
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canDeleteAny(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canForceDeleteAny(): bool
    {
        return static::isSuperAdmin();
    }
}
