<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('services.riasku_admin.email');
        $password = config('services.riasku_admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('AdminSeeder dilewati: atur RIASKU_ADMIN_EMAIL dan RIASKU_ADMIN_PASSWORD di .env lokal.');

            return;
        }

        Admin::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('services.riasku_admin.name', 'Admin RiasKu'),
                'password_hash' => Hash::make($password),
                'is_active' => true,
            ],
        );
    }
}
