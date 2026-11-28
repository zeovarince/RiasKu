<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukFoto;
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

        $catalog = [
            [
                'sku' => 'RSK-CUSH-01',
                'kategori' => 'complexion',
                'name' => 'Velvet Matte Cushion SPF 35',
                'brand' => 'RiasKu Cosmetics',
                'weight_grams' => 120,
                'base_price' => 183000,
                'retail_price' => 185000,
                'bpom_number' => 'NA18250300112',
                'short_ingredients' => 'Niacinamide 4%, Centella Asiatica, UV Filter SPF 35 PA+++',
                'description' => 'Cushion compact dengan puff lembut dan hasil velvet matte natural. Memberikan coverage medium-to-full yang ringan, tahan lama, serta melindungi kulit dengan SPF 35 PA+++.',
                'is_preorder' => false,
                'preorder_estimate_days' => null,
                'min_stock' => 5,
                'image_path' => '/images/products/velvet-matte-cushion.jpg',
                'variants' => [
                    ['name' => 'Natural 01', 'stock' => 8],
                    ['name' => 'Warm 02', 'stock' => 12],
                ],
            ],
            [
                'sku' => 'RSK-LIP-04',
                'kategori' => 'lip-care',
                'name' => 'Glaze Tinted Lip Oil Butter',
                'brand' => 'RiasKu Beauty',
                'weight_grams' => 30,
                'base_price' => 93000,
                'retail_price' => 95000,
                'bpom_number' => 'NA18251300409',
                'short_ingredients' => 'Shea Butter, Jojoba Seed Oil, Vitamin E, Peptide Complex',
                'description' => 'Lip oil berwarna dengan formula ringan tidak lengket dan aplikator doe-foot lembut. Memberikan kelembapan intens serta kilau natural sehat sepanjang hari.',
                'is_preorder' => false,
                'preorder_estimate_days' => null,
                'min_stock' => 5,
                'image_path' => '/images/products/glaze-tinted-lip-oil.jpg',
                'variants' => [
                    ['name' => 'Rose Butter', 'stock' => 10],
                    ['name' => 'Peach Glow', 'stock' => 8],
                ],
            ],
            [
                'sku' => 'RSK-POWD-02',
                'kategori' => 'complexion',
                'name' => 'Translucent HD Setting Powder',
                'brand' => 'RiasKu Studio',
                'weight_grams' => 80,
                'base_price' => 133000,
                'retail_price' => 135000,
                'bpom_number' => 'NA18250400221',
                'short_ingredients' => 'Micro-Fine Silica, Squalane, Camellia Japonica Extract',
                'description' => 'Bedak tabur translucent berbutir micro-fine lengkap dengan puff halus untuk mengunci hasil makeup, menyamarkan pori, dan mengontrol kilap tanpa whitecast.',
                'is_preorder' => false,
                'preorder_estimate_days' => null,
                'min_stock' => 5,
                'image_path' => '/images/products/translucent-setting-powder.jpg',
                'variants' => [
                    ['name' => 'Translucent', 'stock' => 5],
                ],
            ],
            [
                'sku' => 'RSK-BRV-010',
                'kategori' => 'rias-pengantin',
                'name' => 'Paket Rias Pengantin Modern',
                'brand' => 'RiasKu Bridal',
                'weight_grams' => 0,
                'base_price' => 1848000,
                'retail_price' => 1850000,
                'bpom_number' => null,
                'short_ingredients' => 'Layanan Skin Prep, Rias Soft Glam Pengantin, Retouch Akad & Resepsi',
                'description' => 'Paket layanan rias pengantin modern soft glam bersama Makeup Artist profesional RiasKu Surabaya. Termasuk konsultasi look, skin preparation, dan retouch hari acara.',
                'is_preorder' => false,
                'preorder_estimate_days' => null,
                'min_stock' => 5,
                'image_path' => '/images/products/paket-rias-pengantin.jpg',
                'variants' => [
                    ['name' => 'Paket Lengkap', 'stock' => 99],
                ],
            ],
            [
                'sku' => 'RSK-PO-01',
                'kategori' => 'lip-care',
                'name' => 'Special Edition Velvet Tint (PO)',
                'brand' => 'RiasKu Studio',
                'weight_grams' => 35,
                'base_price' => 88000,
                'retail_price' => 90000,
                'bpom_number' => 'NA18251300881',
                'short_ingredients' => 'Hyaluronic Filling Spheres, Macadamia Oil, Ceramide NP',
                'description' => 'Lip tint velvet warna Burgundy Rose edisi terbatas lengkap dengan gantungan charm silikon pink pastel. Pigmentasi intens, tekstur lembut, dibuat berdasarkan pesanan Pre-Order.',
                'is_preorder' => true,
                'preorder_estimate_days' => 5,
                'min_stock' => 5,
                'image_path' => '/images/products/special-edition-velvet-tint.jpg',
                'variants' => [
                    ['name' => 'Burgundy Rose', 'stock' => 0],
                ],
            ],
            [
                'sku' => 'Kahf-001',
                'kategori' => 'skincare-prep',
                'name' => 'Kahf Skin Energizing & Brightening Face Wash',
                'brand' => 'Kahf',
                'weight_grams' => 100,
                'base_price' => 38000,
                'retail_price' => 40000,
                'bpom_number' => 'NA18201203047',
                'short_ingredients' => 'HydroBalance, Moroccan Mint Extract, Mediterranean Grapefruit Extract',
                'description' => 'Sabun pembersih wajah 100 ml dengan teknologi HydroBalance™, ekstrak Moroccan Mint, dan Mediterranean Grapefruit yang menyegarkan sekaligus membantu mencerahkan kulit kusam tanpa rasa kering.',
                'is_preorder' => false,
                'preorder_estimate_days' => null,
                'min_stock' => 5,
                'image_path' => '/images/products/kahf-face-wash.jpg',
                'variants' => [
                    ['name' => '100 ml', 'stock' => 12],
                ],
            ],
            [
                'sku' => 'BRB-001',
                'kategori' => 'skincare-prep',
                'name' => 'Barber Daily Ultra Sensitive Face Wash',
                'brand' => 'Barber Daily',
                'weight_grams' => 100,
                'base_price' => 38000,
                'retail_price' => 40000,
                'bpom_number' => 'NA18231206118',
                'short_ingredients' => 'Vitamin B3 (Niacinamide), Panthenol, Allantoin, Low pH Gentle Formula',
                'description' => 'Pembersih wajah berformulasi lembut Low pH untuk kulit sensitif dari Makarizo Barber Daily. Membersihkan tanpa membuat kulit ketarik sembari menenangkan dan menghidrasi dengan Vitamin B3, Panthenol, dan Allantoin.',
                'is_preorder' => false,
                'preorder_estimate_days' => null,
                'min_stock' => 5,
                'image_path' => '/images/products/barber-daily-face-wash.jpg',
                'variants' => [
                    ['name' => '100 ml', 'stock' => 15],
                ],
            ],
            [
                'sku' => 'RSK-EYE-01',
                'kategori' => 'eye-makeup',
                'name' => 'Rose Nude Eyeshadow Palette 6-Shades',
                'brand' => 'RiasKu Studio',
                'weight_grams' => 60,
                'base_price' => 118000,
                'retail_price' => 120000,
                'bpom_number' => 'NA18251200514',
                'short_ingredients' => 'Mica, Micro-Fine Silica, Jojoba Esters, Vitamin E',
                'description' => 'Palet eyeshadow 6 warna (3 matte & 3 shimmer glitter nuansa rose nude) dalam kemasan compact pink travel-friendly dengan gantungan charm. Mudah dibaurkan, minim fallout, cocok untuk daily hingga acara spesial.',
                'is_preorder' => false,
                'preorder_estimate_days' => null,
                'min_stock' => 5,
                'image_path' => '/images/products/soft-glam-eyeshadow-palette.jpg',
                'variants' => [
                    ['name' => '02 Rose Nude', 'stock' => 10],
                ],
            ],
            [
                'sku' => 'RSK-WIS-01',
                'kategori' => 'rias-wisuda',
                'name' => 'Paket Rias Wisuda Natural Glam',
                'brand' => 'RiasKu Studio',
                'weight_grams' => 0,
                'base_price' => 448000,
                'retail_price' => 450000,
                'bpom_number' => null,
                'short_ingredients' => 'Layanan Skin Prep, Rias Flawless Dewy Glam, Pemasangan Bulu Mata Natural',
                'description' => 'Paket jasa rias wisuda dengan tampilan natural soft glam yang segar, tahan lama, dan fotogenik untuk sesi foto indoor maupun outdoor di area Surabaya.',
                'is_preorder' => false,
                'preorder_estimate_days' => null,
                'min_stock' => 5,
                'image_path' => '/images/products/paket-rias-wisuda.jpg',
                'variants' => [
                    ['name' => 'Paket Wisuda', 'stock' => 99],
                ],
            ],
            [
                'sku' => 'RSK-BRSH-01',
                'kategori' => 'alat-rias',
                'name' => 'Crystal Rose Makeup Brush Set (5 Pcs)',
                'brand' => 'RiasKu Tools',
                'weight_grams' => 110,
                'base_price' => 78000,
                'retail_price' => 80000,
                'bpom_number' => null,
                'short_ingredients' => 'Synthetic Micro-Silk Bristles, Rose Gold Ferrule, Acrylic Crystal Handle',
                'description' => 'Set 5 kuas makeup gagang kristal glitter dengan ferrule rose-gold dan bulu sintetis ultra-lembut bergradasi pink. Berisi powder/blush brush, 3 kuas eyeshadow, serta spoolie & brow comb.',
                'is_preorder' => false,
                'preorder_estimate_days' => null,
                'min_stock' => 5,
                'image_path' => '/images/products/pro-silk-makeup-brush-set.jpg',
                'variants' => [
                    ['name' => 'Set 5 Pcs (Rose Gold)', 'stock' => 14],
                ],
            ],
        ];

        foreach ($catalog as $item) {
            $produk = Produk::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'kategori_id' => $categoryMap[$item['kategori']]->id,
                    'name' => $item['name'],
                    'brand' => $item['brand'],
                    'weight_grams' => $item['weight_grams'],
                    'base_price' => $item['base_price'],
                    'retail_price' => $item['retail_price'],
                    'bpom_number' => $item['bpom_number'],
                    'short_ingredients' => $item['short_ingredients'],
                    'description' => $item['description'],
                    'is_preorder' => $item['is_preorder'],
                    'preorder_estimate_days' => $item['preorder_estimate_days'],
                    'min_stock' => $item['min_stock'],
                    'is_active' => true,
                ]
            );

            if (! $produk->varians()->exists()) {
                foreach ($item['variants'] as $variant) {
                    ProdukVarian::create([
                        'produk_id' => $produk->id,
                        'name' => $variant['name'],
                        'stock' => $variant['stock'],
                        'is_active' => true,
                    ]);
                }
            }

            $existingSeedPhoto = $produk->fotos()
                ->where('image_path', $item['image_path'])
                ->first();

            if (! $existingSeedPhoto) {
                $produk->fotos()->update(['is_primary' => false]);
                ProdukFoto::create([
                    'produk_id' => $produk->id,
                    'image_path' => $item['image_path'],
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);
            } elseif (! $existingSeedPhoto->is_primary) {
                $produk->fotos()->update(['is_primary' => false]);
                $existingSeedPhoto->update(['is_primary' => true, 'sort_order' => 0]);
            }
        }
    }
}
