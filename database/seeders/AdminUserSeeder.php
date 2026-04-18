<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        AdminUser::firstOrCreate(
            ['email' => 'superadmin@lumanetwork.id'],
            [
                'name' => 'Super Admin Luma',
                'password' => 'LumaSuperAdmin2024!',
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        AdminUser::firstOrCreate(
            ['email' => 'admin@lumanetwork.id'],
            [
                'name' => 'Admin Luma',
                'password' => 'LumaAdmin2024!',
                'role' => 'admin',
                'is_active' => true,
            ]
        );
    }
}
