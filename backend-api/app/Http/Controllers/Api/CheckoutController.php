<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\ZonaPengiriman;
use App\Services\OrderStatusService;
use App\Services\ShippingCostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $this->customerFrom($request);
        $orders = $customer->pesanan()
            ->with(['items', 'zona'])
            ->latest('id')
            ->paginate(10);

        return response()->json([
            'message' => 'Daftar pesanan berhasil dimuat.',
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function store(Request $request, ShippingCostService $shipping): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:cod,online'],
            'destination_zone_id' => ['required', 'integer', 'exists:zona_pengiriman,id'],
            'courier' => ['required', 'string', 'max:100'],
            'service' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:produk,id'],
            'items.*.variant_id' => ['required', 'integer', 'distinct', 'exists:produk_varian,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'name.required' => 'Nama penerima wajib diisi.',
            'phone.required' => 'Nomor WhatsApp wajib diisi.',
            'address.required' => 'Alamat pengiriman wajib diisi.',
            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'payment_method.in' => 'Metode pembayaran tidak valid.',
            'destination_zone_id.required' => 'Zona tujuan wajib dipilih.',
            'destination_zone_id.integer' => 'Zona tujuan tidak valid.',
            'destination_zone_id.exists' => 'Zona tujuan tidak ditemukan.',
            'courier.required' => 'Kurir wajib dipilih.',
            'service.required' => 'Layanan pengiriman wajib dipilih.',
            'items.required' => 'Keranjang wajib berisi minimal satu produk.',
            'items.min' => 'Keranjang wajib berisi minimal satu produk.',
            'items.*.product_id.required' => 'Produk wajib dipilih.',
            'items.*.product_id.integer' => 'ID produk harus berupa angka bulat.',
            'items.*.product_id.exists' => 'Produk tidak ditemukan.',
            'items.*.variant_id.required' => 'Varian produk wajib dipilih.',
            'items.*.variant_id.integer' => 'ID varian harus berupa angka bulat.',
            'items.*.variant_id.distinct' => 'Varian produk tidak boleh dipilih lebih dari sekali.',
            'items.*.variant_id.exists' => 'Varian produk tidak ditemukan.',
            'items.*.quantity.required' => 'Jumlah produk wajib diisi.',
            'items.*.quantity.integer' => 'Jumlah produk harus berupa angka bulat.',
            'items.*.quantity.min' => 'Jumlah produk minimal 1.',
        ]);
        $customer = $this->customerFrom($request);

        $order = DB::transaction(function () use ($validated, $customer, $shipping): Pesanan {
            $zone = ZonaPengiriman::query()
                ->whereKey($validated['destination_zone_id'])
                ->lockForUpdate()
                ->first();
            if (! $zone || ! $zone->is_active) {
                throw ValidationException::withMessages([
                    'destination_zone_id' => ['Zona pengiriman tidak ditemukan atau tidak aktif.'],
                ]);
            }

            $rate = $shipping->resolveTarif(
                (int) $zone->id,
                $validated['courier'],
                $validated['service'],
            );
            if ($validated['payment_method'] === 'cod') {
                $shipping->validasiCod($zone);
            }

            $orderItems = [];
            $shippingItems = [];
            $subtotal = 0;

            $indexedItems = [];
            foreach ($validated['items'] as $index => $item) {
                $indexedItems[] = ['index' => $index, 'item' => $item];
            }

            // Urutkan deterministik sebelum lockForUpdate agar urutan keranjang berbeda tidak memicu deadlock MySQL.
            usort($indexedItems, static function (array $a, array $b): int {
                $prodCmp = ((int) $a['item']['product_id']) <=> ((int) $b['item']['product_id']);
                if ($prodCmp !== 0) {
                    return $prodCmp;
                }

                return ((int) $a['item']['variant_id']) <=> ((int) $b['item']['variant_id']);
            });

            foreach ($indexedItems as $entry) {
                $index = $entry['index'];
                $item = $entry['item'];
                $product = Produk::query()
                    ->whereKey($item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ['Produk tidak ditemukan atau sedang tidak aktif.'],
                    ]);
                }

                $variant = ProdukVarian::query()
                    ->whereKey($item['variant_id'])
                    ->where('produk_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if (! $variant || ! $variant->is_active) {
                    throw ValidationException::withMessages([
                        "items.{$index}.variant_id" => ['Varian tidak ditemukan atau bukan milik produk tersebut.'],
                    ]);
                }

                $quantity = (int) $item['quantity'];
                $availableStock = (int) $variant->stock;
                $isLinePreorder = $availableStock <= 0 && (bool) $product->is_preorder;

                if ($isLinePreorder) {
                    if ($quantity > 99) {
                        throw ValidationException::withMessages([
                            "items.{$index}.quantity" => ["Jumlah Pre-Order {$product->name} ({$variant->name}) maksimal 99 pcs."],
                        ]);
                    }
                    $stockDeducted = false;
                } else {
                    if ($availableStock < $quantity) {
                        throw ValidationException::withMessages([
                            "items.{$index}.quantity" => ["Stok {$product->name} ({$variant->name}) tidak mencukupi."],
                        ]);
                    }
                    $variant->decrement('stock', $quantity);
                    $stockDeducted = true;
                }

                $unitPrice = (int) $product->retail_price;
                $lineTotal = $unitPrice * $quantity;
                $subtotal += $lineTotal;
                $shippingItems[$index] = [
                    'product' => $product,
                    'quantity' => $quantity,
                ];

                $orderItems[$index] = [
                    'produk_id' => $product->id,
                    'produk_varian_id' => $variant->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant->name,
                    'sku' => $product->sku,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                    'is_preorder' => $isLinePreorder,
                    'preorder_estimate_days' => $isLinePreorder ? $product->preorder_estimate_days : null,
                    'stock_deducted' => $stockDeducted,
                ];
            }

            ksort($shippingItems);
            $shippingItems = array_values($shippingItems);
            ksort($orderItems);
            $orderItems = array_values($orderItems);

            $hasPreorderItems = collect($orderItems)->contains(fn (array $item): bool => (bool) $item['is_preorder']);
            $preorderEstimateDays = $hasPreorderItems
                ? (int) collect($orderItems)->filter(fn (array $item): bool => (bool) $item['is_preorder'])->max('preorder_estimate_days')
                : null;

            $weightGrams = $shipping->hitungBerat($shippingItems);
            $quote = $shipping->hitungOngkir($subtotal, $weightGrams, $zone);
            $selectedOption = collect($quote['options'])->first(
                fn (array $option): bool => $option['courier'] === $rate->courier
                    && $option['service'] === $rate->service,
            );
            if (! $selectedOption) {
                throw ValidationException::withMessages([
                    'service' => ['Layanan pengiriman tidak tersedia untuk zona ini.'],
                ]);
            }
            $shippingCost = $selectedOption['cost'];

            $order = Pesanan::query()->create([
                'order_code' => 'RSK-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'pelanggan_id' => $customer->id,
                'recipient_name' => $validated['name'],
                'recipient_phone' => $validated['phone'],
                'shipping_address' => $validated['address'],
                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_method'] === 'online' ? 'pending' : 'unpaid',
                'status' => 'pending',
                'has_preorder_items' => $hasPreorderItems,
                'preorder_estimate_days' => $preorderEstimateDays ?: null,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $subtotal + $shippingCost,
                'shipping_zone_id' => $zone->id,
                'destination_province' => $zone->province,
                'destination_city' => $zone->city,
                'destination_district' => $zone->district,
                'destination_postal_code' => $zone->postal_code,
                'courier' => $rate->courier,
                'courier_service' => $rate->service,
                'shipping_etd' => $rate->etd_min_days.'-'.$rate->etd_max_days.' hari',
                'weight_grams' => $weightGrams,
                'notes' => $validated['notes'] ?? null,
            ]);

            $order->items()->createMany($orderItems);
            app(OrderStatusService::class)->recordInitialLog(
                $order,
                'pelanggan',
                $customer->id,
                $validated['name'],
                $validated['payment_method'] === 'cod'
                    ? 'Pesanan COD dibuat dan sedang diproses.'
                    : 'Pesanan dibuat, menunggu pembayaran.',
            );
            $order->setAttribute('packaging_weight_grams', $quote['packaging_weight_grams']);
            $order->setAttribute('billable_weight_kg', $selectedOption['billable_weight_kg']);

            return $order->load(['items', 'zona', 'statusLogs']);
        }, 3);

        return response()->json([
            'message' => 'Pesanan berhasil dibuat.',
            'data' => $order,
        ], 201);
    }

    public function show(Request $request, string $orderCode): JsonResponse
    {
        $customer = $this->customerFrom($request);
        $order = $customer->pesanan()
            ->with(['items', 'zona'])
            ->where('order_code', $orderCode)
            ->firstOrFail();

        return response()->json([
            'message' => 'Detail pesanan berhasil dimuat.',
            'data' => $order,
        ]);
    }

    private function customerFrom(Request $request): Pelanggan
    {
        $customer = $request->user();
        abort_unless($customer instanceof Pelanggan, 403, 'Akses hanya untuk pelanggan.');

        return $customer;
    }
}
