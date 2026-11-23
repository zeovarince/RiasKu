<?php

namespace Database\Seeders;

use App\Models\Pelanggan;
use App\Models\Produk;
use App\Models\UlasanProduk;
use Illuminate\Database\Seeder;

class UlasanProdukSeeder extends Seeder
{
    public function run(): void
    {
        $reviews = [
            [
                'sku' => 'RSK-CUSH-01',
                'username' => 'surya',
                'rating' => 5,
                'comment' => 'Hasil velvet matte-nya halus, tidak cakey, dan tahan seharian di kulit berminyak.',
            ],
            [
                'sku' => 'RSK-CUSH-01',
                'username' => 'pelanggan',
                'rating' => 4,
                'comment' => 'Shade Natural 01 cocok di kulit saya. Coverage medium dan nyaman dipakai seharian.',
            ],
            [
                'sku' => 'RSK-LIP-04',
                'username' => 'pelanggan',
                'rating' => 5,
                'comment' => 'Lip oil-nya melembapkan bibir dan warnanya natural. Packaging juga rapi saat dikirim.',
            ],
        ];

        foreach ($reviews as $item) {
            $produk = Produk::query()->where('sku', $item['sku'])->first();
            $pelanggan = Pelanggan::query()->where('username', $item['username'])->first();

            if (! $produk || ! $pelanggan) {
                continue;
            }

            UlasanProduk::query()->firstOrCreate(
                [
                    'produk_id' => $produk->id,
                    'pelanggan_id' => $pelanggan->id,
                ],
                [
                    'user_name' => $pelanggan->username,
                    'rating' => $item['rating'],
                    'comment' => $item['comment'],
                    'status' => 'approved',
                ],
            );
        }
    }
}
