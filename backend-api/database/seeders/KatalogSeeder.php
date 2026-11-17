<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
use Illuminate\Database\Seeder;

class KatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'complexion', 'name' => 'Complexion', 'icon' => 'complexion'],
            ['slug' => 'lip-care', 'name' => 'Lip Care', 'icon' => 'lip'],
            ['slug' => 'eye-makeup', 'name' => 'Eye Makeup', 'icon' => 'eye'],
            ['slug' => 'skincare-prep', 'name' => 'Skincare Prep', 'icon' => 'complexion'],
            ['slug' => 'rias-pengantin', 'name' => 'Rias Pengantin', 'icon' => 'heart'],
            ['slug' => 'rias-wisuda', 'name' => 'Rias Wisuda', 'icon' => 'crown'],
            ['slug' => 'alat-rias', 'name' => 'Alat Rias', 'icon' => 'brush'],
        ];

        $categoryMap = [];
        foreach ($categories as $cat) {
            $categoryMap[$cat['slug']] = Kategori::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name'], 'icon' => $cat['icon']]
            );
        }

        // Product 1: Velvet Matte Cushion
        if (!Produk::where('sku', 'RSK-CUSH-01')->exists()) {
            $p1 = Produk::create([
                'kategori_id' => $categoryMap['complexion']->id,
                'sku' => 'RSK-CUSH-01',
                'name' => 'Velvet Matte Cushion SPF 35',
                'brand' => 'RiasKu Cosmetics',
                'weight_grams' => 120,
                'base_price' => 183000,
                'retail_price' => 185000,
                'description' => 'Cushion dengan hasil velvet matte untuk tampilan rias natural sehari-hari.',
                'is_preorder' => false,
                'is_active' => true,
            ]);

            ProdukVarian::create(['produk_id' => $p1->id, 'name' => 'Natural 01', 'stock' => 8, 'is_active' => true]);
            ProdukVarian::create(['produk_id' => $p1->id, 'name' => 'Warm 02', 'stock' => 12, 'is_active' => true]);
        }

        // Product 2: Glaze Tinted Lip Oil Butter
        if (!Produk::where('sku', 'RSK-LIP-04')->exists()) {
            $p2 = Produk::create([
                'kategori_id' => $categoryMap['lip-care']->id,
                'sku' => 'RSK-LIP-04',
                'name' => 'Glaze Tinted Lip Oil Butter',
                'brand' => 'RiasKu Beauty',
                'weight_grams' => 30,
                'base_price' => 93000,
                'retail_price' => 95000,
                'description' => 'Lip oil berwarna dengan sentuhan lembap dan kilau natural.',
                'is_preorder' => false,
                'is_active' => true,
            ]);

            ProdukVarian::create(['produk_id' => $p2->id, 'name' => 'Rose Butter', 'stock' => 10, 'is_active' => true]);
            ProdukVarian::create(['produk_id' => $p2->id, 'name' => 'Peach Glow', 'stock' => 8, 'is_active' => true]);
        }

        // Product 3: Translucent HD Setting Powder
        if (!Produk::where('sku', 'RSK-POWD-02')->exists()) {
            $p3 = Produk::create([
                'kategori_id' => $categoryMap['complexion']->id,
                'sku' => 'RSK-POWD-02',
                'name' => 'Translucent HD Setting Powder',
                'brand' => 'RiasKu Studio',
                'weight_grams' => 80,
                'base_price' => 133000,
                'retail_price' => 135000,
                'description' => 'Bedak tabur translucent untuk membantu mengunci hasil makeup.',
                'is_preorder' => false,
                'is_active' => true,
            ]);

            ProdukVarian::create(['produk_id' => $p3->id, 'name' => 'Translucent', 'stock' => 3, 'is_active' => true]);
        }

        // Product 4: Pre-Order Special Edition Tint
        if (!Produk::where('sku', 'RSK-PO-01')->exists()) {
            $p4 = Produk::create([
                'kategori_id' => $categoryMap['lip-care']->id,
                'sku' => 'RSK-PO-01',
                'name' => 'Special Edition Velvet Tint (PO)',
                'brand' => 'RiasKu Studio',
                'weight_grams' => 35,
                'base_price' => 88000,
                'retail_price' => 90000,
                'description' => 'Warna edisi terbatas. Produk dibuat berdasarkan pesanan pre-order.',
                'is_preorder' => true,
                'preorder_estimate_days' => 5,
                'is_active' => true,
            ]);

            ProdukVarian::create(['produk_id' => $p4->id, 'name' => 'Burgundy Rose', 'stock' => 0, 'is_active' => true]);
        }
    }
}
