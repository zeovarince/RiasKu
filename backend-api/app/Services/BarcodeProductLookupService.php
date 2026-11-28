<?php

namespace App\Services;

use App\Models\Kategori;
use App\Models\Produk;
use Illuminate\Support\Facades\Http;

class BarcodeProductLookupService
{
    public function normalizeBarcode(string $raw): string
    {
        return trim((string) preg_replace('/[\r\n\t]+/', '', $raw));
    }

    public function buildSkuPrefixFromName(string $name): string
    {
        $cleaned = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9\s]+/', ' ', $name)));
        $words = array_values(array_filter(
            preg_split('/\s+/', $cleaned) ?: [],
            static fn (string $word): bool => $word !== ''
        ));

        if ($words === []) {
            return 'PROD';
        }

        if (count($words) === 1) {
            return substr($words[0], 0, 4);
        }

        if (count($words) === 2) {
            return substr($words[0], 0, 2).substr($words[1], 0, 2);
        }

        $initials = '';
        foreach (array_slice($words, 0, 4) as $word) {
            $initials .= substr($word, 0, 1);
        }

        return $initials !== '' ? $initials : 'PROD';
    }

    public function generateUniqueSkuFromName(string $name, ?int $ignoreProductId = null): string
    {
        $prefix = $this->buildSkuPrefixFromName($name);

        for ($seq = 1; $seq <= 99; $seq++) {
            $candidate = sprintf('RSK-%s-%02d', $prefix, $seq);
            $exists = Produk::query()
                ->where('sku', $candidate)
                ->when($ignoreProductId, fn ($q) => $q->where('id', '!=', $ignoreProductId))
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        return sprintf('RSK-%s-%s', $prefix, strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)));
    }

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $rawBarcode): array
    {
        $barcode = $this->normalizeBarcode($rawBarcode);

        $localProduct = Produk::with(['kategori', 'varians', 'fotos', 'primaryFoto'])
            ->withSum(['activeVarians as active_stock_sum'], 'stock')
            ->where(function ($query) use ($barcode): void {
                $query->where('barcode', $barcode)
                    ->orWhereRaw('LOWER(sku) = ?', [strtolower($barcode)]);
            })
            ->first();

        if ($localProduct) {
            return [
                'status' => 'existing_local',
                'source' => 'local_db',
                'barcode' => $barcode,
                'product' => $localProduct,
            ];
        }

        $suggestion = $this->lookupOpenBeautyFacts($barcode);
        if ($suggestion !== null) {
            return [
                'status' => 'external_found',
                'source' => 'open_beauty_facts',
                'barcode' => $barcode,
                'suggestion' => $suggestion,
            ];
        }

        return [
            'status' => 'not_found',
            'source' => null,
            'barcode' => $barcode,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lookupOpenBeautyFacts(string $barcode): ?array
    {
        if (! preg_match('/^[0-9A-Za-z\-]{6,32}$/', $barcode)) {
            return null;
        }

        try {
            $url = 'https://world.openbeautyfacts.org/api/v2/product/'.rawurlencode($barcode).'.json';
            $response = Http::timeout(4)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'RiasKu-Inventory/1.0'])
                ->get($url, [
                    'fields' => 'product_name,brands,quantity,ingredients_text,categories,image_front_url,generic_name',
                ]);

            if (! $response->ok()) {
                return null;
            }

            $payload = $response->json();
            if (! is_array($payload) || (int) ($payload['status'] ?? 0) !== 1 || empty($payload['product'])) {
                return null;
            }

            $product = $payload['product'];
            $name = trim((string) ($product['product_name'] ?? ''));
            if ($name === '') {
                return null;
            }

            $rawBrands = trim((string) ($product['brands'] ?? ''));
            $brand = trim(explode(',', $rawBrands)[0] ?? '');
            $quantity = trim((string) ($product['quantity'] ?? ''));
            $ingredients = trim((string) ($product['ingredients_text'] ?? ''));
            $categoriesText = trim((string) ($product['categories'] ?? ''));
            $genericName = trim((string) ($product['generic_name'] ?? ''));

            return [
                'barcode' => $barcode,
                'name' => mb_substr($name, 0, 255),
                'brand' => mb_substr($brand, 0, 100),
                'weight_grams' => $this->parseWeightGrams($quantity),
                'short_ingredients' => $ingredients !== '' ? mb_substr($ingredients, 0, 255) : null,
                'description' => $genericName !== '' ? $genericName : null,
                'suggested_sku' => $this->generateUniqueSkuFromName($name),
                'suggested_category_id' => $this->guessCategoryId($name.' '.$categoriesText),
                'image_preview_url' => ! empty($product['image_front_url']) ? (string) $product['image_front_url'] : null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseWeightGrams(string $quantity): int
    {
        if ($quantity === '') {
            return 50;
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|l|liter|ml|g|gr|gram|oz)/i', $quantity, $matches)) {
            $value = (float) str_replace(',', '.', $matches[1]);
            $unit = strtolower($matches[2]);

            $grams = match ($unit) {
                'kg', 'l', 'liter' => (int) round($value * 1000),
                'oz' => (int) round($value * 28.35),
                default => (int) round($value),
            };

            return max(1, min(100000, $grams));
        }

        return 50;
    }

    private function guessCategoryId(string $haystack): ?int
    {
        $text = strtolower($haystack);
        $categories = Kategori::query()->get(['id', 'name', 'slug']);

        $rules = [
            'lip' => ['lip', 'lipstick', 'lipcream', 'tint', 'gloss', 'balm'],
            'eye' => ['eye', 'eyeshadow', 'mascara', 'eyeliner', 'brow', 'eyebrow'],
            'complexion' => ['cushion', 'foundation', 'powder', 'concealer', 'bb cream', 'cc cream', 'primer', 'blush'],
            'skincare' => ['face wash', 'cleanser', 'facial', 'wash', 'serum', 'toner', 'moisturizer', 'sunscreen', 'micellar', 'skincare', 'cream'],
            'alat' => ['brush', 'sponge', 'puff', 'curler', 'tool', 'applicator'],
        ];

        foreach ($rules as $slugHint => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $matched = $categories->first(function (Kategori $cat) use ($slugHint): bool {
                        $hay = strtolower($cat->name.' '.$cat->slug);

                        return str_contains($hay, $slugHint);
                    });

                    if ($matched) {
                        return (int) $matched->id;
                    }
                }
            }
        }

        return null;
    }
}
