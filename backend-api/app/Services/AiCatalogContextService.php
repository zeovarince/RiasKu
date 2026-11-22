<?php

namespace App\Services;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\TarifPengiriman;
use App\Models\ZonaPengiriman;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AiCatalogContextService
{
    private const CACHE_KEY = 'riasku:ai_catalog_context:v3';

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCatalogSnapshot(): array
    {
        $ttl = (int) config('services.ai_assistant.catalog_cache_ttl', 300);

        $builder = function (): array {
            $categories = Kategori::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Kategori $k): array => [
                    'name' => $k->name,
                    'slug' => $k->slug,
                ])
                ->all();

            $shippingCities = ZonaPengiriman::query()
                ->where('is_active', true)
                ->distinct()
                ->orderBy('city')
                ->pluck('city')
                ->values()
                ->all();

            $shippingRates = TarifPengiriman::query()
                ->where('tarif_pengiriman.is_active', true)
                ->join('zona_pengiriman', 'zona_pengiriman.id', '=', 'tarif_pengiriman.zona_pengiriman_id')
                ->where('zona_pengiriman.is_active', true)
                ->select([
                    'zona_pengiriman.city',
                    'tarif_pengiriman.courier',
                    'tarif_pengiriman.service',
                    'tarif_pengiriman.price_per_kg',
                    'tarif_pengiriman.etd_min_days',
                    'tarif_pengiriman.etd_max_days',
                ])
                ->distinct()
                ->orderBy('zona_pengiriman.city')
                ->orderBy('tarif_pengiriman.courier')
                ->orderBy('tarif_pengiriman.price_per_kg')
                ->get()
                ->map(fn ($row): array => [
                    'city' => (string) $row->city,
                    'courier' => (string) $row->courier,
                    'service' => (string) $row->service,
                    'price_per_kg' => (int) $row->price_per_kg,
                    'etd' => $row->etd_min_days === $row->etd_max_days
                        ? "{$row->etd_min_days} hari"
                        : "{$row->etd_min_days}-{$row->etd_max_days} hari",
                ])
                ->values()
                ->all();

            // STRICT: Jangan pernah mengambil base_price agar harga modal toko tidak pernah masuk prompt/output AI
            $products = Produk::query()
                ->where('is_active', true)
                ->select([
                    'id',
                    'kategori_id',
                    'sku',
                    'name',
                    'brand',
                    'weight_grams',
                    'retail_price',
                    'pack_quantity',
                    'pack_price',
                    'is_preorder',
                    'preorder_estimate_days',
                    'short_ingredients',
                    'bpom_number',
                    'description',
                    'is_active',
                ])
                ->with(['kategori:id,name,slug', 'activeVarians', 'primaryFoto'])
                ->withSum(['activeVarians as active_stock_sum'], 'stock')
                ->orderBy('id')
                ->get()
                ->map(function (Produk $product): array {
                    $variants = $product->activeVarians->map(function ($varian) use ($product): array {
                        return [
                            'id' => $varian->id,
                            'name' => $varian->name,
                            'stock' => (int) $varian->stock,
                            'stock_label' => $varian->resolveStockLabel((bool) $product->is_preorder),
                        ];
                    })->values()->all();

                    return [
                        'id' => (int) $product->id,
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'brand' => $product->brand,
                        'weight_grams' => (int) ($product->weight_grams ?? 100),
                        'category_name' => $product->kategori?->name ?? 'Lainnya',
                        'category_slug' => $product->kategori?->slug ?? 'uncategorized',
                        'retail_price' => (int) $product->retail_price,
                        'pack_quantity' => $product->pack_quantity,
                        'pack_price' => $product->pack_price,
                        'total_stock' => (int) $product->total_stock,
                        'stock_label' => $product->stock_label,
                        'is_preorder' => (bool) $product->is_preorder,
                        'preorder_estimate_days' => $product->preorder_estimate_days,
                        'short_ingredients' => $product->short_ingredients,
                        'bpom_number' => $product->bpom_number,
                        'description' => Str::limit((string) ($product->description ?? ''), 220),
                        'type' => $product->type,
                        'art' => $product->art,
                        'variants' => $variants,
                    ];
                })
                ->all();

            return [
                'categories' => $categories,
                'shipping_cities' => $shippingCities,
                'shipping_rates' => $shippingRates,
                'products' => $products,
            ];
        };

        if ($ttl <= 0) {
            return $builder();
        }

        return Cache::remember(self::CACHE_KEY, $ttl, $builder);
    }

    public function buildPromptContextText(?int $contextProductId = null): string
    {
        $snapshot = $this->getCatalogSnapshot();
        $categoryNames = collect($snapshot['categories'])->pluck('name')->implode(', ');
        $shippingCities = collect($snapshot['shipping_cities'] ?? [])->implode(', ');
        $courierSummary = collect($snapshot['shipping_rates'] ?? [])
            ->groupBy('courier')
            ->map(function ($rows, $courier): string {
                $services = collect($rows)->pluck('service')->filter()->unique()->implode(', ');

                return $services !== '' ? "{$courier} ({$services})" : (string) $courier;
            })
            ->values()
            ->implode(' & ');

        $lines = [
            '[INFO_TOKO_RIASKU]',
            '- Nama Toko: RiasKu Official Store & Studio (berbasis di Kota Surabaya, Jawa Timur).',
            '- Fitur Website Pelanggan:',
            '  * Katalog Produk (`/products`): mendukung pencarian, pengurutan harga/nama, filter kategori, dan filter status stok (Tersedia / Pre-Order / Habis).',
            '  * Keranjang Belanja (`/cart`): manajemen qty real-time; item Ready Stock dibatasi stok varian, item Pre-Order bisa dipesan meski stok 0.',
            '  * Checkout (`/checkout`): memerlukan login akun pelanggan, hitung ongkir otomatis via zona & berat paket, pilih COD Surabaya atau pembayaran online Midtrans.',
            '  * Cek Status & Resi (`/orders/track`): pelanggan & tamu publik bisa lacak progres (`Menunggu Pembayaran` -> `Diproses` -> `Dikirim` -> `Selesai` / `Dibatalkan`), salin resi, & klik "Pesanan Sudah Diterima" setelah paket tiba.',
            '  * Konsultasi & Booking Rias (`/consultation`): booking jadwal Makeup Artist RiasKu (Wisuda, Pengantin, Lamaran).',
            '  * Asisten AI (`/ai-assistant` & modal Tanya AI): menyimpan riwayat percakapan multi-sesi.',
            '- Pembayaran COD: Khusus pengiriman wilayah Kota Surabaya (bayar tunai ke kurir toko RiasKu).',
            '- Pembayaran Online: QRIS, Virtual Account (BCA, BNI, BRI, Mandiri), dan E-Wallet (GoPay/ShopeePay) via Midtrans untuk seluruh Indonesia.',
            '- Ekspedisi & Zona Ongkir Terdaftar: Kurir '.($courierSummary !== '' ? $courierSummary : 'JNE & J&T').'; kota zona aktif saat ini: '.($shippingCities !== '' ? $shippingCities : 'Surabaya, Sidoarjo, Gresik, Bangkalan').'.',
            '- Pre-Order (PO): Produk bertanda Pre-Order diproses khusus dengan estimasi penyiapan 1 sampai 6 hari kerja sebelum dikirim.',
            '- Kategori Katalog: '.($categoryNames !== '' ? $categoryNames : 'Kosmetik & Jasa Rias'),
            '',
            '[KATALOG_RIASKU]',
        ];

        foreach ($snapshot['products'] as $p) {
            $variantText = collect($p['variants'])
                ->map(fn (array $v): string => "{$v['name']} ({$v['stock_label']}, stok {$v['stock']})")
                ->implode('; ');
            $poNote = $p['is_preorder'] && $p['preorder_estimate_days']
                ? " | Est PO: {$p['preorder_estimate_days']} hari"
                : '';
            $bpomNote = $p['bpom_number'] ? " | BPOM: {$p['bpom_number']}" : '';
            $ingNote = $p['short_ingredients'] ? " | Kandungan: {$p['short_ingredients']}" : '';
            $focusMark = $contextProductId && $p['id'] === $contextProductId ? ' [SEDANG DILIHAT PENGGUNA]' : '';

            $lines[] = sprintf(
                '- ID:%d | SKU:%s | %s (%s)%s | Kategori:%s | Harga:Rp%s | Status:%s (stok:%d)%s%s%s | Varian:[%s] | Info:%s',
                $p['id'],
                $p['sku'] ?: '-',
                $p['name'],
                $p['brand'],
                $focusMark,
                $p['category_name'],
                number_format($p['retail_price'], 0, ',', '.'),
                $p['stock_label'],
                $p['total_stock'],
                $poNote,
                $bpomNote,
                $ingNote,
                $variantText !== '' ? $variantText : 'Standar',
                $p['description'] !== '' ? $p['description'] : '-',
            );
        }

        if (! empty($snapshot['shipping_rates'])) {
            $lines[] = '';
            $lines[] = '[TARIF_ONGKIR_DATABASE]';
            foreach (collect($snapshot['shipping_rates'])->groupBy('city') as $city => $items) {
                $rateStr = collect($items)->map(fn (array $r): string => sprintf(
                    '%s %s Rp%s/kg (%s)',
                    $r['courier'],
                    $r['service'],
                    number_format((int) $r['price_per_kg'], 0, ',', '.'),
                    $r['etd']
                ))->unique()->implode(', ');
                $lines[] = "- {$city}: {$rateStr}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Validasi ID terhadap produk aktif nyata di DB agar bebas halusinasi & menyembunyikan produk nonaktif.
     *
     * @param  array<int, mixed>  $productIds
     * @return list<array<string, mixed>>
     */
    public function hydrateRecommendedProducts(array $productIds): array
    {
        $cleanIds = collect($productIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(3)
            ->values();

        if ($cleanIds->isEmpty()) {
            return [];
        }

        $products = Produk::query()
            ->where('is_active', true)
            ->whereIn('id', $cleanIds->all())
            ->with(['kategori:id,name,slug', 'activeVarians', 'primaryFoto', 'fotos'])
            ->withSum(['activeVarians as active_stock_sum'], 'stock')
            ->get()
            ->keyBy('id');

        $cards = [];
        foreach ($cleanIds as $id) {
            /** @var Produk|null $product */
            $product = $products->get($id);
            if (! $product) {
                continue;
            }

            $readyVariant = $product->activeVarians->first(fn ($v): bool => (int) $v->stock > 0);
            $defaultVariant = $readyVariant ?? $product->activeVarians->first();

            $cards[] = [
                'id' => (int) $product->id,
                'sku' => $product->sku,
                'code' => $product->sku ?? "PRODUK #{$product->id}",
                'name' => $product->name,
                'brand' => $product->brand,
                'category_name' => $product->kategori?->name ?? 'Lainnya',
                'price' => (int) $product->retail_price,
                'retail_price' => (int) $product->retail_price,
                'price_label' => 'Rp'.number_format((int) $product->retail_price, 0, ',', '.'),
                'stock' => (int) $product->total_stock,
                'total_stock' => (int) $product->total_stock,
                'stock_label' => $product->stock_label,
                'is_preorder' => (bool) $product->is_preorder,
                'preorder_estimate_days' => $product->preorder_estimate_days,
                'image_url' => $product->image_url,
                'art' => $product->art,
                'type' => $product->type,
                'default_variant_id' => $defaultVariant?->id,
                'default_variant_name' => $defaultVariant?->name,
                'default_variant_stock' => $defaultVariant ? (int) $defaultVariant->stock : 0,
            ];
        }

        return $cards;
    }

    /**
     * @return list<string>
     */
    public function getDynamicSuggestions(): array
    {
        return [
            'Cari cushion untuk kulit kombinasi',
            'Rekomendasi paket rias pengantin atau wisuda',
            'Produk apa saja yang sedang Pre-Order?',
            'Bagaimana cara bayar COD Surabaya & cek resi?',
        ];
    }
}
