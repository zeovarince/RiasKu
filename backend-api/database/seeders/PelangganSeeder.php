<?php

namespace Database\Seeders;

use App\Models\Pelanggan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PelangganSeeder extends Seeder
{
    public function run(): void
    {
        $pelanggans = [
            [
                'username' => 'surya',
                'email' => 'surya@riasku.com',
                'password_hash' => Hash::make('password123'),
                'is_active' => true,
            ],
            [
                'username' => 'pelanggan',
                'email' => 'pelanggan@riasku.com',
                'password_hash' => Hash::make('password123'),
                'is_active' => true,
            ],
        ];

        foreach ($pelanggans as $p) {
            Pelanggan::updateOrCreate(
                ['email' => $p['email']],
                $p
            );
        }

        $this->command?->info('PelangganSeeder berhasil: surya@riasku.com & pelanggan@riasku.com (Password: password123)');
    }
}
