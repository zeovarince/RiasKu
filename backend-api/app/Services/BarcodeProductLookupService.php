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

    /**
     * Extract Indonesian BPOM registration number (NIE) from 2D QR codes
     * such as "(90)NA18241201785", "(90)NA18241201785(91)260824", or plain "NA18241201785".
     */
    public function extractBpomNumber(string $raw): ?string
    {
        $clean = strtoupper($this->normalizeBarcode($raw));
        if ($clean === '') {
            return null;
        }

        if (preg_match('/\(90\)\s*(N[A-E]\d{11}|(?:MD|ML|TR|TI|SD|SI|QD|QI)\d{9,12})/i', $clean, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/\b(N[A-E]\d{11}|(?:MD|ML|TR|TI|SD|SI|QD|QI)\d{9,12})\b/i', $clean, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
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
        $bpomNumber = $this->extractBpomNumber($barcode);

        $localProduct = Produk::with(['kategori', 'varians', 'fotos', 'primaryFoto'])
            ->withSum(['activeVarians as active_stock_sum'], 'stock')
            ->where(function ($query) use ($barcode, $bpomNumber): void {
                $query->where('barcode', $barcode)
                    ->orWhereRaw('LOWER(sku) = ?', [strtolower($barcode)]);

                if ($bpomNumber !== null) {
                    $query->orWhereRaw('LOWER(bpom_number) = ?', [strtolower($bpomNumber)]);
                }
            })
            ->first();

        if ($localProduct) {
            return [
                'status' => 'existing_local',
                'source' => 'local_db',
                'barcode' => $barcode,
                'bpom_number' => $bpomNumber ?? $localProduct->bpom_number,
                'product' => $localProduct,
            ];
        }

        if ($bpomNumber !== null) {
            return [
                'status' => 'bpom_detected',
                'source' => 'bpom_2d_barcode',
                'barcode' => $barcode,
                'bpom_number' => $bpomNumber,
                'cekbpom_url' => 'https://cekbpom.pom.go.id/',
            ];
        }

        $providers = [
            fn () => $this->lookupOpenFactsFamily($barcode, 'https://world.openbeautyfacts.org', 'open_beauty_facts'),
            fn () => $this->lookupOpenFactsFamily($barcode, 'https://world.openproductsfacts.org', 'open_products_facts'),
            fn () => $this->lookupUpcItemDb($barcode),
        ];

        foreach ($providers as $resolver) {
            $match = $resolver();
            if ($match !== null) {
                return [
                    'status' => 'external_found',
                    'source' => $match['source'],
                    'barcode' => $barcode,
                    'suggestion' => $match['suggestion'],
                ];
            }
        }

        return [
            'status' => 'not_found',
            'source' => null,
            'barcode' => $barcode,
        ];
    }

    /**
     * @return array{source: string, suggestion: array<string, mixed>}|null
     */
    private function lookupOpenFactsFamily(string $barcode, string $baseUrl, string $sourceKey): ?array
    {
        if (! preg_match('/^[0-9A-Za-z\-]{6,32}$/', $barcode)) {
            return null;
        }

        try {
            $url = rtrim($baseUrl, '/').'/api/v2/product/'.rawurlencode($barcode).'.json';
            $response = Http::timeout(3)
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
                'source' => $sourceKey,
                'suggestion' => [
                    'barcode' => $barcode,
                    'name' => mb_substr($name, 0, 255),
                    'brand' => mb_substr($brand, 0, 100),
                    'weight_grams' => $this->parseWeightGrams($quantity !== '' ? $quantity : $name),
                    'short_ingredients' => $ingredients !== '' ? mb_substr($ingredients, 0, 255) : null,
                    'description' => $genericName !== '' ? $genericName : null,
                    'suggested_sku' => $this->generateUniqueSkuFromName($name),
                    'suggested_category_id' => $this->guessCategoryId($name.' '.$categoriesText),
                    'image_preview_url' => ! empty($product['image_front_url']) ? (string) $product['image_front_url'] : null,
                ],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{source: string, suggestion: array<string, mixed>}|null
     */
    private function lookupUpcItemDb(string $barcode): ?array
    {
        if (! preg_match('/^[0-9]{8,14}$/', $barcode)) {
            return null;
        }

        try {
            $response = Http::timeout(3)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'RiasKu-Inventory/1.0'])
                ->get('https://api.upcitemdb.com/prod/trial/lookup', [
                    'upc' => $barcode,
                ]);

            if (! $response->ok()) {
                return null;
            }

            $payload = $response->json();
            $items = is_array($payload) ? ($payload['items'] ?? []) : [];
            if (! is_array($items) || count($items) === 0 || ! is_array($items[0])) {
                return null;
            }

            $item = $items[0];
            $name = trim((string) ($item['title'] ?? ''));
            if ($name === '') {
                return null;
            }

            $brand = trim((string) ($item['brand'] ?? ''));
            $weightRaw = trim((string) ($item['weight'] ?? ''));
            $description = trim((string) ($item['description'] ?? ''));
            $category = trim((string) ($item['category'] ?? ''));
            $images = is_array($item['images'] ?? null) ? $item['images'] : [];

            return [
                'source' => 'upcitemdb',
                'suggestion' => [
                    'barcode' => $barcode,
                    'name' => mb_substr($name, 0, 255),
                    'brand' => mb_substr($brand, 0, 100),
                    'weight_grams' => $this->parseWeightGrams($weightRaw !== '' ? $weightRaw : $name),
                    'short_ingredients' => null,
                    'description' => $description !== '' ? $description : null,
                    'suggested_sku' => $this->generateUniqueSkuFromName($name),
                    'suggested_category_id' => $this->guessCategoryId($name.' '.$category),
                    'image_preview_url' => ! empty($images[0]) ? (string) $images[0] : null,
                ],
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

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|l|liter|ml|g|gr|gram|oz)\b/i', $quantity, $matches)) {
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
            'lip' => ['lip', 'lipstick', 'lipcream', 'tint', 'gloss', 'balm', 'mousse'],
            'eye' => ['eye', 'eyeshadow', 'mascara', 'eyeliner', 'brow', 'eyebrow'],
            'complexion' => ['cushion', 'foundation', 'powder', 'concealer', 'bb cream', 'cc cream', 'primer', 'blush'],
            'skincare' => ['face wash', 'cleanser', 'facial', 'wash', 'serum', 'toner', 'moisturizer', 'sunscreen', 'micellar', 'skincare', 'cream', 'lotion', 'body wash'],
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
