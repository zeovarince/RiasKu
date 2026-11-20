<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('services.riasku_admin.email') ?: 'admin@riasku.com';
        $password = config('services.riasku_admin.password') ?: 'password123';
        $name = config('services.riasku_admin.name') ?: 'Admin RiasKu';

        Admin::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password_hash' => Hash::make($password),
                'is_active' => true,
            ],
        );

        $this->command?->info("AdminSeeder berhasil: Email: {$email} | Password: {$password}");
    }
}
