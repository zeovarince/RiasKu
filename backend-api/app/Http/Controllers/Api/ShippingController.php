<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ZonaPengiriman;
use App\Services\ShippingCostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function zones(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ], [
            'q.string' => 'Kata pencarian zona harus berupa teks.',
            'q.max' => 'Kata pencarian zona maksimal 100 karakter.',
        ]);

        $query = ZonaPengiriman::query()->where('is_active', true);
        if (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('province', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('district', 'like', "%{$search}%")
                    ->orWhere('postal_code', 'like', "%{$search}%");
            });
        }

        $codCities = array_map('mb_strtolower', (array) config('shipping.cod_cities', []));
        $zones = $query->orderBy('province')->orderBy('city')->orderBy('district')->get()
            ->map(fn (ZonaPengiriman $zone): array => [
                'id' => $zone->id,
                'province' => $zone->province,
                'city' => $zone->city,
                'district' => $zone->district,
                'postal_code' => $zone->postal_code,
                'cod_available' => in_array(mb_strtolower($zone->city), $codCities, true),
            ]);

        return response()->json([
            'message' => 'Daftar zona pengiriman berhasil dimuat.',
            'data' => $zones,
        ]);
    }

    public function rates(Request $request, ShippingCostService $shipping): JsonResponse
    {
        $validated = $request->validate([
            'destination_zone_id' => ['required', 'integer', 'exists:zona_pengiriman,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:produk,id'],
            'items.*.variant_id' => ['required', 'integer', 'exists:produk_varian,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'destination_zone_id.required' => 'Zona tujuan wajib dipilih.',
            'destination_zone_id.integer' => 'Zona tujuan tidak valid.',
            'destination_zone_id.exists' => 'Zona tujuan tidak ditemukan.',
            'items.required' => 'Keranjang wajib berisi minimal satu produk.',
            'items.min' => 'Keranjang wajib berisi minimal satu produk.',
            'items.*.product_id.required' => 'Produk wajib dipilih.',
            'items.*.product_id.integer' => 'ID produk harus berupa angka bulat.',
            'items.*.product_id.exists' => 'Produk tidak ditemukan.',
            'items.*.variant_id.required' => 'Varian produk wajib dipilih.',
            'items.*.variant_id.integer' => 'ID varian harus berupa angka bulat.',
            'items.*.variant_id.exists' => 'Varian produk tidak ditemukan.',
            'items.*.quantity.required' => 'Jumlah produk wajib diisi.',
            'items.*.quantity.integer' => 'Jumlah produk harus berupa angka bulat.',
            'items.*.quantity.min' => 'Jumlah produk minimal 1.',
        ]);

        $zone = ZonaPengiriman::query()->findOrFail($validated['destination_zone_id']);
        $weight = $shipping->hitungBerat($validated['items']);
        $subtotal = $shipping->hitungSubtotal($validated['items']);
        $quote = $shipping->hitungOngkir($subtotal, $weight, $zone);

        return response()->json([
            'message' => 'Ongkos kirim berhasil dihitung.',
            'data' => [
                'destination' => $zone,
                'subtotal' => $subtotal,
                'weight_grams' => $quote['weight_grams'],
                'packaging_weight_grams' => $quote['packaging_weight_grams'],
                'options' => $quote['options'],
            ],
        ]);
    }
}
