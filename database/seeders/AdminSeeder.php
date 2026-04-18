<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::updateOrCreate(
            ['email' => 'admin@luma.com'],
            [
                'name' => 'Admin Luma',
                'email' => 'admin@luma.com',
                'password' => bcrypt('password'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        Admin::updateOrCreate(
            ['email' => 'operator@luma.com'],
            [
                'name' => 'Operator Luma',
                'email' => 'operator@luma.com',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
    }
}
