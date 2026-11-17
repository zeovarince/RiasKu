<?php

namespace App\Services;

use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\TarifPengiriman;
use App\Models\ZonaPengiriman;
use Illuminate\Validation\ValidationException;

class ShippingCostService
{
    public function hitungBerat(array $items): int
    {
        $total = 0;
        $defaultWeight = (int) config('shipping.default_weight_grams');

        foreach ($items as $index => $item) {
            $product = $this->resolveProduct($item, $index);
            $unitWeight = (int) $product->weight_grams;
            $total += ($unitWeight > 0 ? $unitWeight : $defaultWeight) * (int) $item['quantity'];
        }

        return $total + $this->beratKemasan();
    }

    public function beratKemasan(): int
    {
        return max(0, (int) config('shipping.packaging_weight_grams', 0));
    }

    public function beratTagihan(int $weightGrams, TarifPengiriman|string $courier): int
    {
        $rules = $this->aturanBerat($courier);
        $weightGrams = max(0, $weightGrams);
        $roundedGrams = intdiv($weightGrams + $rules['rounding_step_grams'] - 1, $rules['rounding_step_grams'])
            * $rules['rounding_step_grams'];

        return max($rules['min_billable_weight_grams'], $roundedGrams);
    }

    public function beratVolumetrikGrams(int $lengthCm, int $widthCm, int $heightCm): int
    {
        $volumeCm3 = max(0, $lengthCm) * max(0, $widthCm) * max(0, $heightCm);
        $divisor = max(1, (int) config('shipping.volumetric_divisor_cm3_per_kg', 6000));

        return intdiv($volumeCm3 * 1000 + $divisor - 1, $divisor);
    }

    public function beratAktualAtauVolumetrik(int $actualWeightGrams, ?array $dimensionsCm = null): int
    {
        if ($dimensionsCm === null) {
            return max(0, $actualWeightGrams);
        }

        return max(
            max(0, $actualWeightGrams),
            $this->beratVolumetrikGrams(
                (int) ($dimensionsCm['length'] ?? 0),
                (int) ($dimensionsCm['width'] ?? 0),
                (int) ($dimensionsCm['height'] ?? 0),
            ),
        );
    }

    public function hitungSubtotal(array $items): int
    {
        $subtotal = 0;
        foreach ($items as $index => $item) {
            $product = $this->resolveProduct($item, $index);
            $subtotal += (int) $product->retail_price * (int) $item['quantity'];
        }

        return $subtotal;
    }

    public function hitungOngkir(int $subtotal, int $weightGrams, ZonaPengiriman $zone): array
    {
        if (! $zone->is_active) {
            throw ValidationException::withMessages([
                'destination_zone_id' => ['Zona pengiriman tidak aktif.'],
            ]);
        }

        $rates = TarifPengiriman::query()
            ->where('zona_pengiriman_id', $zone->id)
            ->where('is_active', true)
            ->orderByRaw("CASE courier WHEN 'JNE' THEN 0 WHEN 'J&T' THEN 1 ELSE 2 END")
            ->orderBy('courier')
            ->orderBy('service')
            ->get();

        if ($rates->isEmpty()) {
            throw ValidationException::withMessages([
                'destination_zone_id' => ['Belum ada layanan pengiriman aktif untuk zona ini.'],
            ]);
        }

        $freeShippingThreshold = (int) config('shipping.free_shipping_min_subtotal', 0);
        $isFreeShipping = $freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold;

        return [
            'weight_grams' => $weightGrams,
            'packaging_weight_grams' => $this->beratKemasan(),
            'options' => $rates->map(function (TarifPengiriman $rate) use ($isFreeShipping, $weightGrams): array {
                $billableWeightGrams = $this->beratTagihan(
                    $weightGrams,
                    $rate,
                );
                $weightRules = $this->aturanBerat($rate);

                return [
                    'courier' => $rate->courier,
                    'service' => $rate->service,
                    'billable_weight_kg' => $billableWeightGrams / 1000,
                    'min_billable_weight_grams' => $weightRules['min_billable_weight_grams'],
                    'rounding_step_grams' => $weightRules['rounding_step_grams'],
                    'cost' => $isFreeShipping
                        ? 0
                        : intdiv($billableWeightGrams * (int) $rate->price_per_kg + 999, 1000),
                    'etd_min_days' => $rate->etd_min_days,
                    'etd_max_days' => $rate->etd_max_days,
                ];
            })->all(),
        ];
    }

    private function aturanBerat(TarifPengiriman|string $courier): array
    {
        $courierName = $courier instanceof TarifPengiriman ? $courier->courier : $courier;
        $rules = config('shipping.courier_weight_rules.'.mb_strtolower($courierName));

        $rules = $rules ?: config('shipping.default_weight_rule', [
            'min_billable_weight_grams' => 1000,
            'rounding_step_grams' => 1000,
        ]);

        return [
            'min_billable_weight_grams' => max(1, (int) ($rules['min_billable_weight_grams'] ?? 1000)),
            'rounding_step_grams' => max(1, (int) ($rules['rounding_step_grams'] ?? 1000)),
        ];
    }

    public function resolveTarif(int $zoneId, string $courier, string $service): TarifPengiriman
    {
        $zone = ZonaPengiriman::query()->whereKey($zoneId)->first();
        if (! $zone || ! $zone->is_active) {
            throw ValidationException::withMessages([
                'destination_zone_id' => ['Zona pengiriman tidak ditemukan atau tidak aktif.'],
            ]);
        }

        $rate = TarifPengiriman::query()
            ->where('zona_pengiriman_id', $zone->id)
            ->where('courier', $courier)
            ->where('service', $service)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if (! $rate) {
            throw ValidationException::withMessages([
                'service' => ['Kurir atau layanan pengiriman tidak ditemukan atau tidak aktif untuk zona ini.'],
            ]);
        }

        return $rate;
    }

    public function validasiCod(ZonaPengiriman $zone): void
    {
        $codCities = array_map('mb_strtolower', (array) config('shipping.cod_cities', []));
        if (! in_array(mb_strtolower($zone->city), $codCities, true)) {
            throw ValidationException::withMessages([
                'payment_method' => ['Pembayaran COD hanya tersedia untuk kota tertentu. Pilih pembayaran online.'],
            ]);
        }
    }

    private function resolveProduct(array $item, int $index): Produk
    {
        $product = $item['product'] ?? Produk::query()->find($item['product_id'] ?? null);
        if (! $product || ! $product->is_active) {
            throw ValidationException::withMessages([
                "items.{$index}.product_id" => ['Produk tidak ditemukan atau sedang tidak aktif.'],
            ]);
        }

        if (! isset($item['product'])) {
            $variant = ProdukVarian::query()
                ->whereKey($item['variant_id'] ?? null)
                ->where('produk_id', $product->id)
                ->where('is_active', true)
                ->first();

            if (! $variant) {
                throw ValidationException::withMessages([
                    "items.{$index}.variant_id" => ['Varian tidak ditemukan atau bukan milik produk tersebut.'],
                ]);
            }
        }

        return $product;
    }
}
